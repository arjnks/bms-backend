<?php
$c = app(App\Http\Controllers\Api\V1\Admin\ReportController::class);
echo $c->collections(request())->getContent();

