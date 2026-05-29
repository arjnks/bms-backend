<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = DB::connection()->getPdo();

$tables = ['users', 'customers', 'bills', 'reminder_rules', 'reminder_logs', 'login_logs', 'personal_access_tokens'];
$sql = "SET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $table) {
    try {
        // Get CREATE TABLE
        $create = DB::select("SHOW CREATE TABLE `$table`");
        $createSql = $create[0]->{'Create Table'};
        $sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql .= $createSql . ";\n\n";
        
        // Get data
        $rows = DB::select("SELECT * FROM `$table`");
        if (!empty($rows)) {
            $columns = array_keys((array)$rows[0]);
            $colList = '`' . implode('`, `', $columns) . '`';
            $sql .= "INSERT INTO `$table` ($colList) VALUES\n";
            $values = [];
            foreach ($rows as $row) {
                $row = (array)$row;
                $vals = array_map(function($v) use ($db) {
                    if ($v === null) return 'NULL';
                    return $db->quote($v);
                }, $row);
                $values[] = '(' . implode(', ', $vals) . ')';
            }
            $sql .= implode(",\n", $values) . ";\n\n";
        }
        echo "Exported $table: " . count($rows) . " rows\n";
    } catch (\Exception $e) {
        echo "Skipped $table: " . $e->getMessage() . "\n";
    }
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
file_put_contents(__DIR__ . '/../bms_export.sql', $sql);
echo "\nExport saved to bms_export.sql\n";
echo "File size: " . number_format(filesize(__DIR__ . '/../bms_export.sql') / 1024 / 1024, 2) . " MB\n";
