<?php
/**
 * eclectyc-energy/app/Http/Middleware/RedirectParameterCleanupMiddleware.php
 * Prevents redirect parameter pollution in URLs
 * 
 * This middleware strips 'redirect' query parameters from URLs where they don't belong,
 * preventing bookmark/cache-related redirect loops and cleaning up user-facing URLs.
 * 
 * Created: 2025-11-15
 */

namespace App\Http\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as Psr7Response;

class RedirectParameterCleanupMiddleware implements MiddlewareInterface
{
    /**
     * Paths that should never have a 'redirect' parameter
     * These will be cleaned with a 301 permanent redirect
     */
    private array $cleanPaths = [
        '/',
        '/logout',
        '/dashboard',
    ];

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $uri = $request->getUri();
        $path = $uri->getPath();
        $queryString = $uri->getQuery();

        // Check if this path should be cleaned and has query parameters
        if (in_array($path, $this->cleanPaths, true) && $queryString !== '') {
            parse_str($queryString, $queryParams);
            
            // If there's a 'redirect' parameter, remove it
            if (isset($queryParams['redirect'])) {
                unset($queryParams['redirect']);
                
                // Build the cleaned URL
                $cleanPath = $path;
                if (!empty($queryParams)) {
                    $cleanPath .= '?' . http_build_query($queryParams);
                }
                
                // Redirect to the cleaned URL (301 permanent redirect)
                return (new Psr7Response())
                    ->withHeader('Location', $cleanPath)
                    ->withStatus(301);
            }
        }

        // No cleanup needed, continue processing
        return $handler->handle($request);
    }
}
