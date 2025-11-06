<?php
/**
 * eclectyc-energy/app/http/Controllers/Admin/UsersController.php
 * Basic user management views
 */

namespace App\Http\Controllers\Admin;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class UsersController
{
    private Twig $view;
    private \PDO $pdo;

    public function __construct(Twig $view, \PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $users = [];
        try {
            $stmt = $this->pdo->query('SELECT id, email, name, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC');
            $users = $stmt->fetchAll();
        } catch (\Throwable $e) {
            // If the table is missing or query fails show empty list
        }

        return $this->view->render($response, 'admin/users.twig', [
            'page_title' => 'User Management',
            'users' => $users,
        ]);
    }
}
