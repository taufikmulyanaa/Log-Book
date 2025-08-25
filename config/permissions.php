<?php
/**
 * Enhanced Permission System
 * File: config/permissions.php
 * 
 * Define all permissions for different user roles
 */

// Role definitions
define('ROLE_ADMINISTRATOR', 'administrator');
define('ROLE_CONTRIBUTOR', 'contributor');
define('ROLE_VIEWER', 'viewer');

/**
 * Permission matrix for different user roles
 */
$PERMISSIONS = [
    ROLE_ADMINISTRATOR => [
        // Full access to everything
        'dashboard' => true,
        'entry_create' => true,
        'entry_edit_own' => true,
        'entry_edit_all' => true,
        'entry_delete_own' => true,
        'entry_delete_all' => true,
        'entry_view' => true,
        'logbook_view' => true,
        'logbook_export' => true,
        'reporting_view' => true,
        'reporting_export' => true,
        'import_data' => true,
        'settings_view' => true,
        'settings_edit' => true,
        'settings_matrix' => true,
        'settings_system' => true,
        'user_management' => true,
        'audit_view' => true,
    ],
    
    ROLE_CONTRIBUTOR => [
        // Entry and edit access with limited settings
        'dashboard' => true,
        'entry_create' => true,
        'entry_edit_own' => true,
        'entry_edit_all' => false,
        'entry_delete_own' => true,
        'entry_delete_all' => false,
        'entry_view' => true,
        'logbook_view' => true,
        'logbook_export' => true,
        'reporting_view' => true,
        'reporting_export' => false,
        'import_data' => true,
        'settings_view' => true,
        'settings_edit' => false,
        'settings_matrix' => false,
        'settings_system' => false,
        'user_management' => false,
        'audit_view' => false,
    ],
    
    ROLE_VIEWER => [
        // View only access
        'dashboard' => true,
        'entry_create' => false,
        'entry_edit_own' => false,
        'entry_edit_all' => false,
        'entry_delete_own' => false,
        'entry_delete_all' => false,
        'entry_view' => true,
        'logbook_view' => true,
        'logbook_export' => false,
        'reporting_view' => true,
        'reporting_export' => false,
        'import_data' => false,
        'settings_view' => false,
        'settings_edit' => false,
        'settings_matrix' => false,
        'settings_system' => false,
        'user_management' => false,
        'audit_view' => false,
    ]
];

/**
 * Check if current user has specific permission
 *
 * @param string $permission Permission to check
 * @param string|null $userRole User role (if not provided, uses session)
 * @return bool
 */
function hasPermission($permission, $userRole = null) {
    global $PERMISSIONS;
    
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }
    
    if (!$userRole || !isset($PERMISSIONS[$userRole])) {
        return false;
    }
    
    return $PERMISSIONS[$userRole][$permission] ?? false;
}

/**
 * Check if user can access a specific page
 *
 * @param string $page Page filename
 * @param string|null $userRole User role
 * @return bool
 */
function canAccessPage($page, $userRole = null) {
    $pagePermissions = [
        'index.php' => 'dashboard',
        'entry.php' => 'entry_create',
        'logbook_list.php' => 'logbook_view',
        'reporting.php' => 'reporting_view',
        'import.php' => 'import_data',
        'settings.php' => 'settings_view',
        'user_management.php' => 'user_management',
    ];
    
    $requiredPermission = $pagePermissions[$page] ?? null;
    
    if (!$requiredPermission) {
        return true; // Allow access to undefined pages
    }
    
    return hasPermission($requiredPermission, $userRole);
}

/**
 * Check if user can edit entry (based on ownership)
 *
 * @param array $entry Entry data with user_id
 * @param int|null $currentUserId Current user ID
 * @param string|null $userRole Current user role
 * @return bool
 */
function canEditEntry($entry, $currentUserId = null, $userRole = null) {
    if ($currentUserId === null) {
        $currentUserId = $_SESSION['user_id'] ?? null;
    }
    
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }
    
    if (!$currentUserId || !$userRole) {
        return false;
    }
    
    // Administrator can edit all entries
    if (hasPermission('entry_edit_all', $userRole)) {
        return true;
    }
    
    // Contributors can edit their own entries
    if (hasPermission('entry_edit_own', $userRole) && $entry['user_id'] == $currentUserId) {
        return true;
    }
    
    return false;
}

/**
 * Check if user can delete entry (based on ownership)
 *
 * @param array $entry Entry data with user_id
 * @param int|null $currentUserId Current user ID
 * @param string|null $userRole Current user role
 * @return bool
 */
function canDeleteEntry($entry, $currentUserId = null, $userRole = null) {
    if ($currentUserId === null) {
        $currentUserId = $_SESSION['user_id'] ?? null;
    }
    
    if ($userRole === null) {
        $userRole = $_SESSION['user_role'] ?? null;
    }
    
    if (!$currentUserId || !$userRole) {
        return false;
    }
    
    // Administrator can delete all entries
    if (hasPermission('entry_delete_all', $userRole)) {
        return true;
    }
    
    // Contributors can delete their own entries
    if (hasPermission('entry_delete_own', $userRole) && $entry['user_id'] == $currentUserId) {
        return true;
    }
    
    return false;
}

/**
 * Get user role display name
 *
 * @param string $role Role key
 * @return string
 */
function getRoleDisplayName($role) {
    $roleNames = [
        ROLE_ADMINISTRATOR => 'Administrator',
        ROLE_CONTRIBUTOR => 'Contributor', 
        ROLE_VIEWER => 'Viewer'
    ];
    
    return $roleNames[$role] ?? ucfirst($role);
}

/**
 * Get available roles for user creation/editing
 *
 * @param string|null $currentUserRole Current user's role
 * @return array
 */
function getAvailableRoles($currentUserRole = null) {
    if ($currentUserRole === null) {
        $currentUserRole = $_SESSION['user_role'] ?? null;
    }
    
    $allRoles = [
        ROLE_ADMINISTRATOR => 'Administrator',
        ROLE_CONTRIBUTOR => 'Contributor',
        ROLE_VIEWER => 'Viewer'
    ];
    
    // Only administrators can create other administrators
    if ($currentUserRole !== ROLE_ADMINISTRATOR) {
        unset($allRoles[ROLE_ADMINISTRATOR]);
    }
    
    return $allRoles;
}

/**
 * Require permission or redirect/die
 *
 * @param string $permission Required permission
 * @param string $redirectUrl URL to redirect to if no permission
 * @return void
 */
function requirePermission($permission, $redirectUrl = 'index.php') {
    if (!hasPermission($permission)) {
        if (headers_sent()) {
            echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <strong>Access Denied:</strong> You do not have permission to perform this action.
                  </div>';
            exit();
        } else {
            header("Location: $redirectUrl");
            exit();
        }
    }
}

/**
 * Get role badge HTML
 *
 * @param string $role User role
 * @return string HTML for role badge
 */
function getRoleBadge($role) {
    $badges = [
        ROLE_ADMINISTRATOR => '<span class="px-2 py-0.5 text-[10px] rounded-full font-medium bg-red-100 text-red-800">Administrator</span>',
        ROLE_CONTRIBUTOR => '<span class="px-2 py-0.5 text-[10px] rounded-full font-medium bg-blue-100 text-blue-800">Contributor</span>',
        ROLE_VIEWER => '<span class="px-2 py-0.5 text-[10px] rounded-full font-medium bg-gray-100 text-gray-800">Viewer</span>'
    ];
    
    return $badges[$role] ?? '<span class="px-2 py-0.5 text-[10px] rounded-full font-medium bg-gray-100 text-gray-800">' . ucfirst($role) . '</span>';
}
?>