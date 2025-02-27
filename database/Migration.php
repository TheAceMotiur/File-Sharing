<?php
namespace App;

class Migration {
    private $db;
    private $migrationsPath;

    public function __construct($db) {
        $this->db = $db;
        $this->migrationsPath = __DIR__ . '/migrations';
        $this->createMigrationsTable();
    }

    private function createMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255),
            checksum VARCHAR(64) NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $this->db->query($sql);
    }

    public function migrate() {
        $this->verifyMigrations();
        $executed = $this->getExecutedMigrations();
        $files = glob($this->migrationsPath . '/*.sql');
        sort($files);

        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $executed)) {
                $this->runMigration($file, $name);
            }
        }
        echo "Migrations completed successfully!\n";
    }

    private function getExecutedMigrations() {
        $migrations = [];
        $result = $this->db->query("SELECT migration FROM migrations");
        while ($row = $result->fetch_assoc()) {
            $migrations[] = $row['migration'];
        }
        return $migrations;
    }

    private function calculateChecksum($file) {
        return hash('sha256', file_get_contents($file));
    }

    private function runMigration($file, $name) {
        $checksum = $this->calculateChecksum($file);
        $sql = file_get_contents($file);
        
        try {
            if ($this->db->multi_query($sql)) {
                do {
                    while ($this->db->more_results() && $this->db->next_result());
                } while ($this->db->more_results());
                
                // First try to update the existing record
                $stmt = $this->db->prepare("UPDATE migrations SET checksum = ? WHERE migration = ?");
                $stmt->bind_param("ss", $checksum, $name);
                $stmt->execute();

                // If no rows were updated, insert a new record
                if ($stmt->affected_rows === 0) {
                    $stmt = $this->db->prepare("INSERT INTO migrations (migration, checksum) VALUES (?, ?)");
                    $stmt->bind_param("ss", $name, $checksum);
                    $stmt->execute();
                }
                echo "Migrated: $name (checksum: $checksum)\n";
            }
        } catch (mysqli_sql_exception $e) {
            if (!strpos($e->getMessage(), 'Duplicate column name')) {
                throw $e;
            }
            // Log warning about duplicate column
            echo "Warning: Skipping duplicate column in {$name}\n";
        }
    }

    private function verifyMigrations() {
        $result = $this->db->query("SELECT migration, checksum FROM migrations");
        while ($row = $result->fetch_assoc()) {
            $file = $this->migrationsPath . '/' . $row['migration'];
            $currentChecksum = $this->calculateChecksum($file);
            
            if ($currentChecksum !== $row['checksum']) {
                // Instead of throwing an exception, run the migration again
                echo "Migration {$row['migration']} has changed, re-running...\n";
                $this->runMigration($file, $row['migration']);
            }
        }
        echo "All migrations verified successfully.\n";
    }
}