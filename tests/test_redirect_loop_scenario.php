<?php
/**
 * Test script for specific redirect loop scenario from problem statement
 * 
 * This test validates the exact scenario reported:
 * /?redirect=%2F causes redirect loops
 * 
 * Run: php tests/test_redirect_loop_scenario.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

echo "=== Testing Specific Redirect Loop Scenario ===\n\n";

// Create a mock AuthService that always returns false for check()
$mockAuthService = new class extends AuthService {
    public function __construct() {
        // Don't call parent constructor to avoid DB dependency
    }
    
    public function check(): bool {
        return false; // Always not authenticated
    }
    
    public function hasRole(array $roles): bool {
        return false;
    }
};

// Create middleware instance
$middleware = new AuthMiddleware($mockAuthService);

// Create a mock request for /?redirect=%2F
$requestFactory = new ServerRequestFactory();
$request = $requestFactory->createServerRequest('GET', 'http://example.com/?redirect=%2F');

// Create a mock handler that returns a simple response
$handler = new class implements RequestHandlerInterface {
    public function handle(Request $request): \Psr\Http\Message\ResponseInterface {
        $response = new Response();
        $response->getBody()->write('Handler executed - login page should show');
        return $response;
    }
};

echo "Step 1: User accesses /?redirect=%2F\n";
echo "  (this represents /?redirect=/ where redirect=/ is URL encoded as %2F)\n\n";

// Process the request through middleware
$response = $middleware->process($request, $handler);

$statusCode = $response->getStatusCode();
$location = $response->getHeaderLine('Location');

echo "Response from AuthMiddleware:\n";
echo "  Status: {$statusCode}\n";
echo "  Location: {$location}\n\n";

if ($statusCode === 302 && !empty($location)) {
    echo "Step 2: User is redirected to: {$location}\n\n";
    
    // Parse the redirect location
    $parsedUrl = parse_url($location);
    $path = $parsedUrl['path'] ?? '';
    $query = $parsedUrl['query'] ?? '';
    
    echo "  Redirect path: {$path}\n";
    echo "  Redirect query: {$query}\n\n";
    
    // Check if this is redirecting to /login
    if ($path === '/login') {
        parse_str($query, $queryParams);
        $redirectParam = $queryParams['redirect'] ?? '';
        
        echo "  Redirect parameter value: {$redirectParam}\n\n";
        
        // The redirect parameter should be just '/' not '/?redirect=...'
        if ($redirectParam === '/') {
            echo "✓ PASS: Redirect parameter is correctly set to '/'\n";
            echo "  This should NOT create a loop.\n\n";
            
            echo "Step 3: When user accesses /login?redirect=%2F\n";
            echo "  The login page should display.\n";
            echo "  After successful login, user should be redirected to '/'\n";
            echo "  NOT back to '/?redirect=/'\n\n";
            
            echo "✓ All checks passed! No redirect loop should occur.\n";
            exit(0);
        } elseif (strpos($redirectParam, 'redirect=') !== false) {
            echo "✗ FAIL: Redirect parameter contains nested 'redirect='!\n";
            echo "  Value: {$redirectParam}\n";
            echo "  This WILL create a redirect loop!\n\n";
            
            echo "The issue is that the middleware is not properly stripping\n";
            echo "the 'redirect' parameter from the original query string.\n";
            exit(1);
        } else {
            echo "⚠ WARNING: Unexpected redirect parameter: {$redirectParam}\n";
            exit(1);
        }
    } else {
        echo "✗ FAIL: Expected redirect to /login, got {$path}\n";
        exit(1);
    }
} else {
    echo "✗ FAIL: Expected 302 redirect, got status {$statusCode}\n";
    exit(1);
}
