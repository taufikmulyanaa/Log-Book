<?php
// templates/header.php
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}
$userName = $_SESSION['user_name'] ?? 'User';
$current_page = basename($_SERVER['PHP_SELF']);

function getPageTitle($page) {
    switch($page) {
        case 'entry.php': return 'Entry';
        case 'logbook_list.php': return 'Logbook List';
        case 'reporting.php': return 'Reporting';
        case 'import.php': return 'Import Data'; // Ditambahkan
        case 'settings.php': return 'Settings';
        case 'index.php':
        default:
            return 'Dashboard';
    }
}
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
                <li><a href="index.php" class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == 'index.php') ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>"><i class="fas fa-tachometer-alt fa-fw"></i><span>Dashboard</span></a></li>
                <li><a href="entry.php" class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == 'entry.php') ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>"><i class="fas fa-plus-circle fa-fw"></i><span>Entry</span></a></li>
                <li><a href="logbook_list.php" class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == 'logbook_list.php') ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>"><i class="fas fa-table fa-fw"></i><span>Logbook List</span></a></li>
                <li><a href="reporting.php" class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == 'reporting.php') ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>"><i class="fas fa-chart-bar fa-fw"></i><span>Reporting</span></a></li>
                <li><a href="import.php" class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == 'import.php') ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>"><i class="fas fa-file-import fa-fw"></i><span>Import Data</span></a></li>
                <li><a href="settings.php" class="w-full text-left px-3 py-2 text-white text-sm rounded-lg transition duration-200 flex items-center gap-2 <?php echo ($current_page == 'settings.php') ? 'bg-blue-700' : 'hover:bg-blue-600'; ?>"><i class="fas fa-cog fa-fw"></i><span>Settings</span></a></li>
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
                <div class="relative">
                    <button id="profile-menu-button" class="flex items-center gap-3 bg-gray-50 hover:bg-gray-100 rounded-lg px-4 py-2 transition-colors">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center"><span class="text-white text-sm font-semibold"><?php echo strtoupper(substr($userName, 0, 1)); ?></span></div>
                        <div class="text-sm text-left hidden sm:block"><p class="font-medium text-gray-800"><?php echo esc_html($userName); ?></p><p class="text-gray-500 text-xs">Administrator</p></div>
                        <i class="fas fa-chevron-down text-gray-400 text-xs hidden sm:block"></i>
                    </button>
                    <div id="profileMenu" class="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-2 hidden z-50">
                        <a href="#" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"><i class="fas fa-user mr-3 text-gray-400"></i> My Profile</a>
                        <hr class="my-1 border-gray-200">
                        <form action="logout.php" method="post" class="w-full">
                            <input type="hidden" name="csrf_token" value="<?php echo esc_html($_SESSION['csrf_token']); ?>">
                            <button type="submit" class="w-full text-left flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50"><i class="fas fa-sign-out-alt mr-3 text-red-400"></i> Sign Out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <main class="flex-1 overflow-auto p-6">
            <div class="max-w-7xl mx-auto space-y-6">