<?php
/**
 * Master Cron Job Runner
 * This file should be executed every minute by system cron
 * It will check which jobs need to run based on their schedules
 * 
 * Setup: * * * * * cd /path/to/onenetly && php cron/master.php >> logs/cron.log 2>&1
 */

require_once __DIR__ . '/../database/Database.php';

$db = App\Database::getInstance()->getConnection();

// Get current time
$now = new DateTime();
$currentMinute = (int)$now->format('i');
$currentHour = (int)$now->format('H');
$currentDay = (int)$now->format('d');
$currentMonth = (int)$now->format('m');
$currentWeekday = (int)$now->format('w');

echo "[" . $now->format('Y-m-d H:i:s') . "] Master cron started\n";

// Get all active cron jobs
$result = $db->query("SELECT * FROM cron_jobs WHERE is_active = 1 ORDER BY id ASC");

$jobsRun = 0;
$jobsFailed = 0;

while ($job = $result->fetch_assoc()) {
    // Parse cron schedule
    $schedule = explode(' ', $job['schedule']);
    if (count($schedule) < 5) {
        echo "  [SKIP] {$job['name']}: Invalid schedule format\n";
        continue;
    }
    
    list($minute, $hour, $day, $month, $weekday) = $schedule;
    
    // Check if this job should run now
    if (!shouldRunNow($minute, $hour, $day, $month, $weekday, $currentMinute, $currentHour, $currentDay, $currentMonth, $currentWeekday)) {
        continue;
    }
    
    // Check if already running
    if ($job['last_run_status'] === 'running') {
        echo "  [SKIP] {$job['name']}: Already running\n";
        continue;
    }
    
    // Check if already ran this minute
    if ($job['last_run_at']) {
        $lastRun = new DateTime($job['last_run_at']);
        if ($lastRun->format('Y-m-d H:i') === $now->format('Y-m-d H:i')) {
            echo "  [SKIP] {$job['name']}: Already ran this minute\n";
            continue;
        }
    }
    
    echo "  [RUN] {$job['name']}...\n";
    
    // Update status to running
    $stmt = $db->prepare("UPDATE cron_jobs SET last_run_status = 'running', last_run_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $job['id']);
    $stmt->execute();
    
    // Prepare command with correct PHP path
    $command = $job['command'];
    
    // Fix PHP path on Windows/XAMPP
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // If command starts with 'php', replace with full path
        if (stripos(trim($command), 'php ') === 0) {
            $phpPath = 'C:\\xampp\\php\\php.exe';
            if (file_exists($phpPath)) {
                $scriptPath = trim(substr($command, 4));
                
                // Convert relative path to absolute path
                if (!preg_match('/^[a-zA-Z]:[\\\\\/]/', $scriptPath)) {
                    // It's a relative path, make it absolute
                    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . $scriptPath;
                    $scriptPath = str_replace('/', DIRECTORY_SEPARATOR, $scriptPath);
                }
                
                $command = '"' . $phpPath . '" "' . $scriptPath . '"';
            }
        }
    }
    
    // Execute command
    $output = [];
    $return_var = 0;
    $startTime = microtime(true);
    
    exec($command . ' 2>&1', $output, $return_var);
    
    $executionTime = round(microtime(true) - $startTime, 2);
    $status = $return_var === 0 ? 'success' : 'failed';
    $output_text = implode("\n", $output);
    
    // Limit output to 5000 characters
    if (strlen($output_text) > 5000) {
        $output_text = substr($output_text, 0, 5000) . "\n... (truncated)";
    }
    
    // Update results
    $stmt = $db->prepare("UPDATE cron_jobs SET last_run_status = ?, last_run_output = ?, run_count = run_count + 1, last_run_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $output_text, $job['id']);
    $stmt->execute();
    
    if ($status === 'success') {
        echo "    ✓ Success ({$executionTime}s)\n";
        $jobsRun++;
    } else {
        echo "    ✗ Failed ({$executionTime}s) - Exit code: {$return_var}\n";
        $jobsFailed++;
    }
}

echo "[" . $now->format('Y-m-d H:i:s') . "] Master cron finished: {$jobsRun} run, {$jobsFailed} failed\n\n";

/**
 * Check if a cron schedule matches the current time
 */
function shouldRunNow($minute, $hour, $day, $month, $weekday, $currentMinute, $currentHour, $currentDay, $currentMonth, $currentWeekday) {
    // Check minute
    if (!matchesCronValue($minute, $currentMinute, 0, 59)) return false;
    
    // Check hour
    if (!matchesCronValue($hour, $currentHour, 0, 23)) return false;
    
    // Check day of month
    if (!matchesCronValue($day, $currentDay, 1, 31)) return false;
    
    // Check month
    if (!matchesCronValue($month, $currentMonth, 1, 12)) return false;
    
    // Check day of week (0 = Sunday)
    if (!matchesCronValue($weekday, $currentWeekday, 0, 6)) return false;
    
    return true;
}

/**
 * Check if a cron value matches the current value
 */
function matchesCronValue($cronValue, $currentValue, $min, $max) {
    // * means any value
    if ($cronValue === '*') return true;
    
    // */n means every n units
    if (preg_match('/^\*\/(\d+)$/', $cronValue, $matches)) {
        $step = (int)$matches[1];
        return ($currentValue % $step) === 0;
    }
    
    // Specific value
    if (is_numeric($cronValue)) {
        return (int)$cronValue === $currentValue;
    }
    
    // Range (e.g., 1-5)
    if (preg_match('/^(\d+)-(\d+)$/', $cronValue, $matches)) {
        $start = (int)$matches[1];
        $end = (int)$matches[2];
        return $currentValue >= $start && $currentValue <= $end;
    }
    
    // List (e.g., 1,3,5)
    if (strpos($cronValue, ',') !== false) {
        $values = array_map('intval', explode(',', $cronValue));
        return in_array($currentValue, $values);
    }
    
    return false;
}
