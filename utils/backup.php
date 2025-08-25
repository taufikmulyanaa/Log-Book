<?php
/**
 * R&D Logbook Management System - Backup System
 * File: utils/backup.php
 * 
 * Comprehensive backup system for database and files
 */

require_once __DIR__ . '/../config/init.php';

class LogbookBackupSystem {
    private $pdo;
    private $config;
    private $backup_path;
    private $max_execution_time = 300; // 5 minutes
    
    public function __construct($pdo, $config = null) {
        $this->pdo = $pdo;
        $this->config = $config ?: $this->getDefaultConfig();
        $this->backup_path = $this->config['backup_path'] ?? __DIR__ . '/../backups/';
        
        // Ensure backup directory exists
        if (!is_dir($this->backup_path)) {
            mkdir($this->backup_path, 0755, true);
        }
        
        // Set execution time limit for large backups
        set_time_limit($this->max_execution_time);
    }
    
    private function getDefaultConfig() {
        return [
            'backup_path' => __DIR__ . '/../backups/',
            'compress' => true,
            'include_files' => true,
            'retention_days' => 30,
            'chunk_size' => 1000, // Records per chunk for large tables
            'exclude_tables' => [], // Tables to exclude from backup
            'encrypt' => false,
            'encryption_key' => ''
        ];
    }
    
    /**
     * Create a complete system backup
     */
    public function createFullBackup($description = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $backup_name = "logbook_backup_{$timestamp}";
            $backup_dir = $this->backup_path . $backup_name . '/';
            
            if (!mkdir($backup_dir, 0755, true)) {
                throw new Exception("Failed to create backup directory: $backup_dir");
            }
            
            $manifest = [
                'backup_name' => $backup_name,
                'created_at' => date('Y-m-d H:i:s'),
                'description' => $description,
                'type' => 'full_backup',
                'version' => '1.0',
                'files' => []
            ];
            
            // 1. Backup Database
            $this->logMessage("Starting database backup...");
            $db_backup_file = $this->backupDatabase($backup_dir);
            if ($db_backup_file) {
                $manifest['files']['database'] = basename($db_backup_file);
                $this->logMessage("Database backup completed: " . basename($db_backup_file));
            }
            
            // 2. Backup Configuration Files
            $this->logMessage("Backing up configuration files...");
            $config_backup_file = $this->backupConfiguration($backup_dir);
            if ($config_backup_file) {
                $manifest['files']['configuration'] = basename($config_backup_file);
                $this->logMessage("Configuration backup completed");
            }
            
            // 3. Backup Uploaded Files
            if ($this->config['include_files']) {
                $this->logMessage("Backing up uploaded files...");
                $files_backup_file = $this->backupUploadedFiles($backup_dir);
                if ($files_backup_file) {
                    $manifest['files']['uploads'] = basename($files_backup_file);
                    $this->logMessage("Files backup completed");
                }
            }
            
            // 4. Backup Application Logs
            $this->logMessage("Backing up system logs...");
            $logs_backup_file = $this->backupLogs($backup_dir);
            if ($logs_backup_file) {
                $manifest['files']['logs'] = basename($logs_backup_file);
                $this->logMessage("Logs backup completed");
            }
            
            // 5. Save Manifest
            file_put_contents(
                $backup_dir . 'manifest.json', 
                json_encode($manifest, JSON_PRETTY_PRINT)
            );
            
            // 6. Compress if enabled
            $final_backup_file = null;
            if ($this->config['compress']) {
                $this->logMessage("Compressing backup...");
                $final_backup_file = $this->compressBackup($backup_dir, $backup_name);
                
                // Remove uncompressed directory
                $this->removeDirectory($backup_dir);
            } else {
                $final_backup_file = $backup_dir;
            }
            
            // 7. Clean old backups
            $this->cleanOldBackups();
            
            // 8. Log backup completion
            $backup_size = $this->formatBytes($this->getDirectorySize($final_backup_file));
            $this->logMessage("Backup completed successfully: $backup_name ($backup_size)");
            log_activity("System backup created: $backup_name ($backup_size)");
            
            return [
                'success' => true,
                'backup_name' => $backup_name,
                'backup_file' => $final_backup_file,
                'size' => $backup_size,
                'manifest' => $manifest
            ];
            
        } catch (Exception $e) {
            $this->logMessage("Backup failed: " . $e->getMessage(), 'ERROR');
            log_activity("Backup failed: " . $e->getMessage());
            
            // Cleanup partial backup
            if (isset($backup_dir) && is_dir($backup_dir)) {
                $this->removeDirectory($backup_dir);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Backup database to SQL file
     */
    private function backupDatabase($backup_dir) {
        try {
            $filename = $backup_dir . 'database_' . date('Y-m-d_H-i-s') . '.sql';
            $handle = fopen($filename, 'w');
            
            if (!$handle) {
                throw new Exception("Cannot create database backup file: $filename");
            }
            
            // Write header
            fwrite($handle, "-- R&D Logbook System Database Backup\n");
            fwrite($handle, "-- Created: " . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, "-- MySQL Version: " . $this->pdo->query('SELECT VERSION()')->fetchColumn() . "\n\n");
            fwrite($handle, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "START TRANSACTION;\n");
            fwrite($handle, "SET time_zone = \"+00:00\";\n\n");
            
            // Get all tables
            $tables = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                if (in_array($table, $this->config['exclude_tables'])) {
                    continue;
                }
                
                // Table structure
                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "-- Table structure for table `$table`\n");
                fwrite($handle, "-- --------------------------------------------------------\n\n");
                
                fwrite($handle, "DROP TABLE IF EXISTS `$table`;\n");
                
                $create_table = $this->pdo->query("SHOW CREATE TABLE `$table`")->fetch();
                fwrite($handle, $create_table['Create Table'] . ";\n\n");
                
                // Table data
                fwrite($handle, "-- Dumping data for table `$table`\n\n");
                
                $row_count = $this->pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
                
                if ($row_count > 0) {
                    // Get column info
                    $columns = $this->pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
                    $column_list = '`' . implode('`, `', $columns) . '`';
                    
                    // Process in chunks for large tables
                    $processed = 0;
                    while ($processed < $row_count) {
                        $stmt = $this->pdo->prepare("SELECT * FROM `$table` LIMIT {$this->config['chunk_size']} OFFSET $processed");
                        $stmt->execute();
                        
                        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (!empty($rows)) {
                            fwrite($handle, "INSERT INTO `$table` ($column_list) VALUES\n");
                            
                            $row_values = [];
                            foreach ($rows as $row) {
                                $values = [];
                                foreach ($row as $value) {
                                    if ($value === null) {
                                        $values[] = 'NULL';
                                    } else {
                                        $values[] = $this->pdo->quote($value);
                                    }
                                }
                                $row_values[] = '(' . implode(', ', $values) . ')';
                            }
                            
                            fwrite($handle, implode(",\n", $row_values) . ";\n\n");
                        }
                        
                        $processed += $this->config['chunk_size'];
                    }
                }
            }
            
            fwrite($handle, "COMMIT;\n");
            fclose($handle);
            
            return $filename;
            
        } catch (Exception $e) {
            if (isset($handle)) {
                fclose($handle);
            }
            throw new Exception("Database backup failed: " . $e->getMessage());
        }
    }
    
    /**
     * Backup configuration files
     */
    private function backupConfiguration($backup_dir) {
        $config_dir = __DIR__ . '/../config/';
        $target_file = $backup_dir . 'configuration_' . date('Y-m-d_H-i-s') . '.tar.gz';
        
        if (!is_dir($config_dir)) {
            return null;
        }
        
        // Create tar.gz of config directory
        $files = $this->getDirectoryFiles($config_dir);
        
        if (empty($files)) {
            return null;
        }
        
        $tar_content = '';
        foreach ($files as $file) {
            if (basename($file) === 'env.php') {
                // Mask sensitive data in env.php
                $content = file_get_contents($file);
                $content = preg_replace('/password[\'"\s]*=>[\'"\s]*[^\'",\]]+/', 'password => "****"', $content);
                $tar_content .= $this->createTarEntry(str_replace($config_dir, '', $file), $content);
            } else {
                $tar_content .= $this->createTarEntry(str_replace($config_dir, '', $file), file_get_contents($file));
            }
        }
        
        file_put_contents($target_file, gzencode($tar_content));
        return $target_file;
    }
    
    /**
     * Backup uploaded files
     */
    private function backupUploadedFiles($backup_dir) {
        $uploads_dir = __DIR__ . '/../uploads/';
        $target_file = $backup_dir . 'uploads_' . date('Y-m-d_H-i-s') . '.tar.gz';
        
        if (!is_dir($uploads_dir)) {
            return null;
        }
        
        $files = $this->getDirectoryFiles($uploads_dir);
        
        if (empty($files)) {
            return null;
        }
        
        $tar_content = '';
        foreach ($files as $file) {
            $tar_content .= $this->createTarEntry(str_replace($uploads_dir, '', $file), file_get_contents($file));
        }
        
        file_put_contents($target_file, gzencode($tar_content));
        return $target_file;
    }
    
    /**
     * Backup system logs
     */
    private function backupLogs($backup_dir) {
        $logs_dir = __DIR__ . '/../logs/';
        $target_file = $backup_dir . 'logs_' . date('Y-m-d_H-i-s') . '.tar.gz';
        
        if (!is_dir($logs_dir)) {
            return null;
        }
        
        $files = $this->getDirectoryFiles($logs_dir);
        
        if (empty($files)) {
            return null;
        }
        
        $tar_content = '';
        foreach ($files as $file) {
            // Only backup recent logs (last 30 days)
            if (filemtime($file) > strtotime('-30 days')) {
                $tar_content .= $this->createTarEntry(str_replace($logs_dir, '', $file), file_get_contents($file));
            }
        }
        
        if (!empty($tar_content)) {
            file_put_contents($target_file, gzencode($tar_content));
            return $target_file;
        }
        
        return null;
    }
    
    /**
     * Compress backup directory
     */
    private function compressBackup($backup_dir, $backup_name) {
        $archive_file = $this->backup_path . $backup_name . '.tar.gz';
        
        $files = $this->getDirectoryFiles($backup_dir);
        $tar_content = '';
        
        foreach ($files as $file) {
            $relative_path = str_replace($backup_dir, '', $file);
            $tar_content .= $this->createTarEntry($relative_path, file_get_contents($file));
        }
        
        file_put_contents($archive_file, gzencode($tar_content));
        return $archive_file;
    }
    
    /**
     * Create TAR entry
     */
    private function createTarEntry($filename, $content) {
        $header = str_pad($filename, 100, "\0");
        $header .= str_pad('0644', 8, "0", STR_PAD_LEFT);
        $header .= str_pad('0', 8, "0", STR_PAD_LEFT);
        $header .= str_pad('0', 8, "0", STR_PAD_LEFT);
        $header .= str_pad(sprintf('%o', strlen($content)), 12, "0", STR_PAD_LEFT);
        $header .= str_pad(sprintf('%o', time()), 12, "0", STR_PAD_LEFT);
        $header .= "        ";
        $header .= "0";
        $header .= str_pad('', 100, "\0");
        $header .= str_pad('', 8, "\0");
        $header .= str_pad('', 32, "\0");
        $header .= str_pad('', 32, "\0");
        $header .= str_pad('', 155, "\0");
        $header .= str_pad('', 12, "\0");
        
        // Calculate checksum
        $checksum = 0;
        for ($i = 0; $i < 512; $i++) {
            $checksum += ord($header[$i] ?? "\0");
        }
        
        $header = substr($header, 0, 148) . sprintf('%06o', $checksum) . "\0 " . substr($header, 156);
        
        // Pad content to 512-byte boundary
        $content_padded = $content . str_repeat("\0", (512 - (strlen($content) % 512)) % 512);
        
        return $header . $content_padded;
    }
    
    /**
     * Clean old backups
     */
    private function cleanOldBackups() {
        if ($this->config['retention_days'] <= 0) {
            return;
        }
        
        $cutoff_time = time() - ($this->config['retention_days'] * 24 * 60 * 60);
        $backup_files = glob($this->backup_path . '*');
        
        foreach ($backup_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (is_dir($file)) {
                    $this->removeDirectory($file);
                } else {
                    unlink($file);
                }
                $this->logMessage("Removed old backup: " . basename($file));
            }
        }
    }
    
    /**
     * Get all files in directory recursively
     */
    private function getDirectoryFiles($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }
    
    /**
     * Get directory size
     */
    private function getDirectorySize($path) {
        if (is_file($path)) {
            return filesize($path);
        }
        
        $size = 0;
        $files = $this->getDirectoryFiles($path);
        foreach ($files as $file) {
            $size += filesize($file);
        }
        return $size;
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Log message
     */
    private function logMessage($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";
        
        $log_file = __DIR__ . '/../logs/backup.log';
        file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
        
        // Also echo if running from command line
        if (php_sapi_name() === 'cli') {
            echo $log_entry;
        }
    }
    
    /**
     * List available backups
     */
    public function listBackups() {
        $backups = [];
        $files = glob($this->backup_path . '*');
        
        foreach ($files as $file) {
            if (is_file($file) && (pathinfo($file, PATHINFO_EXTENSION) === 'gz' || 
                                  pathinfo($file, PATHINFO_EXTENSION) === 'tar')) {
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes(filesize($file)),
                    'created' => date('Y-m-d H:i:s', filemtime($file))
                ];
            } elseif (is_dir($file) && strpos(basename($file), 'logbook_backup_') === 0) {
                $manifest_file = $file . '/manifest.json';
                $manifest = file_exists($manifest_file) ? json_decode(file_get_contents($manifest_file), true) : null;
                
                $backups[] = [
                    'name' => basename($file),
                    'path' => $file,
                    'size' => $this->formatBytes($this->getDirectorySize($file)),
                    'created' => date('Y-m-d H:i:s', filemtime($file)),
                    'manifest' => $manifest
                ];
            }
        }
        
        // Sort by creation time (newest first)
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $backups;
    }
    
    /**
     * Delete specific backup
     */
    public function deleteBackup($backup_name) {
        $backup_path = $this->backup_path . $backup_name;
        
        if (!file_exists($backup_path)) {
            throw new Exception("Backup not found: $backup_name");
        }
        
        if (is_dir($backup_path)) {
            $this->removeDirectory($backup_path);
        } else {
            unlink($backup_path);
        }
        
        $this->logMessage("Backup deleted: $backup_name");
        log_activity("Backup deleted: $backup_name by user ID: " . ($_SESSION['user_id'] ?? 'system'));
        
        return true;
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $backup_system = new LogbookBackupSystem($pdo);
    
    $action = $argv[1] ?? 'backup';
    
    switch ($action) {
        case 'backup':
            $description = $argv[2] ?? 'Automated backup';
            $result = $backup_system->createFullBackup($description);
            
            if ($result['success']) {
                echo "Backup completed successfully: {$result['backup_name']}\n";
                echo "Size: {$result['size']}\n";
            } else {
                echo "Backup failed: {$result['error']}\n";
                exit(1);
            }
            break;
            
        case 'list':
            $backups = $backup_system->listBackups();
            echo "Available backups:\n";
            foreach ($backups as $backup) {
                echo "- {$backup['name']} ({$backup['size']}) - {$backup['created']}\n";
            }
            break;
            
        case 'delete':
            if (!isset($argv[2])) {
                echo "Usage: php backup.php delete <backup_name>\n";
                exit(1);
            }
            
            try {
                $backup_system->deleteBackup($argv[2]);
                echo "Backup deleted successfully: {$argv[2]}\n";
            } catch (Exception $e) {
                echo "Delete failed: " . $e->getMessage() . "\n";
                exit(1);
            }
            break;
            
        default:
            echo "Usage: php backup.php [backup|list|delete] [args...]\n";
            exit(1);
    }
}
?>