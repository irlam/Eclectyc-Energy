<?php
/**
 * eclectyc-energy/app/http/routes.php
 * Application route definitions for Slim framework
 * Last updated: 06/11/2024 14:45:00
 */

use App\Http\Controllers\Admin\SitesController;
use App\Http\Controllers\Admin\TariffsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

// Homepage / Dashboard
$container = $app->getContainer();

$app->get('/', [DashboardController::class, 'index'])
    ->setName('dashboard')
    ->add(AuthMiddleware::class);

$app->get('/login', [AuthController::class, 'showLoginForm'])->setName('auth.login');
$app->post('/login', [AuthController::class, 'login'])->setName('auth.login.submit');
$app->map(['GET', 'POST'], '/logout', [AuthController::class, 'logout'])
    ->setName('auth.logout')
    ->add(AuthMiddleware::class);

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
    $group->get('/sites', [SitesController::class, 'index'])->setName('admin.sites');
    $group->get('/tariffs', [TariffsController::class, 'index'])->setName('admin.tariffs');
    $group->get('/users', [UsersController::class, 'index'])->setName('admin.users');
})->add(function ($request, $handler) use ($container) {
    $middleware = new AuthMiddleware($container->get(AuthService::class), ['admin']);
    return $middleware->process($request, $handler);
});

// Reports Routes
$app->group('/reports', function ($group) {
    $group->get('/consumption', [ReportsController::class, 'consumption'])->setName('reports.consumption');
    $group->get('/costs', [ReportsController::class, 'costs'])->setName('reports.costs');
})->add(function ($request, $handler) use ($container) {
    $middleware = new AuthMiddleware($container->get(AuthService::class), ['admin', 'manager']);
    return $middleware->process($request, $handler);
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