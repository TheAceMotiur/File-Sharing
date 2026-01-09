<?php

namespace App\Models;

use App\Core\Model;

/**
 * User Model
 */
class User extends Model
{
    protected $table = 'users';
    
    /**
     * Find user by email
     * 
     * @param string $email
     * @return array|null
     */
    public function findByEmail(string $email): ?array
    {
        return $this->findBy('email', $email);
    }
    
    /**
     * Create a new user
     * 
     * @param array $data
     * @return int|false
     */
    public function register(array $data)
    {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        // Set default values
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['email_verified'] = 0;
        $data['is_admin'] = 0;
        $data['premium'] = 0;
        
        return $this->create($data);
    }
    
    /**
     * Verify user password
     * 
     * @param string $email
     * @param string $password
     * @return array|false
     */
    public function authenticate(string $email, string $password)
    {
        $user = $this->findByEmail($email);
        
        if ($user && password_verify($password, $user['password'])) {
            return $user;
        }
        
        return false;
    }
    
    /**
     * Verify email
     * 
     * @param int $userId
     * @return bool
     */
    public function verifyEmail(int $userId): bool
    {
        return $this->update($userId, ['email_verified' => 1]);
    }
    
    /**
     * Update remember token
     * 
     * @param int $userId
     * @param string $token
     * @param string $expires
     * @return bool
     */
    public function setRememberToken(int $userId, string $token, string $expires): bool
    {
        $data = [
            'remember_token' => $token ?: null,
            'remember_token_expires' => $expires ?: null
        ];
        
        return $this->update($userId, $data);
    }
    
    /**
     * Get user by remember token
     * 
     * @param string $token
     * @return array|null
     */
    public function findByRememberToken(string $token): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE remember_token = ? 
                AND remember_token_expires > NOW()";
        $result = $this->query($sql, [$token]);
        return $result->fetch_assoc();
    }
    
    /**
     * Update premium status
     * 
     * @param int $userId
     * @param string $until
     * @return bool
     */
    public function setPremium(int $userId, string $until): bool
    {
        return $this->update($userId, [
            'premium' => 1,
            'premium_until' => $until
        ]);
    }
    
    /**
     * Check if premium is active
     * 
     * @param int $userId
     * @return bool
     */
    public function isPremiumActive(int $userId): bool
    {
        $user = $this->find($userId);
        
        if (!$user || !$user['premium'] || !$user['premium_until']) {
            return false;
        }
        
        return strtotime($user['premium_until']) > time();
    }
    
    /**
     * Get user storage usage
     * 
     * @param int $userId
     * @return int
     */
    public function getStorageUsage(int $userId): int
    {
        $sql = "SELECT SUM(size) as total 
                FROM file_uploads 
                WHERE uploaded_by = ? AND deleted_at IS NULL";
        $result = $this->query($sql, [$userId]);
        $row = $result->fetch_assoc();
        return (int)($row['total'] ?? 0);
    }
}
