<?php
/**
 * Test script for RedirectParameterCleanupMiddleware
 * 
 * This test validates that unwanted redirect parameters are stripped
 * from URLs where they don't belong.
 * 
 * Run: php tests/test_redirect_cleanup_middleware.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Middleware\RedirectParameterCleanupMiddleware;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

// Test counter
$testsPassed = 0;
$testsFailed = 0;
$totalTests = 0;

function testMiddleware(string $testName, string $url, bool $expectRedirect, ?string $expectedLocation = null): void
{
    global $testsPassed, $testsFailed, $totalTests;
    $totalTests++;
    
    echo "Testing: {$testName}\n";
    echo "  URL: {$url}\n";
    
    $middleware = new RedirectParameterCleanupMiddleware();
    $requestFactory = new ServerRequestFactory();
    $request = $requestFactory->createServerRequest('GET', $url);
    
    $handler = new class implements RequestHandlerInterface {
        public function handle(Request $request): \Psr\Http\Message\ResponseInterface {
            $response = new Response();
            $response->getBody()->write('Handler executed');
            return $response;
        }
    };
    
    $response = $middleware->process($request, $handler);
    
    $statusCode = $response->getStatusCode();
    $location = $response->getHeaderLine('Location');
    
    if ($expectRedirect) {
        if ($statusCode === 301 && !empty($location)) {
            if ($expectedLocation === null || $location === $expectedLocation) {
                echo "  ✓ PASSED: Redirected to '{$location}'\n";
                $testsPassed++;
            } else {
                echo "  ✗ FAILED: Expected redirect to '{$expectedLocation}', got '{$location}'\n";
                $testsFailed++;
            }
        } else {
            echo "  ✗ FAILED: Expected 301 redirect but got status {$statusCode}, location '{$location}'\n";
            $testsFailed++;
        }
    } else {
        if ($statusCode !== 301 && empty($location)) {
            echo "  ✓ PASSED: Request passed through (no cleanup needed)\n";
            $testsPassed++;
        } else {
            echo "  ✗ FAILED: Expected no redirect but got status {$statusCode}, location '{$location}'\n";
            $testsFailed++;
        }
    }
    echo "\n";
}

echo "=== RedirectParameterCleanupMiddleware Tests ===\n\n";

// Test 1: Root URL with redirect parameter should be cleaned
testMiddleware(
    'Root URL with redirect parameter',
    'http://example.com/?redirect=%2F',
    true,
    '/'
);

// Test 2: Root URL without redirect parameter should pass through
testMiddleware(
    'Root URL without redirect parameter',
    'http://example.com/',
    false
);

// Test 3: Root URL with other query parameters (no redirect) should pass through
testMiddleware(
    'Root URL with other query parameters',
    'http://example.com/?tab=overview',
    false
);

// Test 4: Root URL with redirect and other parameters should remove only redirect
testMiddleware(
    'Root URL with redirect and other parameters',
    'http://example.com/?tab=overview&redirect=%2Fadmin',
    true,
    '/?tab=overview'
);

// Test 5: Logout URL with redirect parameter should be cleaned
testMiddleware(
    'Logout URL with redirect parameter',
    'http://example.com/logout?redirect=%2F',
    true,
    '/logout'
);

// Test 6: Dashboard URL with redirect parameter should be cleaned
testMiddleware(
    'Dashboard URL with redirect parameter',
    'http://example.com/dashboard?redirect=%2F',
    true,
    '/dashboard'
);

// Test 7: Login URL with redirect parameter should NOT be cleaned (it's valid there)
testMiddleware(
    'Login URL with redirect parameter (should pass through)',
    'http://example.com/login?redirect=%2Fdashboard',
    false
);

// Test 8: Admin URL with redirect parameter should NOT be cleaned (not in clean list)
testMiddleware(
    'Admin URL with redirect parameter (should pass through)',
    'http://example.com/admin/users?redirect=%2F',
    false
);

// Test 9: Deeply nested redirect should be cleaned
testMiddleware(
    'Root with nested redirect parameter',
    'http://example.com/?redirect=%2F%3Fredirect%3D%252F',
    true,
    '/'
);

// Test 10: Root with URL-encoded redirect should be cleaned
testMiddleware(
    'Root with URL-encoded redirect',
    'http://example.com/?redirect=%2Fadmin%2Fusers',
    true,
    '/'
);

// Summary
echo "=== Test Summary ===\n";
echo "Total Tests: {$totalTests}\n";
echo "Passed: {$testsPassed}\n";
echo "Failed: {$testsFailed}\n\n";

if ($testsFailed === 0) {
    echo "✓ All tests passed!\n";
    echo "\nThis middleware will:\n";
    echo "  1. Prevent redirect loops from bookmarked URLs like /?redirect=%2F\n";
    echo "  2. Clean up URLs that shouldn't have redirect parameters\n";
    echo "  3. Preserve redirect parameters where they're valid (like /login)\n";
    echo "  4. Use 301 (permanent) redirects to update bookmarks and caches\n";
    exit(0);
} else {
    echo "✗ Some tests failed.\n";
    exit(1);
}
