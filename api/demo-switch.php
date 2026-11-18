<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Support/web.php';
require_once dirname(__DIR__) . '/app/Support/DemoAccountService.php';

use App\Support\DemoAccountService;

sfc_start_session();

$requestedRole = (string) ($_REQUEST['role'] ?? 'guest');
$roleKey = DemoAccountService::normalizeRoleKey($requestedRole);
$requestedRedirect = trim((string) ($_REQUEST['redirect'] ?? ''));

$roleWorkspaces = [
    'investor' => 'investor-dashboard.php',
    'broker' => 'seller-dashboard.php',
    'admin' => 'admin-dashboard.php',
    'guest' => 'index.php',
];

if ($roleKey === 'guest') {
    sfc_logout();
    sfc_start_session();
    sfc_csrf_token();
    $activeUser = null;
} else {
    $pdo = sfc_app_container()['pdo'];
    $user = DemoAccountService::ensureAccountForRole($pdo, $roleKey);
    if ($user === null) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Unable to provision demo account for role: ' . $roleKey]);
        exit;
    }

    session_regenerate_id(true);
    unset($_SESSION['sfc_csrf_token'], $_SESSION['sfc_login_errors']);
    $_SESSION['sfc_user'] = sfc_user_session_payload($user);
    $_SESSION['sfc_authenticated_at'] = time();
    $_SESSION['sfc_last_activity_at'] = time();
    $_SESSION['sfc_last_db_touch_at'] = time();
    $_SESSION['sfc_welcome_mode'] = 'returning';
    try {
        sfc_user_repository()->touchActivity((int) $user['id'], true);
    } catch (Throwable) {
    }
    sfc_csrf_token();
    $activeUser = $_SESSION['sfc_user'];
}

if (strtolower((string) ($_REQUEST['format'] ?? '')) === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'roleKey' => $roleKey,
        'user' => $activeUser,
        'workspace' => $roleWorkspaces[$roleKey] ?? 'index.php',
    ]);
    exit;
}

// Determine safe, role-compatible redirect target
$defaultTarget = $roleWorkspaces[$roleKey] ?? 'index.php';
$target = $defaultTarget;

if ($requestedRedirect !== '' && !preg_match('#^(?:https?:)?//#i', $requestedRedirect)) {
    $cleanRedirect = ltrim($requestedRedirect, '/');
    $basePrefix = trim((string) (sfc_web_context()['basePath'] ?? ''), '/');
    if ($basePrefix !== '' && str_starts_with($cleanRedirect, $basePrefix . '/')) {
        $cleanRedirect = ltrim(substr($cleanRedirect, strlen($basePrefix) + 1), '/');
    }
    $pathPart = strtolower((string) parse_url($cleanRedirect, PHP_URL_PATH));
    $basePage = basename($pathPart);

    $roleLockedPages = [
        'investor-dashboard.php' => 'investor',
        'seller-dashboard.php' => 'broker',
        'admin-dashboard.php' => 'admin',
        'profile-settings.php' => $roleKey === 'guest' ? 'authenticated' : $roleKey,
    ];
    $loginPages = ['investor-login.php', 'seller-login.php', 'admin-login.php'];

    if (in_array($basePage, $loginPages, true)) {
        // Switching from a login page always jumps straight into the role's workspace
        $target = $defaultTarget;
    } elseif (isset($roleLockedPages[$basePage]) && $roleLockedPages[$basePage] !== $roleKey) {
        // Switching from another role's private dashboard jumps to the new role's workspace
        $target = $defaultTarget;
    } else {
        $target = $cleanRedirect;
    }
}

header('Location: ' . sfc_path('/' . ltrim($target, '/')));
exit;
