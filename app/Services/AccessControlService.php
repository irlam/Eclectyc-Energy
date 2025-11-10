<?php
/**
 * eclectyc-energy/app/Services/AccessControlService.php
 * Hierarchical access control service for user-to-entity relationships
 * Last updated: 2025-11-10
 */

namespace App\Services;

use App\Models\User;
use PDO;

class AccessControlService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Check if user has access to a specific site
     * Considers hierarchical access through company, region, or direct site access
     *
     * @param int $userId
     * @param int $siteId
     * @return bool
     */
    public function canAccessSite(int $userId, int $siteId): bool
    {
        // Admin users have access to everything
        $user = User::find($userId);
        if ($user && $user->role === 'admin') {
            return true;
        }

        // Check direct site access
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as count
            FROM user_site_access
            WHERE user_id = :user_id AND site_id = :site_id
        ');
        $stmt->execute(['user_id' => $userId, 'site_id' => $siteId]);
        if ((int)$stmt->fetch()['count'] > 0) {
            return true;
        }

        // Check region access
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as count
            FROM user_region_access ura
            INNER JOIN sites s ON s.region_id = ura.region_id
            WHERE ura.user_id = :user_id AND s.id = :site_id
        ');
        $stmt->execute(['user_id' => $userId, 'site_id' => $siteId]);
        if ((int)$stmt->fetch()['count'] > 0) {
            return true;
        }

        // Check company access
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as count
            FROM user_company_access uca
            INNER JOIN sites s ON s.company_id = uca.company_id
            WHERE uca.user_id = :user_id AND s.id = :site_id
        ');
        $stmt->execute(['user_id' => $userId, 'site_id' => $siteId]);
        if ((int)$stmt->fetch()['count'] > 0) {
            return true;
        }

        return false;
    }

    /**
     * Check if user has access to a specific company
     *
     * @param int $userId
     * @param int $companyId
     * @return bool
     */
    public function canAccessCompany(int $userId, int $companyId): bool
    {
        $user = User::find($userId);
        if ($user && $user->role === 'admin') {
            return true;
        }

        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as count
            FROM user_company_access
            WHERE user_id = :user_id AND company_id = :company_id
        ');
        $stmt->execute(['user_id' => $userId, 'company_id' => $companyId]);
        return (int)$stmt->fetch()['count'] > 0;
    }

    /**
     * Check if user has access to a specific region
     *
     * @param int $userId
     * @param int $regionId
     * @return bool
     */
    public function canAccessRegion(int $userId, int $regionId): bool
    {
        $user = User::find($userId);
        if ($user && $user->role === 'admin') {
            return true;
        }

        // Check direct region access
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as count
            FROM user_region_access
            WHERE user_id = :user_id AND region_id = :region_id
        ');
        $stmt->execute(['user_id' => $userId, 'region_id' => $regionId]);
        if ((int)$stmt->fetch()['count'] > 0) {
            return true;
        }

        // Check company access (if region belongs to a company user has access to)
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) as count
            FROM user_company_access uca
            INNER JOIN sites s ON s.company_id = uca.company_id
            WHERE uca.user_id = :user_id AND s.region_id = :region_id
            LIMIT 1
        ');
        $stmt->execute(['user_id' => $userId, 'region_id' => $regionId]);
        return (int)$stmt->fetch()['count'] > 0;
    }

    /**
     * Get all site IDs that a user has access to
     *
     * @param int $userId
     * @return array Array of site IDs
     */
    public function getAccessibleSiteIds(int $userId): array
    {
        $user = User::find($userId);
        if ($user && $user->role === 'admin') {
            // Admin has access to all sites
            $stmt = $this->pdo->query('SELECT id FROM sites');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Combine all site access from different levels using UNION for better performance
        $stmt = $this->pdo->prepare('
            SELECT DISTINCT s.id
            FROM sites s
            INNER JOIN (
                -- Direct site access
                SELECT site_id FROM user_site_access WHERE user_id = :user_id
                UNION ALL
                -- Region access
                SELECT s2.id FROM sites s2
                INNER JOIN user_region_access ura ON s2.region_id = ura.region_id
                WHERE ura.user_id = :user_id2
                UNION ALL
                -- Company access
                SELECT s3.id FROM sites s3
                INNER JOIN user_company_access uca ON s3.company_id = uca.company_id
                WHERE uca.user_id = :user_id3
            ) access ON s.id = access.site_id
        ');
        $stmt->execute([
            'user_id' => $userId,
            'user_id2' => $userId,
            'user_id3' => $userId,
        ]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all company IDs that a user has access to
     *
     * @param int $userId
     * @return array Array of company IDs
     */
    public function getAccessibleCompanyIds(int $userId): array
    {
        $user = User::find($userId);
        if ($user && $user->role === 'admin') {
            $stmt = $this->pdo->query('SELECT id FROM companies');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $stmt = $this->pdo->prepare('
            SELECT company_id FROM user_company_access WHERE user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Get all region IDs that a user has access to
     *
     * @param int $userId
     * @return array Array of region IDs
     */
    public function getAccessibleRegionIds(int $userId): array
    {
        $user = User::find($userId);
        if ($user && $user->role === 'admin') {
            $stmt = $this->pdo->query('SELECT id FROM regions');
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Get regions through direct access or company access
        $stmt = $this->pdo->prepare('
            SELECT DISTINCT r.id
            FROM regions r
            WHERE r.id IN (
                -- Direct region access
                SELECT region_id FROM user_region_access WHERE user_id = :user_id
            )
            OR EXISTS (
                -- Company access (regions used by company sites)
                SELECT 1 FROM sites s
                INNER JOIN user_company_access uca ON uca.company_id = s.company_id
                WHERE uca.user_id = :user_id2 AND s.region_id = r.id
            )
        ');
        $stmt->execute(['user_id' => $userId, 'user_id2' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Grant company access to a user
     *
     * @param int $userId
     * @param int $companyId
     * @param int|null $grantedBy
     * @return bool
     */
    public function grantCompanyAccess(int $userId, int $companyId, ?int $grantedBy = null): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT IGNORE INTO user_company_access (user_id, company_id, granted_by)
                VALUES (:user_id, :company_id, :granted_by)
            ');
            return $stmt->execute([
                'user_id' => $userId,
                'company_id' => $companyId,
                'granted_by' => $grantedBy,
            ]);
        } catch (\PDOException $e) {
            error_log('Failed to grant company access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Grant region access to a user
     *
     * @param int $userId
     * @param int $regionId
     * @param int|null $grantedBy
     * @return bool
     */
    public function grantRegionAccess(int $userId, int $regionId, ?int $grantedBy = null): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT IGNORE INTO user_region_access (user_id, region_id, granted_by)
                VALUES (:user_id, :region_id, :granted_by)
            ');
            return $stmt->execute([
                'user_id' => $userId,
                'region_id' => $regionId,
                'granted_by' => $grantedBy,
            ]);
        } catch (\PDOException $e) {
            error_log('Failed to grant region access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Grant site access to a user
     *
     * @param int $userId
     * @param int $siteId
     * @param int|null $grantedBy
     * @return bool
     */
    public function grantSiteAccess(int $userId, int $siteId, ?int $grantedBy = null): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                INSERT IGNORE INTO user_site_access (user_id, site_id, granted_by)
                VALUES (:user_id, :site_id, :granted_by)
            ');
            return $stmt->execute([
                'user_id' => $userId,
                'site_id' => $siteId,
                'granted_by' => $grantedBy,
            ]);
        } catch (\PDOException $e) {
            error_log('Failed to grant site access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke company access from a user
     *
     * @param int $userId
     * @param int $companyId
     * @return bool
     */
    public function revokeCompanyAccess(int $userId, int $companyId): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM user_company_access WHERE user_id = :user_id AND company_id = :company_id
            ');
            return $stmt->execute(['user_id' => $userId, 'company_id' => $companyId]);
        } catch (\PDOException $e) {
            error_log('Failed to revoke company access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke region access from a user
     *
     * @param int $userId
     * @param int $regionId
     * @return bool
     */
    public function revokeRegionAccess(int $userId, int $regionId): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM user_region_access WHERE user_id = :user_id AND region_id = :region_id
            ');
            return $stmt->execute(['user_id' => $userId, 'region_id' => $regionId]);
        } catch (\PDOException $e) {
            error_log('Failed to revoke region access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Revoke site access from a user
     *
     * @param int $userId
     * @param int $siteId
     * @return bool
     */
    public function revokeSiteAccess(int $userId, int $siteId): bool
    {
        try {
            $stmt = $this->pdo->prepare('
                DELETE FROM user_site_access WHERE user_id = :user_id AND site_id = :site_id
            ');
            return $stmt->execute(['user_id' => $userId, 'site_id' => $siteId]);
        } catch (\PDOException $e) {
            error_log('Failed to revoke site access: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's access summary
     * Returns counts and details of all access levels
     *
     * @param int $userId
     * @return array
     */
    public function getUserAccessSummary(int $userId): array
    {
        $user = User::find($userId);
        
        if ($user && $user->role === 'admin') {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM companies');
            $companyCount = (int)$stmt->fetchColumn();
            
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM regions');
            $regionCount = (int)$stmt->fetchColumn();
            
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM sites');
            $siteCount = (int)$stmt->fetchColumn();
            
            return [
                'is_admin' => true,
                'company_count' => $companyCount,
                'region_count' => $regionCount,
                'site_count' => $siteCount,
                'companies' => [],
                'regions' => [],
                'sites' => [],
            ];
        }

        // Get company access
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.name
            FROM companies c
            INNER JOIN user_company_access uca ON uca.company_id = c.id
            WHERE uca.user_id = :user_id
            ORDER BY c.name
        ');
        $stmt->execute(['user_id' => $userId]);
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get region access
        $stmt = $this->pdo->prepare('
            SELECT r.id, r.name
            FROM regions r
            INNER JOIN user_region_access ura ON ura.region_id = r.id
            WHERE ura.user_id = :user_id
            ORDER BY r.name
        ');
        $stmt->execute(['user_id' => $userId]);
        $regions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get site access
        $stmt = $this->pdo->prepare('
            SELECT s.id, s.name, c.name as company_name
            FROM sites s
            LEFT JOIN companies c ON c.id = s.company_id
            INNER JOIN user_site_access usa ON usa.site_id = s.id
            WHERE usa.user_id = :user_id
            ORDER BY s.name
        ');
        $stmt->execute(['user_id' => $userId]);
        $sites = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'is_admin' => false,
            'company_count' => count($companies),
            'region_count' => count($regions),
            'site_count' => count($sites),
            'companies' => $companies,
            'regions' => $regions,
            'sites' => $sites,
        ];
    }
}
