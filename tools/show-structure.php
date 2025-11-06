<?php
/**
 * eclectyc-energy/tools/show-structure.php
 * Displays project structure in a visual tree format
 * Last updated: 06/11/2025 20:25:00
 */

// Build concise ASCII tree for embedding in Twig template
$basePath = dirname(__DIR__);

function shouldSkipDirectory(string $directory, string $relativePath): bool
{
    if ($directory === 'vendor' && $relativePath !== '') {
        return true;
    }

    if (str_starts_with($relativePath, 'vendor/')) {
        return true;
    }

    return false;
}

function buildTree(string $directory, string $prefix = '', string $relativePath = '', int $depth = 0): string
{
    $items = scandir($directory) ?: [];
    $items = array_values(array_filter($items, static function ($item) {
        return $item[0] !== '.';
    }));

    usort($items, static function ($a, $b) use ($directory) {
        $pathA = $directory . '/' . $a;
        $pathB = $directory . '/' . $b;

        $aIsDir = is_dir($pathA);
        $bIsDir = is_dir($pathB);

        if ($aIsDir === $bIsDir) {
            return strcasecmp($a, $b);
        }

        return $aIsDir ? -1 : 1;
    });

    $output = '';

    foreach ($items as $index => $item) {
        $path = $directory . '/' . $item;
        $isDir = is_dir($path);
        $isLast = $index === count($items) - 1;
        $connector = $isLast ? '└── ' : '├── ';

        if ($isDir && shouldSkipDirectory($item, trim($relativePath . '/' . $item, '/'))) {
            $output .= $prefix . $connector . '[vendor trimmed]' . PHP_EOL;
            continue;
        }

        $output .= $prefix . $connector . $item . ($isDir ? '/' : '') . PHP_EOL;

        if ($isDir && $depth < 6) {
            $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $output .= buildTree($path, $newPrefix, trim($relativePath . '/' . $item, '/'), $depth + 1);
        }
    }

    return $output;
}

$tree = 'eclectyc-energy/' . PHP_EOL;
$tree .= buildTree($basePath);

echo $tree;