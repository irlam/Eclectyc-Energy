<?php
/**
 * eclectyc-energy/app/Services/AuthService.php
 * Lightweight authentication helper
 */

namespace App\Services;

class AuthService
{
    private ?\PDO $pdo;

    public function __construct(?\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function attempt(string $email, string $password): bool
    {
        if (!$this->pdo) {
            return false;
        }

        if ($email === '' || $password === '') {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT id, email, password_hash, name, role, is_active FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || !(bool) $user['is_active']) {
            return false;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
        ];

        $this->logLogin((int) $user['id']);

        return true;
    }

    public function logout(): void
    {
        unset($_SESSION['user']);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function hasRole(array $roles): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return in_array($user['role'], $roles, true);
    }

    private function logLogin(int $userId): void
    {
        if (!$this->pdo) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            $stmt->execute(['id' => $userId]);
        } catch (\Throwable $e) {
            // Ignore logging errors
        }
    }
}
