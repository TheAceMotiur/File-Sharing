<?php

namespace App\Models;

use App\Core\Model;

/**
 * Folder Model
 */
class Folder extends Model
{
    protected $table = 'folders';
    
    /**
     * Get folders by user ID
     * 
     * @param int $userId
     * @return array
     */
    public function getByUser(int $userId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE created_by = ? 
                ORDER BY name ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get folder with file count
     * 
     * @param int $folderId
     * @return array|null
     */
    public function getWithFileCount(int $folderId): ?array
    {
        $sql = "SELECT f.*, COUNT(fu.id) as file_count 
                FROM {$this->table} f 
                LEFT JOIN file_uploads fu ON f.id = fu.folder_id AND fu.deleted_at IS NULL 
                WHERE f.id = ? 
                GROUP BY f.id";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("i", $folderId);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
    
    /**
     * Check if user owns folder
     * 
     * @param int $folderId
     * @param int $userId
     * @return bool
     */
    public function isOwner(int $folderId, int $userId): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE id = ? AND created_by = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $folderId, $userId);
        $stmt->execute();
        return $stmt->get_result()->num_rows > 0;
    }
    
    /**
     * Check if folder belongs to user (alias for isOwner)
     * 
     * @param int $folderId
     * @param int $userId
     * @return bool
     */
    public function belongsToUser(int $folderId, int $userId): bool
    {
        return $this->isOwner($folderId, $userId);
    }
}
