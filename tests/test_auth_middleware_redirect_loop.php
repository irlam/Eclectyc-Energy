<?php
/**
 * Test script for AuthMiddleware redirect loop fix
 * 
 * This test validates that the AuthMiddleware doesn't create redirect loops
 * when the /login endpoint is accessed without authentication.
 * 
 * Run: php tests/test_auth_middleware_redirect_loop.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

// Test counter
$testsPassed = 0;
$testsFailed = 0;
$totalTests = 0;

function testMiddleware(string $testName, string $path, string $queryString, bool $expectedRedirect, ?string $expectedLocation = null): void
{
    global $testsPassed, $testsFailed, $totalTests;
    $totalTests++;
    
    echo "Testing: {$testName}\n";
    echo "  Path: {$path}\n";
    echo "  Query: {$queryString}\n";
    
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
    
    // Create a mock request
    $requestFactory = new ServerRequestFactory();
    $uri = $path . ($queryString ? '?' . $queryString : '');
    $request = $requestFactory->createServerRequest('GET', 'http://example.com' . $uri);
    
    // Create a mock handler that returns a simple response
    $handler = new class implements RequestHandlerInterface {
        public function handle(Request $request): \Psr\Http\Message\ResponseInterface {
            $response = new Response();
            $response->getBody()->write('Handler executed');
            return $response;
        }
    };
    
    // Process the request through middleware
    $response = $middleware->process($request, $handler);
    
    $statusCode = $response->getStatusCode();
    $location = $response->getHeaderLine('Location');
    
    // Check if redirect happened as expected
    if ($expectedRedirect) {
        if ($statusCode === 302 && $location !== '') {
            if ($expectedLocation === null || $location === $expectedLocation) {
                echo "  ✓ PASSED: Got redirect to '{$location}'\n";
                $testsPassed++;
            } else {
                echo "  ✗ FAILED: Expected redirect to '{$expectedLocation}', got '{$location}'\n";
                $testsFailed++;
            }
        } else {
            echo "  ✗ FAILED: Expected redirect but got status {$statusCode}, location '{$location}'\n";
            $testsFailed++;
        }
    } else {
        if ($statusCode !== 302 && $location === '') {
            echo "  ✓ PASSED: Request passed through to handler (no redirect loop)\n";
            $testsPassed++;
        } else {
            echo "  ✗ FAILED: Expected no redirect but got status {$statusCode}, location '{$location}'\n";
            $testsFailed++;
        }
    }
    echo "\n";
}

echo "=== AuthMiddleware Redirect Loop Prevention Tests ===\n\n";

// Test 1: Protected page should redirect to login
testMiddleware(
    'Protected dashboard page redirects to login',
    '/',
    '',
    true,
    '/login?redirect=%2F'
);

// Test 2: Login page should NOT redirect (prevent loop)
testMiddleware(
    'Login page does not redirect (prevents loop)',
    '/login',
    '',
    false
);

// Test 3: Login page with redirect parameter should NOT redirect (prevent loop)
testMiddleware(
    'Login page with redirect param does not create loop',
    '/login',
    'redirect=%2F',
    false
);

// Test 4: Login page with error and redirect should NOT redirect
testMiddleware(
    'Login page with error and redirect does not create loop',
    '/login',
    'error=Invalid+credentials&redirect=%2F',
    false
);

// Test 5: Other protected pages should still redirect properly
testMiddleware(
    'Protected admin page redirects to login',
    '/admin/users',
    '',
    true,
    '/login?redirect=%2Fadmin%2Fusers'
);

// Test 6: Protected page with query params redirects correctly
testMiddleware(
    'Protected page with query params redirects correctly',
    '/dashboard',
    'tab=overview',
    true,
    '/login?redirect=%2Fdashboard%3Ftab%3Doverview'
);

// Test 7: Logout page (which has auth middleware) should redirect
testMiddleware(
    'Logout page redirects to login',
    '/logout',
    '',
    true,
    '/login?redirect=%2Flogout'
);

// Summary
echo "=== Test Summary ===\n";
echo "Total Tests: {$totalTests}\n";
echo "Passed: {$testsPassed}\n";
echo "Failed: {$testsFailed}\n\n";

if ($testsFailed === 0) {
    echo "✓ All tests passed!\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
