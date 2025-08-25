<?php
/**
 * Script untuk membuat user admin
 * Jalankan sekali saja untuk setup initial admin
 * Setelah selesai, hapus file ini untuk keamanan
 */

require_once __DIR__ . '/../config/init.php';

// Security: Only run from command line or localhost
if (php_sapi_name() !== 'cli' && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1') {
    die('This script can only be run locally for security reasons.');
}

echo "<h2>R&D Logbook - Admin User Creator</h2>\n";
echo "<style>body{font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px;} .success{color: green;} .error{color: red;} .info{color: blue;} code{background: #f5f5f5; padding: 2px 5px; border-radius: 3px;}</style>\n";

try {
    // Check if admin user already exists
    $stmt = $pdo->query("SELECT * FROM users WHERE role = 'admin'");
    $existing_admins = $stmt->fetchAll();
    
    if (!empty($existing_admins)) {
        echo "<div class='info'><strong>Existing Admin Users Found:</strong></div>\n";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>\n";
        echo "<tr><th>ID</th><th>Name</th><th>Username</th><th>Email</th><th>Active</th><th>Created</th></tr>\n";
        
        foreach ($existing_admins as $admin) {
            $active = $admin['is_active'] ? 'Yes' : 'No';
            $created = date('Y-m-d H:i', strtotime($admin['created_at']));
            echo "<tr>";
            echo "<td>{$admin['id']}</td>";
            echo "<td>{$admin['name']}</td>";
            echo "<td><strong>{$admin['username']}</strong></td>";
            echo "<td>{$admin['email']}</td>";
            echo "<td>{$active}</td>";
            echo "<td>{$created}</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<div class='info'><strong>Login Instructions:</strong></div>\n";
        echo "<ol>\n";
        echo "<li>Go to: <a href='login.php'>login.php</a></li>\n";
        echo "<li>Use one of the admin accounts above</li>\n";
        echo "<li>Default password for 'admin' user is: <code>password</code></li>\n";
        echo "<li>Change the password after first login</li>\n";
        echo "</ol>\n";
        
        // Test password for admin user
        $admin_user = null;
        foreach ($existing_admins as $admin) {
            if ($admin['username'] === 'admin') {
                $admin_user = $admin;
                break;
            }
        }
        
        if ($admin_user && password_verify('password', $admin_user['password'])) {
            echo "<div class='success'><strong>✓ Default admin password is working!</strong></div>\n";
        } else {
            echo "<div class='error'><strong>⚠ Default admin password may have been changed.</strong></div>\n";
        }
        
    } else {
        echo "<div class='error'><strong>No Admin Users Found!</strong></div>\n";
        echo "<p>Creating default admin user...</p>\n";
        
        // Create default admin user
        $admin_data = [
            'name' => 'System Administrator',
            'username' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT), // Strong default password
            'email' => 'admin@laboratory.com',
            'role' => 'admin',
            'is_active' => 1
        ];
        
        $sql = "INSERT INTO users (name, username, password, email, role, is_active) VALUES (:name, :username, :password, :email, :role, :is_active)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($admin_data);
        
        $admin_id = $pdo->lastInsertId();
        
        echo "<div class='success'><strong>✓ Admin User Created Successfully!</strong></div>\n";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Field</th><th>Value</th></tr>\n";
        echo "<tr><td><strong>User ID</strong></td><td>{$admin_id}</td></tr>\n";
        echo "<tr><td><strong>Name</strong></td><td>{$admin_data['name']}</td></tr>\n";
        echo "<tr><td><strong>Username</strong></td><td><code>{$admin_data['username']}</code></td></tr>\n";
        echo "<tr><td><strong>Password</strong></td><td><code>admin123</code></td></tr>\n";
        echo "<tr><td><strong>Email</strong></td><td>{$admin_data['email']}</td></tr>\n";
        echo "<tr><td><strong>Role</strong></td><td>{$admin_data['role']}</td></tr>\n";
        echo "</table>\n";
        
        echo "<div class='info'><strong>Next Steps:</strong></div>\n";
        echo "<ol>\n";
        echo "<li>Go to: <a href='login.php'>login.php</a></li>\n";
        echo "<li>Login with: <code>admin</code> / <code>admin123</code></li>\n";
        echo "<li>Change the password immediately</li>\n";
        echo "<li>Delete this file (<code>create_admin.php</code>) for security</li>\n";
        echo "</ol>\n";
    }
    
    // Test database connection and tables
    echo "<hr><div class='info'><strong>System Status Check:</strong></div>\n";
    
    // Check users table
    $user_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "<div class='success'>✓ Users table: {$user_count} users found</div>\n";
    
    // Check instruments table
    $instrument_count = $pdo->query("SELECT COUNT(*) FROM instruments")->fetchColumn();
    echo "<div class='success'>✓ Instruments table: {$instrument_count} instruments found</div>\n";
    
    // Check logbook_entries table
    $entry_count = $pdo->query("SELECT COUNT(*) FROM logbook_entries")->fetchColumn();
    echo "<div class='success'>✓ Logbook entries table: {$entry_count} entries found</div>\n";
    
    // Check PHP version and extensions
    echo "<div class='success'>✓ PHP Version: " . PHP_VERSION . "</div>\n";
    echo "<div class='success'>✓ PDO Extension: " . (extension_loaded('pdo') ? 'Available' : 'Not Available') . "</div>\n";
    echo "<div class='success'>✓ MySQL PDO: " . (extension_loaded('pdo_mysql') ? 'Available' : 'Not Available') . "</div>\n";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>Database Error:</strong><br>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Code: " . $e->getCode() . "</div>\n";
    
    echo "<div class='info'><strong>Troubleshooting Tips:</strong></div>\n";
    echo "<ul>\n";
    echo "<li>Check database connection settings in <code>config/init.php</code></li>\n";
    echo "<li>Ensure database server is running</li>\n";
    echo "<li>Verify database name and credentials</li>\n";
    echo "<li>Check if tables exist and are properly created</li>\n";
    echo "</ul>\n";
    
} catch (Exception $e) {
    echo "<div class='error'><strong>General Error:</strong><br>";
    echo htmlspecialchars($e->getMessage()) . "</div>\n";
}

echo "<hr><div class='info'><strong>Security Notice:</strong><br>";
echo "After creating admin user and logging in successfully, please delete this file (<code>create_admin.php</code>) to prevent unauthorized access.</div>\n";

// Add quick form to create additional admin if needed
if (isset($existing_admins) && !empty($existing_admins)) {
    echo "<hr><h3>Create Additional Admin User</h3>\n";
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        
        if (!empty($name) && !empty($username) && !empty($password)) {
            try {
                // Check if username exists
                $check_stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $check_stmt->execute([$username]);
                
                if ($check_stmt->fetch()) {
                    echo "<div class='error'>Username already exists!</div>\n";
                } else {
                    $new_user_data = [
                        'name' => $name,
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'email' => $email,
                        'role' => 'admin',
                        'is_active' => 1
                    ];
                    
                    $sql = "INSERT INTO users (name, username, password, email, role, is_active) VALUES (:name, :username, :password, :email, :role, :is_active)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($new_user_data);
                    
                    echo "<div class='success'>✓ New admin user '{$username}' created successfully!</div>\n";
                }
            } catch (Exception $e) {
                echo "<div class='error'>Error creating user: " . htmlspecialchars($e->getMessage()) . "</div>\n";
            }
        } else {
            echo "<div class='error'>All fields are required!</div>\n";
        }
    }
    
    echo "<form method='POST' style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<table>\n";
    echo "<tr><td><label>Full Name:</label></td><td><input type='text' name='name' required style='width: 200px; padding: 5px;'></td></tr>\n";
    echo "<tr><td><label>Username:</label></td><td><input type='text' name='username' required style='width: 200px; padding: 5px;'></td></tr>\n";
    echo "<tr><td><label>Password:</label></td><td><input type='password' name='password' required style='width: 200px; padding: 5px;'></td></tr>\n";
    echo "<tr><td><label>Email:</label></td><td><input type='email' name='email' style='width: 200px; padding: 5px;'></td></tr>\n";
    echo "<tr><td colspan='2'><button type='submit' name='create_user' style='background: #007cba; color: white; padding: 10px 20px; border: none; border-radius: 3px; cursor: pointer;'>Create Admin User</button></td></tr>\n";
    echo "</table>\n";
    echo "</form>\n";
}
?>