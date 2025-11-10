<?php
/**
 * eclectyc-energy/app/http/routes.php
 * Application route definitions for Slim framework
 * Last updated: 06/11/2024 14:45:00
 */

use App\Http\Controllers\Admin\DocsController;
use App\Http\Controllers\Admin\ExportsController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MetersController as AdminMetersController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SitesController;
use App\Http\Controllers\Admin\TariffsController;
use App\Http\Controllers\Admin\TariffSwitchingController;
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
use App\Http\Controllers\Tools\SftpController;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Slim\Psr7\Stream;

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
    $group->get('', [ToolsController::class, 'index'])->setName('tools.index');
    $group->get('/check', [ToolsController::class, 'checkStructure'])->setName('tools.check');
    $group->get('/show', [ToolsController::class, 'showStructure'])->setName('tools.show');
    $group->get('/system-health', [ToolsController::class, 'systemHealth'])->setName('tools.health');
    $group->map(['GET', 'POST'], '/email-test', [ToolsController::class, 'emailTest'])->setName('tools.email');
    $group->get('/cli-tools', [ToolsController::class, 'cliTools'])->setName('tools.cli');
    $group->get('/cron-jobs', [ToolsController::class, 'cronJobs'])->setName('tools.cron');
    $group->get('/logs', [ToolsController::class, 'viewLogs'])->setName('tools.logs');
    $group->post('/logs/clear', [ToolsController::class, 'clearLogs'])->setName('tools.logs.clear');
    
    // SFTP configuration routes
    $group->get('/sftp', [SftpController::class, 'index'])->setName('tools.sftp');
    $group->get('/sftp/create', [SftpController::class, 'create'])->setName('tools.sftp.create');
    $group->post('/sftp', [SftpController::class, 'store'])->setName('tools.sftp.store');
    $group->get('/sftp/{id}/edit', [SftpController::class, 'edit'])->setName('tools.sftp.edit');
    $group->post('/sftp/{id}', [SftpController::class, 'update'])->setName('tools.sftp.update');
    $group->post('/sftp/{id}/delete', [SftpController::class, 'delete'])->setName('tools.sftp.delete');
    $group->get('/sftp/{id}/test', [SftpController::class, 'testConnection'])->setName('tools.sftp.test');
    $group->get('/sftp/{id}/files', [SftpController::class, 'listFiles'])->setName('tools.sftp.files');
    $group->post('/sftp/{id}/import', [SftpController::class, 'importFile'])->setName('tools.sftp.import');
    
    // System Settings routes
    $group->get('/settings', [SettingsController::class, 'index'])->setName('tools.settings');
    $group->post('/settings', [SettingsController::class, 'update'])->setName('tools.settings.update');
    $group->post('/settings/reset', [SettingsController::class, 'reset'])->setName('tools.settings.reset');
    
    // Documentation routes
    $group->get('/docs', [DocsController::class, 'index'])->setName('tools.docs');
    $group->get('/docs/{filename:.+}', [DocsController::class, 'view'])->setName('tools.docs.view');
})->add(function ($request, $handler) use ($container) {
    $middleware = new AuthMiddleware($container->get(AuthService::class), ['admin']);
    return $middleware->process($request, $handler);
});

// Public showcase assets (accessible without authentication)
$app->get('/showcase[/{path:.*}]', function ($request, $response, array $args) {
    $relativePath = $args['path'] ?? 'index.html';

    if ($relativePath === '' || substr($relativePath, -1) === '/') {
        $relativePath = rtrim($relativePath, '/') . '/index.html';
    }

    $relativePath = ltrim($relativePath, '/');

    $basePath = BASE_PATH . '/showcase';
    $baseRealPath = realpath($basePath);
    $targetRealPath = realpath($basePath . '/' . $relativePath);

    if (!$baseRealPath || !$targetRealPath || strpos($targetRealPath, $baseRealPath) !== 0 || !is_file($targetRealPath)) {
        $body = $response->getBody();
        $body->write('Showcase asset not found.');
        return $response
            ->withBody($body)
            ->withStatus(404)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    $extension = strtolower(pathinfo($targetRealPath, PATHINFO_EXTENSION));
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

    $handle = fopen($targetRealPath, 'rb');

    if ($handle === false) {
        $body = $response->getBody();
        $body->write('Failed to read showcase asset.');
        return $response
            ->withBody($body)
            ->withStatus(500)
            ->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    $stream = new Stream($handle);

    return $response
        ->withHeader('Content-Type', $mime)
        ->withHeader('Cache-Control', 'public, max-age=3600')
        ->withBody($stream);
});

// Admin Routes (future expansion)
$app->group('/admin', function ($group) {
    $group->get('/imports', [ImportController::class, 'index'])->setName('admin.imports');
    $group->post('/imports', [ImportController::class, 'upload'])->setName('admin.imports.upload');
    $group->get('/imports/history', [ImportController::class, 'history'])->setName('admin.imports.history');
    $group->post('/imports/history/{id}/delete', [ImportController::class, 'deleteHistory'])->setName('admin.imports.history.delete');
    $group->post('/imports/history/delete-bulk', [ImportController::class, 'deleteHistoryBulk'])->setName('admin.imports.history.delete-bulk');
    $group->post('/imports/history/delete-all', [ImportController::class, 'deleteHistoryAll'])->setName('admin.imports.history.delete-all');
    $group->get('/imports/jobs', [ImportController::class, 'jobs'])->setName('admin.imports.jobs');
    $group->get('/imports/status/{batchId}', [ImportController::class, 'status'])->setName('admin.imports.status');
    $group->post('/imports/retry', [ImportController::class, 'retry'])->setName('admin.imports.retry');
    $group->post('/imports/jobs/{id}/delete', [ImportController::class, 'deleteJob'])->setName('admin.imports.delete');
    $group->post('/imports/jobs/{id}/cancel', [ImportController::class, 'cancelJob'])->setName('admin.imports.cancel');
    $group->get('/exports', [ExportsController::class, 'index'])->setName('admin.exports');

    // Meter management
    $group->get('/meters', [AdminMetersController::class, 'index'])->setName('admin.meters');
    $group->get('/meters/create', [AdminMetersController::class, 'create'])->setName('admin.meters.create');
    $group->post('/meters', [AdminMetersController::class, 'store'])->setName('admin.meters.store');
    $group->get('/meters/{id}/edit', [AdminMetersController::class, 'edit'])->setName('admin.meters.edit');
    $group->get('/meters/{id}/carbon', [AdminMetersController::class, 'carbonIntensity'])->setName('admin.meters.carbon');
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
    
    // Tariff switching analysis routes
    $group->get('/tariff-switching', [TariffSwitchingController::class, 'index'])->setName('admin.tariff_switching');
    $group->post('/tariff-switching/analyze', [TariffSwitchingController::class, 'analyze'])->setName('admin.tariff_switching.analyze');
    $group->get('/tariff-switching/{id}/quick', [TariffSwitchingController::class, 'quickAnalyze'])->setName('admin.tariff_switching.quick');
    $group->get('/tariff-switching/{id}/history', [TariffSwitchingController::class, 'history'])->setName('admin.tariff_switching.history');
    
    // Users CRUD routes
    $group->get('/users', [UsersController::class, 'index'])->setName('admin.users');
    $group->get('/users/create', [UsersController::class, 'create'])->setName('admin.users.create');
    $group->post('/users', [UsersController::class, 'store'])->setName('admin.users.store');
    $group->get('/users/{id}/edit', [UsersController::class, 'edit'])->setName('admin.users.edit');
    $group->post('/users/{id}', [UsersController::class, 'update'])->setName('admin.users.update');
    $group->post('/users/{id}/delete', [UsersController::class, 'delete'])->setName('admin.users.delete');
    $group->get('/users/{id}/access', [UsersController::class, 'manageAccess'])->setName('admin.users.access');
    $group->post('/users/{id}/access', [UsersController::class, 'updateAccess'])->setName('admin.users.access.update');
})->add(function ($request, $handler) use ($container) {
    $middleware = new AuthMiddleware($container->get(AuthService::class), ['admin']);
    return $middleware->process($request, $handler);
});

// Reports Routes
$app->group('/reports', function ($group) {
    $group->get('/consumption', [ReportsController::class, 'consumption'])->setName('reports.consumption');
    $group->get('/costs', [ReportsController::class, 'costs'])->setName('reports.costs');
    $group->get('/data-quality', [ReportsController::class, 'dataQuality'])->setName('reports.data_quality');
    $group->get('/hh-consumption', [ReportsController::class, 'hhConsumption'])->setName('reports.hh_consumption');
    $group->get('/daily-usage-comparison', [ReportsController::class, 'dailyUsageComparison'])->setName('reports.daily_usage_comparison');
})->add(function ($request, $handler) use ($container) {
    $middleware = new AuthMiddleware($container->get(AuthService::class), ['admin', 'manager']);
    return $middleware->process($request, $handler);
});

// Catch-all for 404
$app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', NotFoundController::class);