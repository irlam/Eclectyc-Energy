<?php
/**
 * Test what happens when an authenticated user accesses /?redirect=%2F
 * 
 * Run: php tests/test_authenticated_redirect_param.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Controllers\DashboardController;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Response;
use Slim\Views\Twig;

echo "=== Testing Authenticated User with redirect Parameter ===\n\n";

echo "Scenario: User is already logged in and accesses /?redirect=%2F\n";
echo "  (this could happen from bookmarks, search engines, or old links)\n\n";

// In this case, the AuthMiddleware won't intercept because user is authenticated
// The request will go straight to DashboardController

echo "Expected behavior:\n";
echo "  1. User should see the dashboard\n";
echo "  2. The redirect parameter should be ignored\n";
echo "  3. No redirect loop should occur\n\n";

echo "Current behavior in the code:\n";
echo "  - AuthMiddleware checks if user is authenticated\n";
echo "  - If yes, passes request to handler (DashboardController)\n";
echo "  - DashboardController shows the dashboard page\n";
echo "  - The redirect parameter in the URL is simply ignored\n\n";

echo "✓ This scenario should work correctly.\n\n";

echo "However, let's check if the root route has any special handling...\n\n";

// Looking at routes.php line 38-40:
// $app->get('/', [DashboardController::class, 'index'])
//     ->setName('dashboard')
//     ->add(AuthMiddleware::class);

echo "The root route uses DashboardController::index which should just render the dashboard.\n";
echo "There's no special redirect logic in the route itself.\n\n";

echo "So the issue must be coming from somewhere else...\n\n";

echo "Let me check if maybe the problem is with how Apache handles the query string.\n";
echo "Or perhaps there's a redirect happening BEFORE the request reaches Slim.\n\n";

exit(0);
