<?php
// public/login.php
require_once __DIR__ . '/../config/init.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) { 
        die('CSRF token tidak valid.'); 
    }
    
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) { 
        $error_message = 'Username dan password wajib diisi.'; 
    } else {
        // Query with role information
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username AND is_active = 1");
        $stmt->execute(['username' => $username]);
        $user = $stmt->fetch();
        
        if ($user && $user['failed_login_attempts'] >= MAX_LOGIN_ATTEMPTS && strtotime($user['last_failed_login']) > strtotime('-' . LOGIN_LOCKOUT_TIME)) { 
            $error_message = 'Akun Anda terkunci sementara. Coba lagi dalam 15 menit.'; 
        } elseif ($user && password_verify($password, $user['password'])) {
            // Reset failed attempts
            $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, last_failed_login = NULL WHERE id = :id");
            $stmt->execute(['id' => $user['id']]);
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            // Store complete user information in session
            $_SESSION['user_id'] = $user['id']; 
            $_SESSION['user_name'] = $user['name']; 
            $_SESSION['user_role'] = $user['role']; // IMPORTANT: Store role
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['session_created_time'] = time();
            
            // Log successful login
            log_activity("User logged in: {$user['name']} (Role: {$user['role']})");
            
            header('Location: index.php'); 
            exit();
        } else {
            $error_message = 'Username atau password salah.';
            if ($user) {
                $stmt = $pdo->prepare("UPDATE users SET failed_login_attempts = failed_login_attempts + 1, last_failed_login = NOW() WHERE id = :id");
                $stmt->execute(['id' => $user['id']]);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - R&D Log Book</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <script>
        tailwind.config = { 
            theme: { 
                extend: { 
                    fontFamily: { sans: ['Inter', 'sans-serif'] }, 
                    colors: { 
                        border: 'hsl(214.3, 31.8%, 91.4%)', 
                        input: 'hsl(214.3, 31.8%, 91.4%)', 
                        ring: 'hsl(222.2, 84%, 4.9%)', 
                        background: 'hsl(0, 0%, 100%)', 
                        foreground: 'hsl(222.2, 84%, 4.9%)', 
                        primary: { DEFAULT: 'hsl(221.2, 83.2%, 53.3%)', foreground: 'hsl(210, 40%, 98%)' }, 
                        secondary: { DEFAULT: 'hsl(210, 40%, 96.1%)', foreground: 'hsl(222.2, 47.4%, 11.2%)' }, 
                        muted: { DEFAULT: 'hsl(210, 40%, 96.1%)', foreground: 'hsl(215.4, 16.3%, 46.9%)' }, 
                        accent: { DEFAULT: 'hsl(210, 40%, 96.1%)', foreground: 'hsl(222.2, 47.4%, 11.2%)' }, 
                        card: { DEFAULT: 'hsl(0, 0%, 100%)', foreground: 'hsl(222.2, 84%, 4.9%)' } 
                    }, 
                    borderRadius: { lg: `0.5rem`, md: `calc(0.5rem - 2px)`, sm: `calc(0.5rem - 4px)`} 
                } 
            } 
        }
    </script>
</head>
<body class="bg-muted/40 font-sans text-foreground antialiased flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md bg-card p-8 rounded-xl border border-border shadow-lg">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-flask text-white text-2xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-foreground">R&D Log Book System</h1>
            <p class="text-muted-foreground text-sm mt-1">Silakan masuk untuk melanjutkan</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-300 text-red-800 px-4 py-3 rounded-md relative mb-4 text-sm" role="alert">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span><?php echo esc_html($error_message); ?></span>
                </div>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
            
            <div>
                <label for="username" class="block text-sm font-medium text-muted-foreground mb-1">Username</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-muted-foreground"></i>
                    </div>
                    <input type="text" id="username" name="username" 
                           class="w-full bg-muted border border-border rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2" 
                           placeholder="Masukkan username" required>
                </div>
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-muted-foreground mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-muted-foreground"></i>
                    </div>
                    <input type="password" id="password" name="password" 
                           class="w-full bg-muted border border-border rounded-lg pl-10 pr-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2" 
                           placeholder="Masukkan password" required>
                </div>
            </div>
            
            <div>
                <button type="submit" 
                        class="w-full mt-4 px-4 py-2 text-sm font-medium bg-blue-600 text-white rounded-lg flex items-center justify-center gap-2 hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk ke Sistem
                </button>
            </div>
        </form>
        
        <div class="mt-6 pt-6 border-t border-border">
            <div class="text-center">
                <h4 class="text-sm font-medium text-foreground mb-2">Default Admin Account:</h4>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-xs">
                    <div class="space-y-1">
                        <div><strong>Username:</strong> admin</div>
                        <div><strong>Password:</strong> password</div>
                    </div>
                    <p class="text-blue-600 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Change default password after first login
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
</body>
</html>