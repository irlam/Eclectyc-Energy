<?php
/**
 * eclectyc-energy/app/http/Controllers/AuthController.php
 * Handles login and logout flows
 */

namespace App\Http\Controllers;

use App\Services\AuthService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class AuthController
{
    private Twig $view;
    private AuthService $authService;

    public function __construct(Twig $view, AuthService $authService)
    {
        $this->view = $view;
        $this->authService = $authService;
    }

    public function showLoginForm(Request $request, Response $response): Response
    {
        if ($this->authService->check()) {
            return $response
                ->withHeader('Location', '/')
                ->withStatus(302);
        }

        $queryParams = $request->getQueryParams();
        $error = $queryParams['error'] ?? null;

        return $this->view->render($response, 'auth/login.twig', [
            'page_title' => 'Sign In',
            'error' => $error,
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        if ($this->authService->attempt($email, $password)) {
            $redirectUrl = $data['redirect'] ?? '/';

            return $response
                ->withHeader('Location', $redirectUrl ?: '/')
                ->withStatus(302);
        }

        $error = urlencode('Invalid credentials');
        return $response
            ->withHeader('Location', '/login?error=' . $error)
            ->withStatus(302);
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
}
