<?php
// config/env.php
// Production environment configuration
// Copy from env.example.php and modify for your environment

return [
    'database' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'name' => $_ENV['DB_NAME'] ?? 'logbook_db_rnd',
        'username' => $_ENV['DB_USER'] ?? 'root',
        'password' => $_ENV['DB_PASS'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]
    ],
    
    'security' => [
        'max_login_attempts' => (int)($_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5),
        'lockout_time' => $_ENV['LOCKOUT_TIME'] ?? '15 minutes',
        'session_lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 1800), // 30 minutes
        'csrf_token_expire' => (int)($_ENV['CSRF_EXPIRE'] ?? 3600), // 1 hour
        'password_hash_algo' => PASSWORD_DEFAULT,
        'secure_cookies' => isset($_SERVER['HTTPS']),
        'session_name' => 'RND_LOGBOOK_SESSION',
    ],
    
    'upload' => [
        'max_file_size' => (int)($_ENV['MAX_FILE_SIZE'] ?? 10 * 1024 * 1024), // 10MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif', 'xlsx', 'xls', 'txt'],
        'upload_path' => $_ENV['UPLOAD_PATH'] ?? __DIR__ . '/../uploads/',
        'quarantine_path' => $_ENV['QUARANTINE_PATH'] ?? __DIR__ . '/../uploads/quarantine/',
        'max_files_per_entry' => (int)($_ENV['MAX_FILES_PER_ENTRY'] ?? 5),
    ],
    
    'logging' => [
        'app_log' => $_ENV['APP_LOG_PATH'] ?? __DIR__ . '/../logs/app.log',
        'error_log' => $_ENV['ERROR_LOG_PATH'] ?? __DIR__ . '/../logs/error.log',
        'audit_log' => $_ENV['AUDIT_LOG_PATH'] ?? __DIR__ . '/../logs/audit.log',
        'max_log_size' => (int)($_ENV['MAX_LOG_SIZE'] ?? 50 * 1024 * 1024), // 50MB
        'log_retention_days' => (int)($_ENV['LOG_RETENTION_DAYS'] ?? 90),
        'log_level' => $_ENV['LOG_LEVEL'] ?? 'INFO', // DEBUG, INFO, WARNING, ERROR
    ],
    
    'email' => [
        'enabled' => filter_var($_ENV['EMAIL_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'smtp_host' => $_ENV['SMTP_HOST'] ?? 'localhost',
        'smtp_port' => (int)($_ENV['SMTP_PORT'] ?? 587),
        'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
        'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
        'smtp_encryption' => $_ENV['SMTP_ENCRYPTION'] ?? 'tls', // tls, ssl, or null
        'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@laboratory.com',
        'from_name' => $_ENV['FROM_NAME'] ?? 'R&D Logbook System',
        'admin_email' => $_ENV['ADMIN_EMAIL'] ?? 'admin@laboratory.com',
    ],
    
    'app' => [
        'name' => $_ENV['APP_NAME'] ?? 'R&D Logbook System',
        'version' => $_ENV['APP_VERSION'] ?? '1.0.0',
        'environment' => $_ENV['APP_ENV'] ?? 'production', // development, staging, production
        'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'timezone' => $_ENV['APP_TIMEZONE'] ?? 'Asia/Jakarta',
        'url' => $_ENV['APP_URL'] ?? 'https://logbook.laboratory.com',
        'maintenance_mode' => filter_var($_ENV['MAINTENANCE_MODE'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'maintenance_message' => $_ENV['MAINTENANCE_MESSAGE'] ?? 'System is under maintenance. Please try again later.',
        'language' => $_ENV['APP_LANGUAGE'] ?? 'en',
    ],
    
    'backup' => [
        'enabled' => filter_var($_ENV['BACKUP_ENABLED'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'schedule' => $_ENV['BACKUP_SCHEDULE'] ?? 'daily', // daily, weekly, monthly
        'retention_days' => (int)($_ENV['BACKUP_RETENTION_DAYS'] ?? 30),
        'backup_path' => $_ENV['BACKUP_PATH'] ?? __DIR__ . '/../backups/',
        'compress' => filter_var($_ENV['BACKUP_COMPRESS'] ?? true, FILTER_VALIDATE_BOOLEAN),
        'encrypt' => filter_var($_ENV['BACKUP_ENCRYPT'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'encryption_key' => $_ENV['BACKUP_ENCRYPTION_KEY'] ?? '',
        'remote_storage' => [
            'enabled' => filter_var($_ENV['REMOTE_BACKUP_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'type' => $_ENV['REMOTE_BACKUP_TYPE'] ?? 'ftp', // ftp, sftp, s3
            'host' => $_ENV['REMOTE_BACKUP_HOST'] ?? '',
            'username' => $_ENV['REMOTE_BACKUP_USER'] ?? '',
            'password' => $_ENV['REMOTE_BACKUP_PASS'] ?? '',
            'path' => $_ENV['REMOTE_BACKUP_PATH'] ?? '/backups/',
        ],
    ],
    
    'cache' => [
        'enabled' => filter_var($_ENV['CACHE_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'type' => $_ENV['CACHE_TYPE'] ?? 'file', // file, redis, memcached
        'path' => $_ENV['CACHE_PATH'] ?? __DIR__ . '/../cache/',
        'default_ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600), // 1 hour
        'redis' => [
            'host' => $_ENV['REDIS_HOST'] ?? '127.0.0.1',
            'port' => (int)($_ENV['REDIS_PORT'] ?? 6379),
            'password' => $_ENV['REDIS_PASSWORD'] ?? '',
            'database' => (int)($_ENV['REDIS_DATABASE'] ?? 0),
        ],
    ],
    
    'api' => [
        'enabled' => filter_var($_ENV['API_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'rate_limit' => (int)($_ENV['API_RATE_LIMIT'] ?? 100), // requests per hour
        'token_expire' => (int)($_ENV['API_TOKEN_EXPIRE'] ?? 3600), // 1 hour
        'cors_enabled' => filter_var($_ENV['API_CORS_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'cors_origins' => array_filter(explode(',', $_ENV['API_CORS_ORIGINS'] ?? '')),
    ],
    
    'ldap' => [
        'enabled' => filter_var($_ENV['LDAP_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'host' => $_ENV['LDAP_HOST'] ?? 'ldap.company.com',
        'port' => (int)($_ENV['LDAP_PORT'] ?? 389),
        'base_dn' => $_ENV['LDAP_BASE_DN'] ?? 'dc=company,dc=com',
        'user_dn' => $_ENV['LDAP_USER_DN'] ?? 'cn=admin,dc=company,dc=com',
        'password' => $_ENV['LDAP_PASSWORD'] ?? '',
        'user_filter' => $_ENV['LDAP_USER_FILTER'] ?? '(uid=%s)',
        'attributes' => [
            'username' => $_ENV['LDAP_ATTR_USERNAME'] ?? 'uid',
            'email' => $_ENV['LDAP_ATTR_EMAIL'] ?? 'mail',
            'name' => $_ENV['LDAP_ATTR_NAME'] ?? 'cn',
        ],
    ],
    
    'monitoring' => [
        'enabled' => filter_var($_ENV['MONITORING_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'log_queries' => filter_var($_ENV['LOG_QUERIES'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'slow_query_time' => (int)($_ENV['SLOW_QUERY_TIME'] ?? 1000), // milliseconds
        'memory_limit_alert' => (int)($_ENV['MEMORY_LIMIT_ALERT'] ?? 80), // percentage
        'disk_space_alert' => (int)($_ENV['DISK_SPACE_ALERT'] ?? 90), // percentage
    ],
];