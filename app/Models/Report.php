<?php

namespace App\Models;

use App\Core\Model;

/**
 * Report Model
 */
class Report extends Model
{
    protected $table = 'file_reports';
    
    /**
     * Create a new report
     * 
     * @param array $data
     * @return int|false
     */
    public function createReport(array $data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['status'] = 'pending';
        return $this->create($data);
    }
    
    /**
     * Get all pending reports
     * 
     * @return array
     */
    public function getPending(): array
    {
        $sql = "SELECT r.*, f.original_name, f.unique_id 
                FROM {$this->table} r 
                LEFT JOIN file_uploads f ON r.file_id = f.id 
                WHERE r.status = 'pending' 
                ORDER BY r.created_at DESC";
        return $this->query($sql)->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get all reports with pagination
     * 
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAllWithPagination(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT r.*, f.original_name, f.unique_id 
                FROM {$this->table} r 
                LEFT JOIN file_uploads f ON r.file_id = f.id 
                ORDER BY r.created_at DESC 
                LIMIT ? OFFSET ?";
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param("ii", $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Update report status
     * 
     * @param int $reportId
     * @param string $status
     * @return bool
     */
    public function updateStatus(int $reportId, string $status): bool
    {
        return $this->update($reportId, [
            'status' => $status,
            'resolved_at' => date('Y-m-d H:i:s')
        ]);
    }
}
