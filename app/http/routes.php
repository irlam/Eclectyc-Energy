<?php
/**
 * eclectyc-energy/app/http/routes.php
 * Application route definitions for Slim framework
 * Last updated: 06/11/2024 14:45:00
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MetersController;
use App\Http\Controllers\ImportController;

// Homepage / Dashboard
$app->get('/', function (Request $request, Response $response) use ($app) {
    $view = $app->getContainer()->get('view');
    
    // Get some basic stats for dashboard
    $db = $app->getContainer()->get('db');
    $stats = [
        'total_sites' => 0,
        'total_meters' => 0,
        'total_readings' => 0,
        'last_import' => 'Never'
    ];
    
    if ($db) {
        try {
            // Get stats from database
            $stmt = $db->query("SELECT COUNT(*) as count FROM sites");
            $stats['total_sites'] = $stmt->fetch()['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM meters");
            $stats['total_meters'] = $stmt->fetch()['count'] ?? 0;
            
            $stmt = $db->query("SELECT COUNT(*) as count FROM meter_readings");
            $stats['total_readings'] = $stmt->fetch()['count'] ?? 0;
            
            $stmt = $db->query("SELECT MAX(created_at) as last FROM meter_readings");
            $last = $stmt->fetch()['last'];
            if ($last) {
                $stats['last_import'] = date('d/m/Y H:i:s', strtotime($last));
            }
        } catch (Exception $e) {
            // Database not initialized yet
        }
    }
    
    return $view->render($response, 'dashboard.twig', [
        'page_title' => 'Dashboard',
        'stats' => $stats
    ]);
});

// API Routes
$app->group('/api', function ($group) {
    // Health check endpoint
    $group->get('/health', HealthController::class . ':check');
    
    // Meter readings API
    $group->get('/meters', function (Request $request, Response $response) use ($group) {
        $db = $group->getContainer()->get('db');
        $meters = [];
        
        if ($db) {
            try {
                $stmt = $db->query("
                    SELECT m.*, s.name as site_name 
                    FROM meters m 
                    LEFT JOIN sites s ON m.site_id = s.id 
                    ORDER BY m.mpan
                ");
                $meters = $stmt->fetchAll();
            } catch (Exception $e) {
                // Handle error
            }
        }
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $meters
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Get readings for a specific meter
    $group->get('/meters/{mpan}/readings', function (Request $request, Response $response, $args) use ($group) {
        $mpan = $args['mpan'];
        $db = $group->getContainer()->get('db');
        $readings = [];
        
        if ($db) {
            try {
                $stmt = $db->prepare("
                    SELECT * FROM meter_readings 
                    WHERE meter_id = (SELECT id FROM meters WHERE mpan = ?)
                    ORDER BY reading_date DESC, reading_time DESC
                    LIMIT 100
                ");
                $stmt->execute([$mpan]);
                $readings = $stmt->fetchAll();
            } catch (Exception $e) {
                // Handle error
            }
        }
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'mpan' => $mpan,
            'data' => $readings
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
    
    // Import status
    $group->get('/import/status', function (Request $request, Response $response) use ($group) {
        $db = $group->getContainer()->get('db');
        $status = [
            'last_import' => null,
            'total_imported' => 0,
            'pending_files' => []
        ];
        
        if ($db) {
            try {
                $stmt = $db->query("
                    SELECT COUNT(*) as count, MAX(created_at) as last 
                    FROM meter_readings
                ");
                $result = $stmt->fetch();
                $status['total_imported'] = $result['count'];
                $status['last_import'] = $result['last'];
            } catch (Exception $e) {
                // Handle error
            }
        }
        
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'data' => $status
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    });
});

// Tools Routes
$app->group('/tools', function ($group) {
    // Structure checker
    $group->get('/check-structure', function (Request $request, Response $response) {
        // Include the check structure tool
        ob_start();
        require BASE_PATH . '/tools/check-structure.php';
        $output = ob_get_clean();
        
        $response->getBody()->write($output);
        return $response;
    });
    
    // Structure viewer
    $group->get('/show-structure', function (Request $request, Response $response) {
        // Include the show structure tool
        ob_start();
        require BASE_PATH . '/tools/show-structure.php';
        $output = ob_get_clean();
        
        $response->getBody()->write($output);
        return $response;
    });
});

// Admin Routes (future expansion)
$app->group('/admin', function ($group) {
    // Sites management
    $group->get('/sites', function (Request $request, Response $response) use ($group) {
        $view = $group->getContainer()->get('view');
        
        return $view->render($response, 'admin/sites.twig', [
            'page_title' => 'Sites Management'
        ]);
    });
    
    // Tariffs management
    $group->get('/tariffs', function (Request $request, Response $response) use ($group) {
        $view = $group->getContainer()->get('view');
        
        return $view->render($response, 'admin/tariffs.twig', [
            'page_title' => 'Tariffs Management'
        ]);
    });
    
    // Users management
    $group->get('/users', function (Request $request, Response $response) use ($group) {
        $view = $group->getContainer()->get('view');
        
        return $view->render($response, 'admin/users.twig', [
            'page_title' => 'Users Management'
        ]);
    });
});

// Reports Routes
$app->group('/reports', function ($group) {
    // Energy consumption report
    $group->get('/consumption', function (Request $request, Response $response) use ($group) {
        $view = $group->getContainer()->get('view');
        
        return $view->render($response, 'reports/consumption.twig', [
            'page_title' => 'Energy Consumption Report'
        ]);
    });
    
    // Cost analysis report
    $group->get('/costs', function (Request $request, Response $response) use ($group) {
        $view = $group->getContainer()->get('view');
        
        return $view->render($response, 'reports/costs.twig', [
            'page_title' => 'Cost Analysis Report'
        ]);
    });
});

// Catch-all for 404
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function (Request $request, Response $response) use ($app) {
    $view = $app->getContainer()->get('view');
    
    return $view->render($response->withStatus(404), 'error.twig', [
        'page_title' => 'Page Not Found',
        'error_code' => 404,
        'error_message' => 'The requested page could not be found.'
    ]);
});