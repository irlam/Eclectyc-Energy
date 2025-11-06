<?php
/**
 * eclectyc-energy/public/index.php
 * Main application entry point - bootstraps the Slim framework application
 * Last updated: 06/11/2024 14:45:00
 */

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
use DI\Container;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Psr\Container\ContainerInterface;

// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Will be overridden by environment

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load environment variables
if (class_exists('Dotenv\\Dotenv')) {
    $dotenv = Dotenv::createImmutable(BASE_PATH);
    $dotenv->safeLoad();
} else {
    error_log('Dotenv library not available; skipping .env loading.');
}

// Set timezone to UK
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Europe/London');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Environment-specific error handling
$appEnv = $_ENV['APP_ENV'] ?? 'production';
$debugMode = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

if ($appEnv === 'development' || $debugMode) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', BASE_PATH . '/logs/php-error.log');
}

// Create container
$container = new Container();

// Add Twig to container
$container->set('view', function() {
    $twig = Twig::create(BASE_PATH . '/app/views', [
        'cache' => false, // Set to BASE_PATH . '/cache' in production
        'debug' => (($_ENV['APP_ENV'] ?? 'production') === 'development')
    ]);
    
    // Add global variables
    $twig->getEnvironment()->addGlobal('app_name', 'Eclectyc Energy');
    $twig->getEnvironment()->addGlobal('app_url', $_ENV['APP_URL'] ?? 'https://eclectyc.energy');
    $twig->getEnvironment()->addGlobal('current_year', date('Y'));
    $twig->getEnvironment()->addGlobal('auth', [
        'check' => isset($_SESSION['user']),
        'user' => $_SESSION['user'] ?? null,
    ]);
    
    return $twig;
});

// Add database connection to container
$container->set('db', function() {
    $dbConfig = [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'database' => $_ENV['DB_DATABASE'] ?? 'energy_platform',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4'
    ];
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database'],
            $dbConfig['charset']
        );
        
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        return $pdo;
    } catch (PDOException $e) {
        // Log error securely
        error_log('Database connection failed: ' . $e->getMessage());
        
        // Return null to handle gracefully in controllers
        return null;
    }
});

// Add logger to container
$container->set('logger', function() {
    $logger = new \Monolog\Logger('eclectyc-energy');
    
    $logLevel = $_ENV['LOG_LEVEL'] ?? 'INFO';
    $logPath = BASE_PATH . '/' . ($_ENV['LOG_PATH'] ?? 'logs/app.log');
    
    // Ensure log directory exists
    $logDir = dirname($logPath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $handler = new \Monolog\Handler\StreamHandler($logPath, $logLevel);
    $handler->setFormatter(new \Monolog\Formatter\LineFormatter(
        "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
        'd/m/Y H:i:s' // UK date format
    ));
    
    $logger->pushHandler($handler);
    
    return $logger;
});

$container->set(AuthService::class, function(Container $c) {
    return new AuthService($c->get('db'));
});

$container->set(AuthController::class, function(Container $c) {
    return new AuthController(
        $c->get('view'),
        $c->get(AuthService::class)
    );
});

$container->set(DashboardController::class, function(Container $c) {
    return new DashboardController(
        $c->get('view'),
        $c->get('db')
    );
});

$container->set(ReportsController::class, function(Container $c) {
    return new ReportsController(
        $c->get('view'),
        $c->get('db')
    );
});

$container->set(UsersController::class, function(Container $c) {
    return new UsersController(
        $c->get('view'),
        $c->get('db')
    );
});

$container->set(SitesController::class, function(Container $c) {
    return new SitesController(
        $c->get('view'),
        $c->get('db')
    );
});

$container->set(TariffsController::class, function(Container $c) {
    return new TariffsController(
        $c->get('view'),
        $c->get('db')
    );
});

$container->set(MetersController::class, function(Container $c) {
    return new MetersController($c->get('db'));
});

$container->set(ImportStatusController::class, function(Container $c) {
    return new ImportStatusController($c->get('db'));
});

$container->set(ToolsController::class, function(Container $c) {
    return new ToolsController($c->get('view'));
});

$container->set(AuthMiddleware::class, function(Container $c) {
    return new AuthMiddleware($c->get(AuthService::class));
});

$container->set(HealthController::class, function(ContainerInterface $c) {
    return new HealthController($c);
});

// Set container for AppFactory
AppFactory::setContainer($container);

// Create Slim app
$app = AppFactory::create();

$app->addRoutingMiddleware();

$app->add(function ($request, $handler) use ($container) {
    $twig = $container->get('view');
    $twig->getEnvironment()->addGlobal('auth', [
        'check' => isset($_SESSION['user']),
        'user' => $_SESSION['user'] ?? null,
    ]);

    return $handler->handle($request);
});

// Add Twig middleware
$app->add(TwigMiddleware::createFromContainer($app, 'view'));

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $debugMode,
    true,
    true
);

// Custom error handler
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->forceContentType('application/json');

// Add CORS middleware for API routes
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    
    // Only add CORS headers for API routes
    if (strpos($request->getUri()->getPath(), '/api') === 0) {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
    }
    
    return $response;
});

// Load routes
require BASE_PATH . '/app/Http/routes.php';

// Run application
try {
    $app->run();
} catch (Exception $e) {
    // Log critical errors
    error_log('Application error: ' . $e->getMessage());
    
    // Show generic error in production
    if ($appEnv === 'production') {
        http_response_code(500);
        echo json_encode(['error' => 'Internal Server Error']);
    } else {
        throw $e;
    }
}