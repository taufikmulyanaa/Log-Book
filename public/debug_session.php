<?php
/**
 * Debug Session & Role Script
 * Untuk troubleshooting masalah login dan permissions
 */

require_once __DIR__ . '/../config/init.php';

echo "<h2>Session & Role Debug Information</h2>\n";
echo "<style>
    body { font-family: Arial, sans-serif; max-width: 1000px; margin: 20px auto; padding: 20px; }
    .success { color: green; background: #f0fff0; padding: 10px; border: 1px solid green; border-radius: 5px; }
    .error { color: red; background: #fff0f0; padding: 10px; border: 1px solid red; border-radius: 5px; }
    .info { color: blue; background: #f0f0ff; padding: 10px; border: 1px solid blue; border-radius: 5px; }
    .warning { color: orange; background: #fff8f0; padding: 10px; border: 1px solid orange; border-radius: 5px; }
    table { width: 100%; border-collapse: collapse; margin: 10px 0; }
    th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
    th { background-color: #f5f5f5; }
    code { background: #f5f5f5; padding: 2px 5px; border-radius: 3px; }
    pre { background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 5px; overflow-x: auto; }
</style>\n";

// 1. Session Status
echo "<h3>1. Session Information</h3>\n";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='success'>✓ Session is active</div>\n";
    
    echo "<h4>Session Data:</h4>\n";
    echo "<table>\n";
    echo "<tr><th>Key</th><th>Value</th><th>Type</th></tr>\n";
    
    foreach ($_SESSION as $key => $value) {
        $type = gettype($value);
        $display_value = is_string($value) ? htmlspecialchars($value) : json_encode($value);
        echo "<tr><td><code>{$key}</code></td><td>{$display_value}</td><td>{$type}</td></tr>\n";
    }
    echo "</table>\n";
    
} else {
    echo "<div class='error'>✗ No active session found</div>\n";
    echo "<p><strong>Solution:</strong> You need to <a href='login.php'>login first</a></p>\n";
}

// 2. User Authentication Status
echo "<h3>2. Authentication Status</h3>\n";

if (isset($_SESSION['user_id'])) {
    echo "<div class='success'>✓ User is logged in</div>\n";
    
    // Get user details from database
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<h4>Database User Information:</h4>\n";
            echo "<table>\n";
            echo "<tr><th>Database Field</th><th>Value</th><th>Session Value</th><th>Match</th></tr>\n";
            
            $fields = ['id' => 'user_id', 'name' => 'user_name', 'role' => 'user_role', 'email' => 'user_email'];
            foreach ($fields as $db_field => $session_key) {
                $db_value = $user[$db_field] ?? 'NULL';
                $session_value = $_SESSION[$session_key] ?? 'NOT SET';
                $match = (string)$db_value === (string)$session_value ? '✓' : '✗';
                $row_class = $match === '✓' ? 'success' : 'error';
                
                echo "<tr class='{$row_class}'>";
                echo "<td><strong>{$db_field}</strong></td>";
                echo "<td>{$db_value}</td>";
                echo "<td>{$session_value}</td>";
                echo "<td>{$match}</td>";
                echo "</tr>\n";
            }
            echo "</table>\n";
            
            // Check if user is active
            if ($user['is_active']) {
                echo "<div class='success'>✓ User account is active</div>\n";
            } else {
                echo "<div class='error'>✗ User account is deactivated</div>\n";
            }
            
        } else {
            echo "<div class='error'>✗ User not found in database (ID: {$_SESSION['user_id']})</div>\n";
        }
        
    } catch (PDOException $e) {
        echo "<div class='error'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>\n";
    }
    
} else {
    echo "<div class='error'>✗ User not logged in</div>\n";
    echo "<p><strong>Solution:</strong> <a href='login.php'>Login here</a></p>\n";
}

// 3. Role and Permissions Check
echo "<h3>3. Role & Permissions Check</h3>\n";

$current_role = $_SESSION['user_role'] ?? 'not-set';
echo "<div class='info'>Current Role: <strong>{$current_role}</strong></div>\n";

// Check role permissions
$role_permissions = [
    'admin' => ['All pages', 'User management', 'System settings', 'Import/Export', 'All CRUD operations'],
    'user' => ['Dashboard', 'Create entries', 'Edit own entries', 'View reports', 'Import data'],
    'viewer' => ['Dashboard', 'View entries', 'View reports (read-only)']
];

if (isset($role_permissions[$current_role])) {
    echo "<h4>Permissions for '{$current_role}' role:</h4>\n";
    echo "<ul>\n";
    foreach ($role_permissions[$current_role] as $permission) {
        echo "<li>✓ {$permission}</li>\n";
    }
    echo "</ul>\n";
} else {
    echo "<div class='error'>Unknown or invalid role: '{$current_role}'</div>\n";
}

// 4. Admin Access Test
echo "<h3>4. Admin Access Test</h3>\n";

if ($current_role === 'admin') {
    echo "<div class='success'>✓ You have admin privileges</div>\n";
    echo "<p>You should be able to access:</p>\n";
    echo "<ul>\n";
    echo "<li><a href='user_management.php'>User Management</a></li>\n";
    echo "<li><a href='settings.php'>Advanced Settings</a></li>\n";
    echo "</ul>\n";
} else {
    echo "<div class='warning'>⚠ You do not have admin privileges</div>\n";
    echo "<p><strong>Current role:</strong> {$current_role}</p>\n";
    echo "<p><strong>To get admin access:</strong></p>\n";
    echo "<ol>\n";
    echo "<li>Contact existing admin to upgrade your role</li>\n";
    echo "<li>Or run <a href='create_admin.php'>create_admin.php</a> to create admin account</li>\n";
    echo "<li>Or manually update database:</li>\n";
    echo "</ol>\n";
    
    if (isset($_SESSION['user_id'])) {
        echo "<div class='info'>\n";
        echo "<strong>Manual Database Update:</strong><br>\n";
        echo "<code>UPDATE users SET role = 'admin' WHERE id = {$_SESSION['user_id']};</code><br>\n";
        echo "<small>Then logout and login again</small>\n";
        echo "</div>\n";
    }
}

// 5. Quick Actions
echo "<h3>5. Quick Actions</h3>\n";
echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px;'>\n";

if (!isset($_SESSION['user_id'])) {
    echo "<a href='login.php' style='background: #007cba; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px;'>Login</a>\n";
}

echo "<a href='create_admin.php' style='background: #28a745; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px;'>Create Admin</a>\n";

if (isset($_SESSION['user_id'])) {
    echo "<a href='user_management.php' style='background: #6f42c1; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px;'>User Management</a>\n";
    echo "<a href='index.php' style='background: #17a2b8; color: white; padding: 10px 15px; text-decoration: none; border-radius: 3px; margin: 5px;'>Dashboard</a>\n";
    
    echo "<form method='post' action='logout.php' style='display: inline; margin: 5px;'>\n";
    echo "<input type='hidden' name='csrf_token' value='" . htmlspecialchars($_SESSION['csrf_token']) . "'>\n";
    echo "<button type='submit' style='background: #dc3545; color: white; padding: 10px 15px; border: none; border-radius: 3px; cursor: pointer;'>Logout</button>\n";
    echo "</form>\n";
}
echo "</div>\n";

// 6. System Configuration Check
echo "<h3>6. System Configuration</h3>\n";

try {
    // Check database connection
    $stmt = $pdo->query("SELECT 1");
    echo "<div class='success'>✓ Database connection working</div>\n";
    
    // Check tables
    $tables = ['users', 'logbook_entries', 'instruments'];
    foreach ($tables as $table) {
        try {
            $count = $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            echo "<div class='success'>✓ Table '{$table}': {$count} records</div>\n";
        } catch (PDOException $e) {
            echo "<div class='error'>✗ Table '{$table}': Error - " . htmlspecialchars($e->getMessage()) . "</div>\n";
        }
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>✗ Database connection failed: " . htmlspecialchars($e->getMessage()) . "</div>\n";
}

// 7. PHP Information
echo "<h3>7. PHP Environment</h3>\n";
echo "<div class='info'>\n";
echo "<strong>PHP Version:</strong> " . PHP_VERSION . "<br>\n";
echo "<strong>Session Status:</strong> " . session_status() . " (1=disabled, 2=active)<br>\n";
echo "<strong>Memory Limit:</strong> " . ini_get('memory_limit') . "<br>\n";
echo "<strong>Upload Max:</strong> " . ini_get('upload_max_filesize') . "<br>\n";
echo "<strong>Server:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>\n";
echo "</div>\n";

// Auto-refresh option
echo "<hr><div style='text-align: center; margin: 20px 0;'>\n";
echo "<a href='?' style='background: #6c757d; color: white; padding: 8px 15px; text-decoration: none; border-radius: 3px;'>Refresh Debug Info</a>\n";
echo "</div>\n";

echo "<div class='warning'><strong>Security Note:</strong><br>";
echo "Remove this debug file (<code>debug_session.php</code>) from production server to prevent information disclosure.</div>\n";
?>