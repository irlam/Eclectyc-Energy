<?php
/**
 * eclectyc-energy/app/models/BaseModel.php
 * Base model class for database entities
 * Last updated: 06/11/2024 14:45:00
 */

namespace App\Models;

use App\Config\Database;
use PDO;

abstract class BaseModel
{
    protected static string $table = '';
    protected static string $primaryKey = 'id';
    protected array $attributes = [];
    protected array $original = [];
    
    /**
     * Constructor
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
        $this->original = $this->attributes;
    }
    
    /**
     * Fill attributes
     */
    public function fill(array $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }
        
        return $this;
    }
    
    /**
     * Get attribute
     */
    public function __get(string $name)
    {
        return $this->attributes[$name] ?? null;
    }
    
    /**
     * Set attribute
     */
    public function __set(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }
    
    /**
     * Find by ID
     */
    public static function find(int $id): ?self
    {
        $db = Database::getConnection();
        if (!$db) return null;
        
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        $stmt->execute([$id]);
        
        $data = $stmt->fetch();
        
        return $data ? new static($data) : null;
    }
    
    /**
     * Find all
     */
    public static function all(): array
    {
        $db = Database::getConnection();
        if (!$db) return [];
        
        $stmt = $db->query("SELECT * FROM " . static::$table);
        $results = [];
        
        while ($row = $stmt->fetch()) {
            $results[] = new static($row);
        }
        
        return $results;
    }
    
    /**
     * Where clause
     */
    public static function where(string $column, $value, string $operator = '='): array
    {
        $db = Database::getConnection();
        if (!$db) return [];
        
        $stmt = $db->prepare("SELECT * FROM " . static::$table . " WHERE $column $operator ?");
        $stmt->execute([$value]);
        
        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = new static($row);
        }
        
        return $results;
    }
    
    /**
     * Save model
     */
    public function save(): bool
    {
        $db = Database::getConnection();
        if (!$db) return false;
        
        if (isset($this->attributes[static::$primaryKey])) {
            return $this->update();
        }
        
        return $this->insert();
    }
    
    /**
     * Insert new record
     */
    protected function insert(): bool
    {
        $db = Database::getConnection();
        if (!$db) return false;
        
        $columns = array_keys($this->attributes);
        $placeholders = array_map(fn($col) => ":$col", $columns);
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            static::$table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = $db->prepare($sql);
        
        foreach ($this->attributes as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        if ($stmt->execute()) {
            $this->attributes[static::$primaryKey] = $db->lastInsertId();
            return true;
        }
        
        return false;
    }
    
    /**
     * Update existing record
     */
    protected function update(): bool
    {
        $db = Database::getConnection();
        if (!$db) return false;
        
        $columns = [];
        $params = [];
        
        foreach ($this->attributes as $key => $value) {
            if ($key !== static::$primaryKey) {
                $columns[] = "$key = :$key";
                $params[$key] = $value;
            }
        }
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE %s = :primary_key",
            static::$table,
            implode(', ', $columns),
            static::$primaryKey
        );
        
        $params['primary_key'] = $this->attributes[static::$primaryKey];
        
        $stmt = $db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Delete record
     */
    public function delete(): bool
    {
        if (!isset($this->attributes[static::$primaryKey])) {
            return false;
        }
        
        $db = Database::getConnection();
        if (!$db) return false;
        
        $stmt = $db->prepare("DELETE FROM " . static::$table . " WHERE " . static::$primaryKey . " = ?");
        
        return $stmt->execute([$this->attributes[static::$primaryKey]]);
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->attributes;
    }
    
    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->attributes);
    }
}