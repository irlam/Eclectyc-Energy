<?php
/**
 * eclectyc-energy/app/http/routes.php
 * Application route definitions for Slim framework
 * Last updated: 06/11/2024 14:45:00
 */

use App\Http\Controllers\Admin\ExportsController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MetersController as AdminMetersController;
use App\Http\Controllers\Admin\SitesController;
use App\Http\Controllers\Admin\TariffsController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Api\CarbonIntensityController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportStatusController;
use App\Http\Controllers\Api\ImportJobController;
use App\Http\Controllers\Api\MetersController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotFoundController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\ToolsController;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;

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
    
    // Import job tracking
    $group->get('/import/jobs', [ImportJobController::class, 'getJobs']);
    $group->get('/import/jobs/{batchId}', [ImportJobController::class, 'getStatus']);
    
    // Carbon intensity endpoints
    $group->get('/carbon-intensity', [CarbonIntensityController::class, 'getCurrent']);
    $group->post('/carbon-intensity/refresh', [CarbonIntensityController::class, 'refresh']);
    $group->get('/carbon-intensity/history', [CarbonIntensityController::class, 'getHistory']);
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
    $group->get('/imports/jobs', [ImportController::class, 'jobs'])->setName('admin.imports.jobs');
    $group->get('/imports/status/{batchId}', [ImportController::class, 'status'])->setName('admin.imports.status');
    $group->post('/imports/retry', [ImportController::class, 'retry'])->setName('admin.imports.retry');
    $group->get('/exports', [ExportsController::class, 'index'])->setName('admin.exports');

    // Meter management
    $group->get('/meters', [AdminMetersController::class, 'index'])->setName('admin.meters');
    $group->get('/meters/create', [AdminMetersController::class, 'create'])->setName('admin.meters.create');
    $group->post('/meters', [AdminMetersController::class, 'store'])->setName('admin.meters.store');
    $group->get('/meters/{id}/edit', [AdminMetersController::class, 'edit'])->setName('admin.meters.edit');
    $group->post('/meters/{id}', [AdminMetersController::class, 'update'])->setName('admin.meters.update');
    $group->post('/meters/{id}/delete', [AdminMetersController::class, 'delete'])->setName('admin.meters.delete');
    
    // Sites CRUD routes
    $group->get('/sites', [SitesController::class, 'index'])->setName('admin.sites');
    $group->get('/sites/create', [SitesController::class, 'create'])->setName('admin.sites.create');
    $group->post('/sites', [SitesController::class, 'store'])->setName('admin.sites.store');
    $group->get('/sites/{id}/edit', [SitesController::class, 'edit'])->setName('admin.sites.edit');
    $group->post('/sites/{id}', [SitesController::class, 'update'])->setName('admin.sites.update');
    $group->post('/sites/{id}/delete', [SitesController::class, 'delete'])->setName('admin.sites.delete');
    
    $group->get('/tariffs', [TariffsController::class, 'index'])->setName('admin.tariffs');
    $group->get('/tariffs/create', [TariffsController::class, 'create'])->setName('admin.tariffs.create');
    $group->post('/tariffs', [TariffsController::class, 'store'])->setName('admin.tariffs.store');
    $group->get('/tariffs/{id}/edit', [TariffsController::class, 'edit'])->setName('admin.tariffs.edit');
    $group->post('/tariffs/{id}', [TariffsController::class, 'update'])->setName('admin.tariffs.update');
    $group->post('/tariffs/{id}/delete', [TariffsController::class, 'delete'])->setName('admin.tariffs.delete');
    
    // Users CRUD routes
    $group->get('/users', [UsersController::class, 'index'])->setName('admin.users');
    $group->get('/users/create', [UsersController::class, 'create'])->setName('admin.users.create');
    $group->post('/users', [UsersController::class, 'store'])->setName('admin.users.store');
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
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', NotFoundController::class);