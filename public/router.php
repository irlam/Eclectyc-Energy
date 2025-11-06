<?php
/**
 * eclectyc-energy/public/router.php
 * PHP built-in server router for development
 * Last updated: 06/11/2024 14:45:00
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$requested = __DIR__ . $uri;

// Serve static files directly
if ($uri !== '/' && file_exists($requested) && !is_dir($requested)) {
    return false;
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';