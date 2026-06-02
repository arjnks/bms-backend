<?php
use Illuminate\Support\Facades\Route;
Route::get("/debug-r2", function() {
    return [
        "bucket" => config("filesystems.disks.r2.bucket"),
        "has_key" => !empty(config("filesystems.disks.r2.key")),
        "has_secret" => !empty(config("filesystems.disks.r2.secret")),
        "has_endpoint" => !empty(config("filesystems.disks.r2.endpoint")),
        "endpoint" => config("filesystems.disks.r2.endpoint"),
        "env_key" => !empty(env("CLOUDFLARE_R2_ACCESS_KEY_ID"))
    ];
});

