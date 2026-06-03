<?php
$cols = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM bill_line_items");
echo json_encode($cols);

