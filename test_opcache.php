<?php
if (function_exists("opcache_reset")) {
    opcache_reset();
    echo "Opcache reset.";
} else {
    echo "Opcache not enabled.";
}

