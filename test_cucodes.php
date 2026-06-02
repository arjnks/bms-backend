<?php
echo json_encode(\App\Models\Customer::whereNotNull("external_cucode")->pluck("external_cucode")->toArray());

