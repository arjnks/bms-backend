<?php
$b = App\Models\Bill::where("invoice_no", "LPH/2627/110154")->first();
$c = app(App\Http\Controllers\Api\V1\Admin\BillController::class);
echo $c->show($b->id)->getContent();

