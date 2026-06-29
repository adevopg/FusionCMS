<?php
// Router script for PHP built-in server (test only) — emulates FusionCMS .htaccess
$path = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path !== __DIR__ . '/' && is_file($path)) {
    return false; // serve the requested static asset as-is
}
require __DIR__ . '/index.php';
