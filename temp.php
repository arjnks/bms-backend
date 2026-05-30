<?php
$dummyUsers = \App\Models\User::where("role", "customer")->where("email", "not like", "customer_%")->pluck("id");
$count = \App\Models\User::whereIn("id", $dummyUsers)->delete();
echo "Deleted: " . $count;

