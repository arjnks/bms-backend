<?php
$admin = \App\Models\User::where("email", "admin@leogroup.in")->first();
if ($admin) {
    echo "Admin exists. Password hash: " . $admin->password;
} else {
    echo "Admin does NOT exist!";
}

