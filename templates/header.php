<?php
// templates/header.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'viewer';
$current_page = basename($_SERVER['PHP_SELF']);

function getPageTitle($page) {
    switch($page) {
        case 'entry.php': return 'Entry';
        case 'logbook_list.php': return 'Logbook List';
        case 'reporting.php': return 'Reporting';
        case 'import.php': return 'Import Data';
        case 'settings.php': return 'Settings';
        case 'user_management.php': return 'User Management';
        case 'index.php':
        default:
            return 'Dashboard';
    }
}

// Check if permissions are loaded
if (!function_exists('hasPermission')) {
    // Fallback if permissions not loaded
    function hasPermission($permission) {
        return true;
    }
    function getRoleDisplayName($role) {
        return ucfirst($role);
    }
    function canAccessPage($page) {
        return true;
    }
    function getRoleBadge($role) {
        return '<span class="px-2 py-0.5 text-[10px] rounded-full font-medium bg-gray-100 text-gray-800">' . ucfirst($role) . '</span>';
    }
}

// Navigation items with their required permissions
$navigationItems = [
    [
        'url' => 'index.php',
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'Dashboard',
        'permission' => 'dashboard'
    ],
    [
        'url' => 'entry.php', 
        'icon' => 'fas fa-plus-circle',
        'label' => 'Entry',
        'permission' => 'entry_create'
    ],
    [
        'url' => 'logbook_list.php',
        'icon' => 'fas fa-table', 
        'label' => 'Logbook List',
        'permission' => 'logbook_view'
    ],
    [
        'url' => 'reporting.php',
        'icon' => 'fas fa-chart-bar',
        'label' => 'Reporting', 
        'permission' => 'reporting_view'
    ],
    [
        'url' => 'import.php',
        'icon' => 'fas fa-file-import',
        'label' => 'Import Data',
        'permission' => 'import_data'
    ],
    [
        'url' => 'settings.php',
        'icon' => 'fas fa-cog',
        'label' => 'Settings',
        'permission' => 'settings_view'
    ]
];

$adminItems = [
    [
        'url' => 'user_management.php',
        'icon' => 'fas fa-users-cog',
        'label' => 'User Management',
        'permission' => 'user_management',
        'badge' => 'Admin'
    ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R&D Log Book Management System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-gray-100 flex h-screen overflow-hidden">
    <aside id="sidebar" class="bg-[#005294] w-64 min-h-screen flex flex-col shadow-xl sidebar-transition lg:translate-x-0 transform -translate-x-full fixed lg:relative z-30">
        <div class="p-4 border-b border-blue-400">
            <h1 class="text-white text-lg font-bold text-center"><i class="fas fa-book mr-2 text-sm"></i>R&D Log Book System</h1>
        </div>
        <nav class="flex-1 p-3">
            <ul class="space-y-1">
                <?php foreach ($navigationItems as $item): ?>
                    <?php if (hasPermission($item['permission'])): ?>
                    <li>
                        <a href="<?php echo $item['url']; ?>" 
                           class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == $item['url']) ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>">
                            <i class="<?php echo $item['icon']; ?> fa-fw"></i>
                            <span><?php echo $item['label']; ?></span>
                            <?php if ($userRole === 'viewer' && in_array($item['url'], ['logbook_list.php', 'reporting.php'])): ?>
                                <span class="text-xs bg-gray-500 px-1 rounded ml-auto">View Only</span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- Admin Section -->
                <?php 
                $hasAdminItems = false;
                foreach ($adminItems as $item) {
                    if (hasPermission($item['permission'])) {
                        $hasAdminItems = true;
                        break;
                    }
                }
                ?>
                
                <?php if ($hasAdminItems): ?>
                <li class="border-t border-blue-400 pt-2 mt-2">
                    <div class="text-xs text-blue-200 px-3 py-1 uppercase tracking-wider">Administration</div>
                </li>
                <?php foreach ($adminItems as $item): ?>
                    <?php if (hasPermission($item['permission'])): ?>
                    <li>
                        <a href="<?php echo $item['url']; ?>" 
                           class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == $item['url']) ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>">
                            <i class="<?php echo $item['icon']; ?> fa-fw"></i>
                            <span><?php echo $item['label']; ?></span>
                            <?php if (isset($item['badge'])): ?>
                                <span class="text-xs bg-red-500 px-1 rounded ml-auto"><?php echo $item['badge']; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>

    <div id="overlay" class="fixed inset-0 bg-black bg-opacity-50 z-20 lg:hidden hidden"></div>
    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm border-b border-gray-200 p-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="menu-button" class="lg:hidden text-gray-600 hover:text-gray-800"><i class="fas fa-bars text-xl"></i></button>
                <h2 id="pageTitle" class="text-xl font-semibold text-gray-800"><?php echo getPageTitle($current_page); ?></h2>
            </div>
            <div class="flex items-center gap-4">
                <!-- Debug info (remove in production) -->
                <?php if (isset($_GET['debug'])): ?>
                <div class="text-xs bg-yellow-100 px-2 py-1 rounded border">
                    <strong>Debug:</strong> Role: <?php echo $userRole; ?> | ID: <?php echo $_SESSION['user_id']; ?>
                </div>
                <?php endif; ?>
                
                <div class="relative">
                    <button id="profile-menu-button" class="flex items-center gap-3 bg-gray-50 hover:bg-gray-100 rounded-lg px-4 py-2 transition-colors">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-semibold"><?php echo strtoupper(substr($userName, 0, 1)); ?></span>
                        </div>
                        <div class="text-sm text-left hidden sm:block">
                            <p class="font-medium text-gray-800"><?php echo esc_html($userName); ?></p>
                            <p class="text-gray-500 text-xs"><?php echo getRoleDisplayName($userRole); ?></p>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-xs hidden sm:block"></i>
                    </button>
                    <div id="profileMenu" class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 hidden z-50">
                        <div class="px-4 py-2 border-b text-xs text-gray-500">
                            Logged in as: <strong><?php echo getRoleDisplayName($userRole); ?></strong>
                        </div>
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-user mr-3 text-gray-400"></i> My Profile
                        </a>
                        <?php if (hasPermission('user_management')): ?>
                        <a href="user_management.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-users-cog mr-3 text-gray-400"></i> User Management
                        </a>
                        <?php endif; ?>
                        <?php if (hasPermission('settings_view')): ?>
                        <a href="settings.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            <i class="fas fa-cog mr-3 text-gray-400"></i> Settings
                        </a>
                        <?php endif; ?>
                        <hr class="my-1 border-gray-200">
                        <form action="logout.php" method="post" class="w-full">
                            <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                            <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fas fa-sign-out-alt mr-3 text-red-400"></i> Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto space-y-6">
            
            <!-- Access Denied Message for users without permission -->
            <?php if (function_exists('canAccessPage') && !canAccessPage($current_page)): ?>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-500 mr-3"></i>
                        <div>
                            <h4 class="font-medium text-red-800">Access Denied</h4>
                            <p class="text-sm text-red-700 mt-1">
                                You do not have permission to access this page. 
                                Your current role: <strong><?php echo getRoleDisplayName($userRole); ?></strong>
                            </p>
                            
                            <!-- Show what permissions user has -->
                            <div class="mt-3 text-sm text-red-600">
                                <strong>Available pages for your role:</strong>
                                <ul class="list-disc list-inside mt-1 text-xs">
                                    <?php foreach ($navigationItems as $item): ?>
                                        <?php if (hasPermission($item['permission'])): ?>
                                        <li><a href="<?php echo $item['url']; ?>" class="underline hover:text-red-800"><?php echo $item['label']; ?></a></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            
                            <?php if (defined('ROLE_ADMINISTRATOR') && $userRole !== ROLE_ADMINISTRATOR): ?>
                            <p class="text-sm text-red-600 mt-2">
                                <i class="fas fa-info-circle mr-1"></i>
                                Contact your administrator to upgrade your account permissions.
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <script>
                // Redirect users away from unauthorized pages after 5 seconds
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 5000);
                </script>
            <?php endif; ?>