<?php
/**
 * eclectyc-energy/app/Models/User.php
 * User model with permissions support
 * Last updated: 2025-11-09
 */

namespace App\Models;

use PDO;

class User extends BaseModel
{
    protected static string $table = 'users';
    protected static string $primaryKey = 'id';

    /**
     * Get user's permissions
     *
     * @return array Array of permission names
     */
    public function getPermissions(): array
    {
        if (!isset($this->attributes['id'])) {
            return [];
        }

        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return [];
        }

        $stmt = $db->prepare('
            SELECT p.name
            FROM permissions p
            INNER JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = :user_id AND p.is_active = 1
        ');

        $stmt->execute(['user_id' => $this->attributes['id']]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Check if user has a specific permission
     *
     * @param string $permissionName Permission name (e.g., 'import.upload')
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        // Admin users have all permissions
        if ($this->attributes['role'] === 'admin') {
            return true;
        }

        $permissions = $this->getPermissions();
        return in_array($permissionName, $permissions, true);
    }

    /**
     * Check if user has any of the given permissions
     *
     * @param array $permissionNames Array of permission names
     * @return bool
     */
    public function hasAnyPermission(array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     *
     * @param array $permissionNames Array of permission names
     * @return bool
     */
    public function hasAllPermissions(array $permissionNames): bool
    {
        foreach ($permissionNames as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Grant a permission to the user
     *
     * @param int $permissionId Permission ID
     * @param int|null $grantedBy User ID who granted the permission
     * @return bool
     */
    public function grantPermission(int $permissionId, ?int $grantedBy = null): bool
    {
        if (!isset($this->attributes['id'])) {
            return false;
        }

        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare('
                INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by)
                VALUES (:user_id, :permission_id, :granted_by)
            ');

            return $stmt->execute([
                'user_id' => $this->attributes['id'],
                'permission_id' => $permissionId,
                'granted_by' => $grantedBy,
            ]);
        } catch (\PDOException $e) {
            error_log('Failed to grant permission: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke a permission from the user
     *
     * @param int $permissionId Permission ID
     * @return bool
     */
    public function revokePermission(int $permissionId): bool
    {
        if (!isset($this->attributes['id'])) {
            return false;
        }

        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return false;
        }

        try {
            $stmt = $db->prepare('
                DELETE FROM user_permissions
                WHERE user_id = :user_id AND permission_id = :permission_id
            ');

            return $stmt->execute([
                'user_id' => $this->attributes['id'],
                'permission_id' => $permissionId,
            ]);
        } catch (\PDOException $e) {
            error_log('Failed to revoke permission: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync user permissions (grant/revoke to match provided list)
     *
     * @param array $permissionIds Array of permission IDs
     * @param int|null $grantedBy User ID who is updating permissions
     * @return bool
     */
    public function syncPermissions(array $permissionIds, ?int $grantedBy = null): bool
    {
        if (!isset($this->attributes['id'])) {
            return false;
        }

        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return false;
        }

        try {
            $db->beginTransaction();

            // Delete all existing permissions
            $stmt = $db->prepare('DELETE FROM user_permissions WHERE user_id = :user_id');
            $stmt->execute(['user_id' => $this->attributes['id']]);

            // Grant new permissions
            if (!empty($permissionIds)) {
                $stmt = $db->prepare('
                    INSERT INTO user_permissions (user_id, permission_id, granted_by)
                    VALUES (:user_id, :permission_id, :granted_by)
                ');

                foreach ($permissionIds as $permissionId) {
                    $stmt->execute([
                        'user_id' => $this->attributes['id'],
                        'permission_id' => $permissionId,
                        'granted_by' => $grantedBy,
                    ]);
                }
            }

            $db->commit();
            return true;
        } catch (\PDOException $e) {
            $db->rollBack();
            error_log('Failed to sync permissions: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all permission IDs for this user
     *
     * @return array
     */
    public function getPermissionIds(): array
    {
        if (!isset($this->attributes['id'])) {
            return [];
        }

        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return [];
        }

        $stmt = $db->prepare('
            SELECT permission_id
            FROM user_permissions
            WHERE user_id = :user_id
        ');

        $stmt->execute(['user_id' => $this->attributes['id']]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
