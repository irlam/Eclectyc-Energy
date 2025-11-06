<?php
/**
 * eclectyc-energy/app/http/routes.php
 * Application route definitions for Slim framework
 * Last updated: 06/11/2024 14:45:00
 */

use App\Http\Controllers\Admin\ExportsController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\SitesController;
use App\Http\Controllers\Admin\TariffsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportStatusController;
use App\Http\Controllers\Api\MetersController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ToolsController;
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
    
    $group->get('/meters', [MetersController::class, 'index']);
    $group->get('/meters/{mpan}/readings', [MetersController::class, 'readings']);
    $group->get('/import/status', [ImportStatusController::class, 'index']);
});

// Tools Routes
$app->group('/tools', function ($group) {
    $group->get('/check', [ToolsController::class, 'checkStructure'])->setName('tools.check');
    $group->get('/show', [ToolsController::class, 'showStructure'])->setName('tools.show');
})->add(function ($request, $handler) use ($container) {
    $middleware = new AuthMiddleware($container->get(AuthService::class), ['admin']);
    return $middleware->process($request, $handler);
});

// Admin Routes (future expansion)
$app->group('/admin', function ($group) {
    $group->get('/imports', [ImportController::class, 'index'])->setName('admin.imports');
    $group->post('/imports', [ImportController::class, 'upload'])->setName('admin.imports.upload');
    $group->get('/imports/history', [ImportController::class, 'history'])->setName('admin.imports.history');
    $group->get('/exports', [ExportsController::class, 'index'])->setName('admin.exports');
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