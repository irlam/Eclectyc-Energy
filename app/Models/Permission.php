<?php
/**
 * eclectyc-energy/app/Models/Permission.php
 * Permission model for access control
 * Last updated: 2025-11-09
 */

namespace App\Models;

use PDO;

class Permission extends BaseModel
{
    protected static string $table = 'permissions';
    protected static string $primaryKey = 'id';

    /**
     * Get all permissions grouped by category
     *
     * @return array
     */
    public static function getAllGrouped(): array
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return [];
        }

        $stmt = $db->query('
            SELECT *
            FROM permissions
            WHERE is_active = 1
            ORDER BY category, display_name
        ');

        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group by category
        $grouped = [];
        foreach ($permissions as $permission) {
            $category = $permission['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $permission;
        }

        return $grouped;
    }

    /**
     * Get all active permissions
     *
     * @return array
     */
    public static function getAllActive(): array
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return [];
        }

        $stmt = $db->query('
            SELECT *
            FROM permissions
            WHERE is_active = 1
            ORDER BY category, display_name
        ');

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get permission by name
     *
     * @param string $name
     * @return Permission|null
     */
    public static function findByName(string $name): ?self
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return null;
        }

        $stmt = $db->prepare('
            SELECT *
            FROM permissions
            WHERE name = :name
            LIMIT 1
        ');

        $stmt->execute(['name' => $name]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? new static($data) : null;
    }

    /**
     * Get permissions by category
     *
     * @param string $category
     * @return array
     */
    public static function getByCategory(string $category): array
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return [];
        }

        $stmt = $db->prepare('
            SELECT *
            FROM permissions
            WHERE category = :category AND is_active = 1
            ORDER BY display_name
        ');

        $stmt->execute(['category' => $category]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get all unique categories
     *
     * @return array
     */
    public static function getCategories(): array
    {
        $db = \App\Config\Database::getConnection();
        if (!$db) {
            return [];
        }

        $stmt = $db->query('
            SELECT DISTINCT category
            FROM permissions
            WHERE is_active = 1
            ORDER BY category
        ');

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}
