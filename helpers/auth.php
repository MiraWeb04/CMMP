<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/http.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = request_is_https();

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('CMMP_SESSION');
    session_start();
}

function is_logged_in(): bool
{
    start_secure_session();

    return isset($_SESSION['user_id']) && is_int($_SESSION['user_id']);
}

function authenticated_user(): ?array
{
    if (!is_logged_in()) {
        return null;
    }

    $statement = database()->prepare(
        'SELECT id, username, email, status FROM users WHERE id = :id LIMIT 1'
    );
    $statement->execute(['id' => $_SESSION['user_id']]);
    $user = $statement->fetch();

    if (!$user || $user['status'] !== 'ACTIVE') {
        logout_user();
        return null;
    }

    return $user;
}

function current_user_roles(): array
{
    if (!is_logged_in()) {
        return [];
    }

    $statement = database()->prepare(
        'SELECT r.code
         FROM roles r
         INNER JOIN user_roles ur ON ur.role_id = r.id
         WHERE ur.user_id = :user_id
         ORDER BY r.code'
    );
    $statement->execute(['user_id' => $_SESSION['user_id']]);

    return array_column($statement->fetchAll(), 'code');
}

function authenticate_user(string $identifier, string $password): ?array
{
    require_once __DIR__ . '/../models/User.php';
    $ip = request_client_ip();
    if (User::isTemporarilyLocked($identifier, $ip)) {
        return ['status' => 'LOCKED_TEMPORARY'];
    }

    $statement = database()->prepare(
        'SELECT u.id, u.username, u.email, u.password_hash, u.status,
                GROUP_CONCAT(r.code ORDER BY r.code SEPARATOR ",") AS role_codes
         FROM users u
         LEFT JOIN user_roles ur ON ur.user_id = u.id
         LEFT JOIN roles r ON r.id = ur.role_id
         WHERE u.username = :username OR u.email = :email
         GROUP BY u.id
         LIMIT 1'
    );
    $statement->execute([
        'username' => $identifier,
        'email' => $identifier,
    ]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        User::recordLoginAttempt($identifier, $ip, false);
        return null;
    }

    if ($user['status'] !== 'ACTIVE') {
        User::recordLoginAttempt($identifier, $ip, false);
        return ['status' => $user['status']];
    }

    User::recordLoginAttempt($identifier, $ip, true);

    $user['roles'] = $user['role_codes'] === null || $user['role_codes'] === ''
        ? []
        : explode(',', $user['role_codes']);

    return $user;
}

function has_role(string|array $roles): bool
{
    $requiredRoles = (array) $roles;

    return array_intersect($requiredRoles, current_user_roles()) !== [];
}

function has_permission(string $permission): bool
{
    $permission = trim($permission);
    if ($permission === '') {
        return false;
    }

    $roles = current_user_roles();
    if ($roles === []) {
        return false;
    }

    $allowedRoles = CMMP_PERMISSION_MATRIX[$permission] ?? null;
    if (!is_array($allowedRoles)) {
        // Preserve the existing RBAC contract: if a permission is unknown,
        // deny access rather than opening a hole.
        return false;
    }

    return array_intersect($allowedRoles, $roles) !== [];
}

function require_permission(string $permission): void
{
    require_login();

    if (!has_permission($permission)) {
        http_response_code(403);
        exit('You are not authorized to access this resource.');
    }
}

function require_login(): void
{
    if (!authenticated_user()) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '/CMMP/';
        header('Location: ' . APP_BASE_URL . '/auth/login.php?redirect=' . urlencode($redirect));
        exit;
    }
}

function require_role(string|array $roles): void
{
    require_login();

    if (!has_role($roles)) {
        http_response_code(403);
        exit('You are not authorized to access this page.');
    }
}

function login_user(int $userId, array $roles): void
{
    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['roles'] = array_values($roles);
    $_SESSION['authenticated_at'] = time();
}

function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '',
            (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function role_redirect_path(array $roles): string
{
    $rolePaths = [
        'SUPER_ADMIN' => '/admin/dashboard/',
        'CHURCH_ADMIN' => '/admin/dashboard/',
        'PASTOR' => '/pastor/dashboard/',
        'COUNSELOR' => '/counselor/dashboard/',
        'MINISTRY_LEADER' => '/ministry/dashboard/',
        'STAFF' => '/admin/dashboard/',
        'MEMBER' => '/member/dashboard/',
    ];

    foreach (['SUPER_ADMIN', 'CHURCH_ADMIN', 'PASTOR', 'COUNSELOR', 'MINISTRY_LEADER', 'STAFF', 'MEMBER'] as $role) {
        if (in_array($role, $roles, true)) {
            return $rolePaths[$role];
        }
    }

    return '/auth/protected-test.php';
}

function safe_redirect_target(?string $target): string
{
    if (!$target || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
        return APP_BASE_URL . '/auth/protected-test.php';
    }

    return $target;
}

function current_user_id(): ?int
{
    return is_logged_in() ? (int) $_SESSION['user_id'] : null;
}

function role_display_label(?string $role = null): string
{
    $role = $role ?? (current_user_roles()[0] ?? '');
    return match ($role) {
        'SUPER_ADMIN' => 'Super Administrator',
        'CHURCH_ADMIN' => 'Church Administrator',
        'STAFF' => 'Church Secretary',
        'PASTOR' => 'Pastor',
        'COUNSELOR' => 'Counselor',
        'MINISTRY_LEADER' => 'Ministry Leader',
        'MEMBER' => 'Church Member',
        default => $role !== '' ? $role : 'Portal user',
    };
}

function portal_display_name(): string
{
    $user = authenticated_user();
    if (!$user) {
        return 'User';
    }
    $query = database()->prepare('SELECT first_name, last_name FROM members WHERE user_id = :id LIMIT 1');
    $query->execute(['id' => (int) $user['id']]);
    $member = $query->fetch();
    $name = trim(((string) ($member['first_name'] ?? '')) . ' ' . ((string) ($member['last_name'] ?? '')));
    return $name !== '' ? $name : (string) ($user['username'] ?? 'User');
}