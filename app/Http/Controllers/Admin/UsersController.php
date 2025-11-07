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
    private ?\PDO $pdo;

    public function __construct(Twig $view, ?\PDO $pdo)
    {
        $this->view = $view;
        $this->pdo = $pdo;
    }

    public function index(Request $request, Response $response): Response
    {
        $users = [];
        if ($this->pdo) {
            try {
                $stmt = $this->pdo->query('SELECT id, email, name, role, is_active, last_login, created_at FROM users ORDER BY created_at DESC');
                $users = $stmt->fetchAll();
            } catch (\Throwable $e) {
                // If the table is missing or query fails show empty list
            }
        }

        $flash = $_SESSION['user_flash'] ?? null;
        unset($_SESSION['user_flash']);

        return $this->view->render($response, 'admin/users.twig', [
            'page_title' => 'User Management',
            'users' => $users,
            'flash' => $flash,
        ]);
    }

    /**
     * Show create user form
     */
    public function create(Request $request, Response $response): Response
    {
        return $this->view->render($response, 'admin/users_create.twig', [
            'page_title' => 'Create User',
        ]);
    }

    /**
     * Store new user
     */
    public function store(Request $request, Response $response): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/users');
        }

        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $name = trim($data['name'] ?? '');
        $role = $data['role'] ?? 'viewer';
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $isActive = isset($data['is_active']);

        // Validation
        $errors = [];
        
        if (empty($email)) {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address';
        }
        
        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
        
        if (!in_array($role, ['admin', 'manager', 'viewer'])) {
            $errors[] = 'Invalid role selected';
        }

        // Check if email already exists
        if (empty($errors)) {
            try {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetchColumn() > 0) {
                    $errors[] = 'Email address is already in use';
                }
            } catch (\Throwable $e) {
                $errors[] = 'Unable to check email availability';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(', ', $errors));
            return $this->redirect($response, '/admin/users/create');
        }

        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare('
                INSERT INTO users (email, password_hash, name, role, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ');
            
            $stmt->execute([
                $email,
                $passwordHash,
                $name,
                $role,
                $isActive ? 1 : 0
            ]);

            $this->setFlash('success', "User '{$name}' has been created successfully.");
            return $this->redirect($response, '/admin/users');

        } catch (\Throwable $e) {
            $this->setFlash('error', 'Failed to create user. Please try again.');
            return $this->redirect($response, '/admin/users/create');
        }
    }

    /**
     * Set flash message
     */
    private function setFlash(string $type, string $message): void
    {
        $_SESSION['user_flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Redirect response
     */
    private function redirect(Response $response, string $url): Response
    {
        return $response
            ->withHeader('Location', $url)
            ->withStatus(302);
    }
}
