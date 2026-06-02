<?php
echo "R2 Key: " . (env("CLOUDFLARE_R2_ACCESS_KEY_ID") ? "SET (" . substr(env("CLOUDFLARE_R2_ACCESS_KEY_ID"), 0, 6) . "...)" : "EMPTY") . "\n";
echo "R2 Secret: " . (env("CLOUDFLARE_R2_SECRET_ACCESS_KEY") ? "SET" : "EMPTY") . "\n";
echo "R2 Endpoint: " . (env("CLOUDFLARE_R2_ENDPOINT") ? env("CLOUDFLARE_R2_ENDPOINT") : "EMPTY") . "\n";

