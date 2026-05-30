<?php
$u = \App\Models\User::where("role", "customer")->first();
echo "user_id: " . $u->id . "\n";
echo "username: " . $u->username . "\n";
echo "customer relation: ";
if ($u->customer) {
    echo "EXISTS - customer_id: " . $u->customer->id . "\n";
    print_r($u->customer->toArray());
} else {
    echo "NULL - no customer record linked!\n";
}

