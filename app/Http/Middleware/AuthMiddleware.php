<?php
/**
 * eclectyc-energy/app/http/Middleware/AuthMiddleware.php
 * Ensures routes are accessible only to authenticated users
 */

namespace App\Http\Middleware;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response as Psr7Response;

class AuthMiddleware implements MiddlewareInterface
{
    private AuthService $authService;
    private ?array $roles;

    public function __construct(AuthService $authService, ?array $roles = null)
    {
        $this->authService = $authService;
        $this->roles = $roles;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!$this->authService->check()) {
            $uri = $request->getUri();
            $redirectTarget = $uri->getPath();
            $queryString = $uri->getQuery();

            if ($queryString !== '') {
                $redirectTarget .= '?' . $queryString;
            }

            $redirect = '/login?redirect=' . urlencode($redirectTarget);
            return (new Psr7Response())
                ->withHeader('Location', $redirect)
                ->withStatus(302);
        }

        if ($this->roles !== null && !$this->authService->hasRole($this->roles)) {
            $response = new Psr7Response();
            $response->getBody()->write('Forbidden');
            return $response->withStatus(403);
        }

        return $handler->handle($request);
    }
}
