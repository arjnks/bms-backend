<?php
$hash = \Illuminate\Support\Facades\Hash::make("leo123");
$count = \App\Models\User::where("role", "customer")->update(["password" => $hash]);
echo "Updated $count passwords.\n";

