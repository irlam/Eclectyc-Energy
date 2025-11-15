<?php
declare(strict_types=1);

/**
 * File: public/index.php
 * Name: Eclectyc Energy Slim Front Controller
 *
 * What this file does:
 *  - Bootstraps the Slim framework application.
 *  - Loads environment variables and sets UK timezone.
 *  - Configures PHP error handling (development vs production).
 *  - Builds and configures the Dependency Injection (DI) container.
 *  - Registers Twig (view layer) with explicit DI bindings to fix autowire errors.
 *  - Registers PDO database connection (nullable) with explicit DI bindings.
 *  - Registers all controllers, middleware, logger, and services.
 *  - Adds middlewares (routing, Twig, auth globals, error handling, CORS for /api).
 *  - Loads application route definitions.
 *  - Runs the Slim application with graceful error fallback in production.
 *
 * Why this update (UK format: 09/11/2025 12:55:00):
 *  - FIX: “Slim Application Error” caused by PHP-DI trying to autowire Slim\Views\Twig
 *    and failing on Twig\Loader\LoaderInterface being not instantiable.
 *    RESOLVED by adding explicit container bindings for Twig::class and PDO::class,
 *    plus a manual binding for SettingsController.
 *  - Modernised header, enabled strict_types, improved comments.
 *  - Provided aliases 'view' and 'db' for backwards compatibility while binding
 *    Twig::class and PDO::class for autowiring.
 *  - Added SettingsController binding so /tools/settings works.
 *  - Ensured UK date format remains for logging.
 *
 * Last updated (UK): 09/11/2025 12:55:00
 */

use App\Http\Controllers\Admin\AiInsightsController;
use App\Http\Controllers\Admin\AlarmsController;
use App\Http\Controllers\Admin\DocsController;
use App\Http\Controllers\Admin\ExportsController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\MetersController as AdminMetersController;
use App\Http\Controllers\Admin\ScheduledReportsController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SitesController;
use App\Http\Controllers\Admin\TariffsController;
use App\Http\Controllers\Admin\TariffSwitchingController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Api\CarbonIntensityController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportJobController;
use App\Http\Controllers\Api\ImportStatusController;
use App\Http\Controllers\Api\MetersController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotFoundController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\Tools\SftpController;
use App\Http\Controllers\ToolsController;
use App\Http\Middleware\AuthMiddleware;
use App\Http\Middleware\RedirectParameterCleanupMiddleware;
use App\Services\AuthService;
use DI\Container;
use Dotenv\Dotenv;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;

// -----------------------------------------------------------------------------
// PHP error reporting & environment preparation
// -----------------------------------------------------------------------------

error_reporting(E_ALL);
ini_set('display_errors', '0'); // Overridden below if in development

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/vendor/autoload.php';

// Load .env if available
if (class_exists(Dotenv::class)) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
} else {
    error_log('Dotenv library not available; skipping .env loading.');
}

// UK timezone
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

// Start session early
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Determine environment
$appEnv    = $_ENV['APP_ENV'] ?? 'production';
$debugMode = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($appEnv === 'development' || $debugMode) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');

    $errorLogPath = BASE_PATH . '/logs/php-error.log';
    if (!is_dir(dirname($errorLogPath))) {
        @mkdir(dirname($errorLogPath), 0775, true);
    }

    ini_set('error_log', $errorLogPath);
}

// -----------------------------------------------------------------------------
// Dependency Injection Container
// -----------------------------------------------------------------------------

$container = new Container();

/**
 * Bind Twig::class (explicit) and 'view' alias.
 *
 * This prevents PHP-DI from attempting to autowire Twig internals (LoaderInterface).
 * It also sets up global Twig variables used across templates.
 */
$container->set(Twig::class, function () {
    $twig = Twig::create(BASE_PATH . '/app/views', [
        'cache' => false, // For production you could use BASE_PATH . '/cache'
        'debug' => (($_ENV['APP_ENV'] ?? 'production') === 'development'),
    ]);

    $env = $twig->getEnvironment();
    $env->addGlobal('app_name', 'Eclectyc Energy');
    $env->addGlobal('app_url', $_ENV['APP_URL'] ?? 'https://eclectyc.energy');
    $env->addGlobal('current_year', date('Y'));
    $env->addGlobal('auth', [
        'check' => isset($_SESSION['user']),
        'user'  => $_SESSION['user'] ?? null,
    ]);

    return $twig;
});

// Backwards-compatible alias for legacy code retrieving 'view'
$container->set('view', fn (ContainerInterface $c) => $c->get(Twig::class));

/**
 * Bind PDO::class (nullable) and 'db' alias.
 *
 * Returns null if connection fails—controllers using ?PDO should handle that gracefully.
 */
$container->set(PDO::class, function () {
    $host    = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port    = $_ENV['DB_PORT'] ?? '3306';
    $dbname  = $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'energy_platform';
    $user    = $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? 'root';
    $pass    = $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '';
    $charset = $_ENV['DB_CHARSET'] ?? 'utf8mb4';

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $host,
        $port,
        $dbname,
        $charset
    );

    try {
        $pdo = new PDO(
            $dsn,
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
                PDO::ATTR_TIMEOUT            => 5,  // Connection timeout to prevent hanging connections
            ]
        );

        return $pdo;
    } catch (PDOException $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        return null;
    }
});

// Backwards-compatible alias
$container->set('db', fn (ContainerInterface $c) => $c->get(PDO::class));

/**
 * Monolog logger (UK date format).
 */
$container->set('logger', function () {
    $logger   = new Logger('eclectyc-energy');
    $logLevel = strtoupper($_ENV['LOG_LEVEL'] ?? 'INFO');
    $logPath  = BASE_PATH . '/' . ($_ENV['LOG_PATH'] ?? 'logs/app.log');
    $logDir   = dirname($logPath);

    if (!is_dir($logDir)) {
        @mkdir($logDir, 0777, true);
    }

    $handler = new StreamHandler($logPath, $logLevel);
    $handler->setFormatter(
        new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'd/m/Y H:i:s'
        )
    );

    $logger->pushHandler($handler);

    return $logger;
});

// -----------------------------------------------------------------------------
// Services & Controllers
// -----------------------------------------------------------------------------

$container->set(AuthService::class, fn (ContainerInterface $c) => new AuthService($c->get('db')));

$container->set(AuthController::class, fn (ContainerInterface $c) => new AuthController(
    $c->get('view'),
    $c->get(AuthService::class)
));

$container->set(DashboardController::class, fn (ContainerInterface $c) => new DashboardController(
    $c->get('view'),
    $c->get('db')
));

$container->set(ReportsController::class, fn (ContainerInterface $c) => new ReportsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(UsersController::class, fn (ContainerInterface $c) => new UsersController(
    $c->get('view'),
    $c->get('db')
));

$container->set(SitesController::class, fn (ContainerInterface $c) => new SitesController(
    $c->get('view'),
    $c->get('db')
));

$container->set(ImportController::class, fn (ContainerInterface $c) => new ImportController(
    $c->get('view'),
    $c->get('db')
));

$container->set(ExportsController::class, fn (ContainerInterface $c) => new ExportsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(TariffsController::class, fn (ContainerInterface $c) => new TariffsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(TariffSwitchingController::class, fn (ContainerInterface $c) => new TariffSwitchingController(
    $c->get('view'),
    $c->get('db')
));

$container->set(MetersController::class, fn (ContainerInterface $c) => new MetersController(
    $c->get('db')
));

$container->set(AdminMetersController::class, fn (ContainerInterface $c) => new AdminMetersController(
    $c->get('view'),
    $c->get('db')
));

$container->set(ImportStatusController::class, fn (ContainerInterface $c) => new ImportStatusController(
    $c->get('db')
));

$container->set(ImportJobController::class, fn (ContainerInterface $c) => new ImportJobController(
    $c->get('db')
));

$container->set(CarbonIntensityController::class, fn (ContainerInterface $c) => new CarbonIntensityController(
    $c->get('db')
));

$container->set(ToolsController::class, fn (ContainerInterface $c) => new ToolsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(SftpController::class, fn (ContainerInterface $c) => new SftpController(
    $c->get('view'),
    $c->get('db')
));

$container->set(SettingsController::class, fn (ContainerInterface $c) => new SettingsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(DocsController::class, fn (ContainerInterface $c) => new DocsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(AiInsightsController::class, fn (ContainerInterface $c) => new AiInsightsController(
    $c->get('db'),
    $c->get('view')
));

$container->set(AlarmsController::class, fn (ContainerInterface $c) => new AlarmsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(ScheduledReportsController::class, fn (ContainerInterface $c) => new ScheduledReportsController(
    $c->get('view'),
    $c->get('db')
));

$container->set(AuthMiddleware::class, fn (ContainerInterface $c) => new AuthMiddleware(
    $c->get(AuthService::class)
));

$container->set(HealthController::class, fn (ContainerInterface $c) => new HealthController($c));

$container->set(NotFoundController::class, fn (ContainerInterface $c) => new NotFoundController(
    $c->get('view')
));

// -----------------------------------------------------------------------------
// Slim App creation & Middleware
// -----------------------------------------------------------------------------

AppFactory::setContainer($container);
$app = AppFactory::create();

// Optional: set base path if deploying in a subdirectory
// $app->setBasePath('/eclectyc-energy');

// Core routing middleware
$app->addRoutingMiddleware();

/**
 * Redirect parameter cleanup middleware.
 *
 * Added AFTER routing middleware so it runs BEFORE route handlers and route-specific middleware.
 * This prevents URLs like /?redirect=%2F from causing loops by normalising or clearing
 * problematic redirect parameters.
 */
$app->add(new RedirectParameterCleanupMiddleware());

/**
 * Auth globals refresh middleware.
 *
 * Ensures 'auth' global reflects current session on every request,
 * so Twig templates always see up-to-date login state.
 */
$app->add(function ($request, $handler) use ($container) {
    /** @var Twig $twig */
    $twig = $container->get('view');

    $twig->getEnvironment()->addGlobal('auth', [
        'check' => isset($_SESSION['user']),
        'user'  => $_SESSION['user'] ?? null,
    ]);

    return $handler->handle($request);
});

// Twig middleware (uses Twig::class binding)
$app->add(TwigMiddleware::createFromContainer($app, Twig::class));

/**
 * Error middleware:
 *  - display details in development / debug mode
 *  - always log errors (configured earlier)
 */
$errorMiddleware = $app->addErrorMiddleware(
    $debugMode,
    true,
    true
);

// CORS middleware (only for /api endpoints)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);

    if (str_starts_with($request->getUri()->getPath(), '/api')) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization'
            )
            ->withHeader(
                'Access-Control-Allow-Methods',
                'GET, POST, PUT, DELETE, PATCH, OPTIONS'
            );
    }

    return $response;
});

// -----------------------------------------------------------------------------
// Routes
// -----------------------------------------------------------------------------

require BASE_PATH . '/app/Http/routes.php';

// -----------------------------------------------------------------------------
// Run Application
// -----------------------------------------------------------------------------

try {
    $app->run();
} catch (Throwable $e) {
    error_log('Application error: ' . $e->getMessage());

    if ($appEnv === 'production') {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'error' => 'Internal Server Error',
        ]);
    } else {
        throw $e;
    }
}
