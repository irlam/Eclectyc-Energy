<?php
/**
 * eclectyc-energy/tools/check-structure.php
 * Validates project structure and reports missing files/folders
 * Last updated: 06/11/2024 14:45:00
 */

// Determine if running from CLI or web
$isCli = php_sapi_name() === 'cli';

// Set base path
$basePath = dirname(__DIR__);

// Define expected structure
$expectedStructure = [
    'directories' => [
    'app',
    'app/Config',
    'app/Http',
    'app/Http/Controllers',
    'app/Http/Controllers/Api',
        'app/Domain',
        'app/Domain/Ingestion',
        'app/Domain/Aggregation',
        'app/Domain/Tariffs',
        'app/Domain/Analytics',
    'app/Domain/Exports',
    'app/Models',
        'app/views',
        'database',
        'database/migrations',
        'database/seeds',
        'public',
        'public/assets',
        'public/assets/css',
        'public/assets/js',
        'scripts',
        'tools',
        'logs',
        'vendor'
    ],
    'files' => [
        'composer.json',
        'README.md',
        '.env.example',
        'public/index.php',
        'public/router.php',
        'public/assets/css/style.css',
        'public/assets/js/app.js',
    'app/Http/routes.php',
    'app/Http/Controllers/Api/HealthController.php',
        'tools/check-structure.php',
        'tools/show-structure.php',
        'scripts/import_csv.php',
        'scripts/aggregate_cron.php',
        'scripts/export_sftp.php'
    ]
];

// Check function
function checkStructure($basePath, $structure) {
    $results = [
        'missing_directories' => [],
        'missing_files' => [],
        'found_directories' => [],
        'found_files' => [],
        'warnings' => []
    ];
    
    // Check directories
    foreach ($structure['directories'] as $dir) {
        $fullPath = $basePath . '/' . $dir;
        if (is_dir($fullPath)) {
            $results['found_directories'][] = $dir;
        } else {
            $results['missing_directories'][] = $dir;
        }
    }
    
    // Check files
    foreach ($structure['files'] as $file) {
        $fullPath = $basePath . '/' . $file;
        if (file_exists($fullPath)) {
            $results['found_files'][] = $file;
        } else {
            $results['missing_files'][] = $file;
        }
    }
    
    // Check for .env file
    if (!file_exists($basePath . '/.env')) {
        $results['warnings'][] = '.env file not found - copy .env.example and configure';
    }
    
    // Check permissions
    if (!is_writable($basePath . '/logs')) {
        $results['warnings'][] = 'logs directory not writable';
    }
    
    // Calculate health score
    $totalItems = count($structure['directories']) + count($structure['files']);
    $foundItems = count($results['found_directories']) + count($results['found_files']);
    $results['health_score'] = round(($foundItems / $totalItems) * 100, 2);
    
    return $results;
}

// Run check
$results = checkStructure($basePath, $expectedStructure);

// Output results
if ($isCli) {
    // CLI output
    echo "\n";
    echo "===========================================\n";
    echo "  Eclectyc Energy - Structure Check\n";
    echo "  " . date('d/m/Y H:i:s') . "\n";
    echo "===========================================\n\n";
    
    echo "Health Score: " . $results['health_score'] . "%\n\n";
    
    if (empty($results['missing_directories']) && empty($results['missing_files'])) {
        echo "✅ All required files and directories are present!\n";
    } else {
        if (!empty($results['missing_directories'])) {
            echo "❌ Missing Directories:\n";
            foreach ($results['missing_directories'] as $dir) {
                echo "   - $dir\n";
            }
            echo "\n";
        }
        
        if (!empty($results['missing_files'])) {
            echo "❌ Missing Files:\n";
            foreach ($results['missing_files'] as $file) {
                echo "   - $file\n";
            }
            echo "\n";
        }
    }
    
    if (!empty($results['warnings'])) {
        echo "⚠️  Warnings:\n";
        foreach ($results['warnings'] as $warning) {
            echo "   - $warning\n";
        }
        echo "\n";
    }
    
    echo "Summary:\n";
    echo "  Directories: " . count($results['found_directories']) . "/" . count($expectedStructure['directories']) . "\n";
    echo "  Files: " . count($results['found_files']) . "/" . count($expectedStructure['files']) . "\n";
    echo "\n";
    
    // Exit code for CI/CD
    exit($results['health_score'] == 100 ? 0 : 1);
    
} else {
    // Web output
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Structure Check - Eclectyc Energy</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
        <div class="container" style="padding: 2rem;">
            <h1>Project Structure Check</h1>
            <p>Last updated: <?php echo date('d/m/Y H:i:s'); ?></p>
            
            <div class="card">
                <h2>Health Score: <?php echo $results['health_score']; ?>%</h2>
                <div style="width: 100%; background: #e5e7eb; border-radius: 9999px; height: 20px;">
                    <div style="width: <?php echo $results['health_score']; ?>%; background: <?php echo $results['health_score'] == 100 ? '#10b981' : ($results['health_score'] > 75 ? '#f59e0b' : '#ef4444'); ?>; border-radius: 9999px; height: 100%;"></div>
                </div>
            </div>
            
            <?php if (!empty($results['missing_directories'])): ?>
            <div class="card">
                <h3>❌ Missing Directories</h3>
                <ul>
                    <?php foreach ($results['missing_directories'] as $dir): ?>
                        <li><?php echo htmlspecialchars($dir); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['missing_files'])): ?>
            <div class="card">
                <h3>❌ Missing Files</h3>
                <ul>
                    <?php foreach ($results['missing_files'] as $file): ?>
                        <li><?php echo htmlspecialchars($file); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($results['warnings'])): ?>
            <div class="card">
                <h3>⚠️ Warnings</h3>
                <ul>
                    <?php foreach ($results['warnings'] as $warning): ?>
                        <li><?php echo htmlspecialchars($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h3>Summary</h3>
                <p>Directories: <?php echo count($results['found_directories']); ?>/<?php echo count($expectedStructure['directories']); ?></p>
                <p>Files: <?php echo count($results['found_files']); ?>/<?php echo count($expectedStructure['files']); ?></p>
            </div>
            
            <div style="margin-top: 2rem;">
                <a href="/" class="btn btn-primary">Back to Dashboard</a>
                <a href="/tools/show-structure" class="btn btn-secondary">View Structure</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}