<?php
/**
 * eclectyc-energy/public/router.php
 * PHP built-in server router for development
 * Last updated: 06/11/2024 14:45:00
 */


$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = urldecode($uri);

$basePath = dirname(__DIR__);
$publicPath = __DIR__;
$showcasePath = $basePath . '/showcase';

if (strpos($uri, '/showcase') === 0) {
    $relativePath = substr($uri, strlen('/showcase'));
    if ($relativePath === '' || $relativePath === '/') {
        $relativePath = '/index.html';
    }

    $target = realpath($showcasePath . $relativePath);
    $showcaseRoot = realpath($showcasePath);

    if ($target && $showcaseRoot && strpos($target, $showcaseRoot) === 0 && is_file($target)) {
        $extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $mimeTypes = [
            'html' => 'text/html; charset=utf-8',
            'htm' => 'text/html; charset=utf-8',
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];

        $mime = $mimeTypes[$extension] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=3600');
        readfile($target);
        return true;
    }
}

$requested = $publicPath . $uri;

// Serve static files directly
if ($uri !== '/' && file_exists($requested) && !is_dir($requested)) {
    return false;
}

// Route everything else through index.php
require_once __DIR__ . '/index.php';