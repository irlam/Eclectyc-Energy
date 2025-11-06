<?php
/**
 * eclectyc-energy/tools/show-structure.php
 * Displays project structure in a visual tree format
 * Last updated: 06/11/2024 14:45:00
 */

// Set base path
$basePath = dirname(__DIR__);

// Function to scan directory
function scanDirectory($dir, $prefix = '', $baseDir = '') {
    $items = [];
    $files = scandir($dir);
    
    // Remove . and .. and hidden files
    $files = array_filter($files, function($file) {
        return $file[0] !== '.';
    });
    
    // Sort: directories first, then files
    usort($files, function($a, $b) use ($dir) {
        $aIsDir = is_dir($dir . '/' . $a);
        $bIsDir = is_dir($dir . '/' . $b);
        
        if ($aIsDir && !$bIsDir) return -1;
        if (!$aIsDir && $bIsDir) return 1;
        
        return strcasecmp($a, $b);
    });
    
    foreach ($files as $i => $file) {
        $path = $dir . '/' . $file;
        $isLast = ($i === count($files) - 1);
        $isDir = is_dir($path);
        
        // Skip vendor directory contents (too large)
        if ($file === 'vendor' && $baseDir !== '') {
            $items[] = [
                'name' => $file,
                'type' => 'folder',
                'prefix' => $prefix,
                'isLast' => $isLast,
                'children' => []
            ];
            continue;
        }
        
        $item = [
            'name' => $file,
            'type' => $isDir ? 'folder' : 'file',
            'prefix' => $prefix,
            'isLast' => $isLast,
            'children' => []
        ];
        
        // Recursively scan subdirectories (limit depth)
        if ($isDir && substr_count($prefix, '│') < 3) {
            $newPrefix = $prefix . ($isLast ? '    ' : '│   ');
            $item['children'] = scanDirectory($path, $newPrefix, $baseDir . '/' . $file);
        }
        
        $items[] = $item;
    }
    
    return $items;
}

// Get structure
$structure = scanDirectory($basePath, '', '');

// Function to render tree
function renderTree($items, $html = '') {
    foreach ($items as $item) {
        $icon = $item['type'] === 'folder' ? '📁' : '📄';
        $connector = $item['isLast'] ? '└──' : '├──';
        $class = $item['type'] === 'folder' ? 'folder' : 'file';
        
        $html .= '<div class="tree-item">';
        $html .= '<span class="tree-prefix">' . htmlspecialchars($item['prefix']) . '</span>';
        $html .= '<span class="tree-connector">' . $connector . '</span> ';
        $html .= $icon . ' ';
        $html .= '<span class="' . $class . '">' . htmlspecialchars($item['name']) . '</span>';
        $html .= '</div>';
        
        if (!empty($item['children'])) {
            $html = renderTree($item['children'], $html);
        }
    }
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Structure - Eclectyc Energy</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .tree-view {
            font-family: 'Courier New', Courier, monospace;
            background: #f3f4f6;
            padding: 1.5rem;
            border-radius: 8px;
            overflow-x: auto;
        }
        .tree-item {
            line-height: 1.8;
            white-space: nowrap;
        }
        .tree-prefix, .tree-connector {
            color: #6b7280;
        }
        .folder {
            color: #2563eb;
            font-weight: bold;
        }
        .file {
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="container" style="padding: 2rem;">
        <h1>Eclectyc Energy Project Structure</h1>
        <p>Generated: <?php echo date('d/m/Y H:i:s'); ?></p>
        
        <div class="card">
            <h2>📂 eclectyc-energy</h2>
            <div class="tree-view">
                <?php echo renderTree($structure); ?>
            </div>
        </div>
        
        <div class="card">
            <h3>Legend</h3>
            <p>📁 Folder | 📄 File</p>
            <p><strong>Note:</strong> The vendor directory contents are not shown for brevity.</p>
        </div>
        
        <div style="margin-top: 2rem;">
            <a href="/" class="btn btn-primary">Back to Dashboard</a>
            <a href="/tools/check-structure" class="btn btn-secondary">Check Structure</a>
        </div>
    </div>
</body>
</html>