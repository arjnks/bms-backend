<?php
$cucodes = \App\Models\Customer::whereNotNull("external_cucode")->distinct()->pluck("external_cucode")->toArray();
echo "Unique cucodes: " . implode(", ", $cucodes) . "\n";

