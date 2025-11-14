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
        $redirect = isset($queryParams['redirect'])
            ? $this->sanitizeRedirect($queryParams['redirect'])
            : '';
        $email = $queryParams['email'] ?? '';

        return $this->view->render($response, 'auth/login.twig', [
            'page_title' => 'Sign In',
            'error' => $error,
            'redirect' => $redirect,
            'old' => ['email' => $email],
        ]);
    }

    public function login(Request $request, Response $response): Response
    {
        $data = (array) $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';

        $redirectUrl = $this->sanitizeRedirect($data['redirect'] ?? '/');

        if ($this->authService->attempt($email, $password)) {
            return $response
                ->withHeader('Location', $redirectUrl ?: '/')
                ->withStatus(302);
        }

        $query = ['error' => 'Invalid credentials'];

        if ($email !== '') {
            $query['email'] = $email;
        }

        if (!empty($data['redirect'])) {
            $query['redirect'] = $this->sanitizeRedirect($data['redirect']);
        }

        $location = '/login?' . http_build_query($query);

        return $response
            ->withHeader('Location', $location)
            ->withStatus(302);
    }

    private function sanitizeRedirect(?string $path): string
    {
        if (!$path) {
            return '/';
        }

        if (str_starts_with($path, 'http') || str_starts_with($path, '//')) {
            return '/';
        }

        return $path[0] === '/' ? $path : '/' . ltrim($path, '/');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->authService->logout();

        return $response
            ->withHeader('Location', '/login')
            ->withStatus(302);
    }
}
