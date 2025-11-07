<?php
/**
 * eclectyc-energy/app/http/Controllers/Admin/UsersController.php
 * Basic user management views
 */

namespace App\Http\Controllers\Admin;

use PDO;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;

class UsersController
{
    private Twig $view;
    private ?PDO $pdo;

    public function __construct(Twig $view, ?PDO $pdo)
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
            } catch (PDOException $e) {
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
        $flash = $_SESSION['user_flash'] ?? null;
        unset($_SESSION['user_flash']);

        $formData = $this->pullFormData();

        return $this->view->render($response, 'admin/users_create.twig', [
            'page_title' => 'Create User',
            'flash' => $flash,
            'form_data' => $formData,
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

        $data = $request->getParsedBody() ?? [];
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
            } catch (PDOException $e) {
                $errors[] = 'Unable to check email availability';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(', ', $errors));
            $this->rememberFormData($data);
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

        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to create user. Please try again.');
            $this->rememberFormData($data);
            return $this->redirect($response, '/admin/users/create');
        }
    }

    /**
     * Show edit user form
     */
    public function edit(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/users');
        }

        $userId = (int) ($args['id'] ?? 0);

        try {
            $stmt = $this->pdo->prepare('SELECT id, email, name, role, is_active FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $user = false;
        }

        if (!$user) {
            $this->setFlash('error', 'User not found.');
            return $this->redirect($response, '/admin/users');
        }

        $flash = $_SESSION['user_flash'] ?? null;
        unset($_SESSION['user_flash']);

        $formData = $this->pullFormData($user);

        return $this->view->render($response, 'admin/users_edit.twig', [
            'page_title' => 'Edit User',
            'user' => $user,
            'flash' => $flash,
            'form_data' => $formData,
        ]);
    }

    /**
     * Update an existing user
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/users');
        }

        $userId = (int) ($args['id'] ?? 0);
        $data = $request->getParsedBody() ?? [];

        $email = trim($data['email'] ?? '');
        $name = trim($data['name'] ?? '');
        $role = $data['role'] ?? 'viewer';
        $password = $data['password'] ?? '';
        $confirmPassword = $data['confirm_password'] ?? '';
        $isActive = isset($data['is_active']);

        $errors = [];

        if ($email === '') {
            $errors[] = 'Email is required';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address';
        }

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if (!in_array($role, ['admin', 'manager', 'viewer'], true)) {
            $errors[] = 'Invalid role selected';
        }

        if ($password !== '') {
            if (strlen($password) < 8) {
                $errors[] = 'Password must be at least 8 characters long';
            }

            if ($password !== $confirmPassword) {
                $errors[] = 'Passwords do not match';
            }
        }

        if (empty($errors)) {
            try {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id != :id');
                $stmt->execute([
                    'email' => $email,
                    'id' => $userId,
                ]);

                if ((int) $stmt->fetchColumn() > 0) {
                    $errors[] = 'Email address is already in use';
                }
            } catch (PDOException $e) {
                $errors[] = 'Unable to check email availability';
            }
        }

        if (!empty($errors)) {
            $this->setFlash('error', implode(', ', $errors));
            $this->rememberFormData($data);
            return $this->redirect($response, "/admin/users/{$userId}/edit");
        }

        $fields = [
            'email' => $email,
            'name' => $name,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0,
        ];

        if ($password !== '') {
            $fields['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        try {
            $setClauses = [];
            foreach ($fields as $column => $_) {
                $setClauses[] = sprintf('%s = :%s', $column, $column);
            }
            $setClauses[] = 'updated_at = NOW()';

            $sql = 'UPDATE users SET ' . implode(', ', $setClauses) . ' WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);

            foreach ($fields as $column => $value) {
                $stmt->bindValue(':' . $column, $value);
            }
            $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

            $stmt->execute();

            $this->setFlash('success', 'User updated successfully.');
            return $this->redirect($response, '/admin/users');
        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to update user. Please try again.');
            $this->rememberFormData($data);
            return $this->redirect($response, "/admin/users/{$userId}/edit");
        }
    }

    /**
     * Delete a user account
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        if (!$this->pdo) {
            $this->setFlash('error', 'Database connection unavailable.');
            return $this->redirect($response, '/admin/users');
        }

        $userId = (int) ($args['id'] ?? 0);
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($userId === $currentUserId) {
            $this->setFlash('error', 'You cannot delete your own account while logged in.');
            return $this->redirect($response, '/admin/users');
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmt->execute(['id' => $userId]);

            if ($stmt->rowCount() > 0) {
                $this->setFlash('success', 'User deleted successfully.');
            } else {
                $this->setFlash('error', 'User not found or already deleted.');
            }
        } catch (PDOException $e) {
            $this->setFlash('error', 'Failed to delete user. Please try again.');
        }

        return $this->redirect($response, '/admin/users');
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

    private function rememberFormData(array $data): void
    {
        $data['is_active'] = isset($data['is_active']) ? 'on' : 'off';
        $_SESSION['user_form_data'] = $data;
    }

    private function pullFormData(array $defaults = []): array
    {
        $data = $_SESSION['user_form_data'] ?? $defaults;
        unset($_SESSION['user_form_data']);

        if (isset($data['password'])) {
            $data['password'] = '';
        }

        if (isset($data['confirm_password'])) {
            $data['confirm_password'] = '';
        }

        return $data;
    }
}
