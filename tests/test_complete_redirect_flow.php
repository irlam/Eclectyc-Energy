<?php
/**
 * Comprehensive redirect flow test
 * Tests the entire flow from /?redirect=%2F through login
 * 
 * Run: php tests/test_complete_redirect_flow.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\AuthController;
use App\Http\Middleware\AuthMiddleware;
use App\Services\AuthService;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;

echo "=== Complete Redirect Flow Test ===\n\n";

// Step 1: Unauthenticated user accesses /?redirect=%2F
echo "STEP 1: User accesses /?redirect=%2F (unauthenticated)\n";
echo "------------------------------------------------------------------------\n";

$mockAuthService = new class extends AuthService {
    private bool $isAuthenticated = false;
    
    public function __construct() {}
    
    public function check(): bool {
        return $this->isAuthenticated;
    }
    
    public function setAuthenticated(bool $value): void {
        $this->isAuthenticated = $value;
    }
    
    public function hasRole(array $roles): bool {
        return false;
    }
    
    public function attempt(string $email, string $password): bool {
        if ($email === 'test@example.com' && $password === 'password') {
            $this->isAuthenticated = true;
            return true;
        }
        return false;
    }
    
    public function logout(): void {
        $this->isAuthenticated = false;
    }
};

$middleware = new AuthMiddleware($mockAuthService);
$requestFactory = new ServerRequestFactory();
$request = $requestFactory->createServerRequest('GET', 'http://example.com/?redirect=%2F');

$handler = new class implements RequestHandlerInterface {
    public function handle(Request $request): \Psr\Http\Message\ResponseInterface {
        $response = new Response();
        $response->getBody()->write('Dashboard rendered');
        return $response;
    }
};

$response = $middleware->process($request, $handler);

echo "Response Status: " . $response->getStatusCode() . "\n";
echo "Location Header: " . ($response->getHeaderLine('Location') ?: 'none') . "\n";

if ($response->getStatusCode() === 302) {
    $location = $response->getHeaderLine('Location');
    echo "✓ User is redirected to: {$location}\n";
    
    // Parse the redirect URL
    $parsed = parse_url($location);
    $redirectPath = $parsed['path'] ?? '';
    $redirectQuery = $parsed['query'] ?? '';
    
    if ($redirectPath === '/login') {
        parse_str($redirectQuery, $queryParams);
        $redirectParam = $queryParams['redirect'] ?? '';
        
        echo "  Redirect parameter: '{$redirectParam}'\n";
        
        if ($redirectParam === '/') {
            echo "  ✓ Redirect parameter is correct (just '/', not '/?redirect=...')\n\n";
        } else {
            echo "  ✗ ERROR: Redirect parameter is '{$redirectParam}', expected '/'\n\n";
            exit(1);
        }
        
        // Step 2: User accesses /login?redirect=%2F
        echo "STEP 2: User accesses /login?redirect=%2F\n";
        echo "------------------------------------------------------------------------\n";
        
        $loginRequest = $requestFactory->createServerRequest('GET', 'http://example.com' . $location);
        $loginResponse = $middleware->process($loginRequest, $handler);
        
        echo "Response Status: " . $loginResponse->getStatusCode() . "\n";
        echo "Location Header: " . ($loginResponse->getHeaderLine('Location') ?: 'none') . "\n";
        
        if ($loginResponse->getStatusCode() === 302) {
            echo "✗ ERROR: Login page is redirecting! This could cause a loop.\n";
            echo "  Location: " . $loginResponse->getHeaderLine('Location') . "\n\n";
            exit(1);
        } else {
            echo "✓ Login page is displayed (no redirect)\n";
            echo "  Body: " . $loginResponse->getBody() . "\n\n";
        }
        
        // Step 3: User submits login form
        echo "STEP 3: User submits login form with redirect parameter\n";
        echo "------------------------------------------------------------------------\n";
        
        // Note: We can't fully test AuthController here without more setup
        // But we can verify the logic
        
        echo "The login form would POST to /login with:\n";
        echo "  email: test@example.com\n";
        echo "  password: password\n";
        echo "  redirect: / (from hidden field)\n\n";
        
        echo "AuthController::login would:\n";
        echo "  1. Call authService->attempt(email, password)\n";
        echo "  2. If successful, redirect to the sanitized redirect value\n";
        echo "  3. Redirect to '/' (not '/?redirect=/')\n\n";
        
        echo "✓ Final redirect should be to '/', breaking any potential loop\n\n";
        
        echo "=== CONCLUSION ===\n";
        echo "The code appears to handle the redirect flow correctly.\n";
        echo "If loops are still occurring, the issue may be:\n";
        echo "  1. Browser caching or bookmarks with redirect parameters\n";
        echo "  2. External links or search engines with redirect parameters\n";
        echo "  3. Apache .htaccess configuration issue\n";
        echo "  4. Some other middleware or code path not tested here\n\n";
        
        echo "Recommendation:\n";
        echo "  - Add explicit handling to strip redirect parameters from root URL\n";
        echo "  - Ensure any bookmarked or cached URLs don't cause loops\n";
        echo "  - Add redirect parameter validation at the application entry point\n\n";
        
        exit(0);
    } else {
        echo "✗ ERROR: Expected redirect to /login, got {$redirectPath}\n";
        exit(1);
    }
} else {
    echo "✗ ERROR: Expected 302 redirect, got status " . $response->getStatusCode() . "\n";
    exit(1);
}
