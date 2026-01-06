<?php
// qcm_app/app/services/auth_service.php
// Centralized authentication helpers (pure logic, no session_start here)

require_once __DIR__ . '/user_service.php';

function authenticate(string $username, string $password)
{
    $u = getUserByUsername($username);
    if (!$u) return false;
    if (!password_verify($password, $u['password_hash'])) return false;
    // return minimal public user info
    return [
        'id' => (int)$u['id'],
        'username' => $u['username'],
        'display_name' => $u['display_name'] ?? null,
    ];
}

function logout()
{
    // No session operations here; middleware will clear session.
    return true;
}

function currentUser($sessionUser = null)
{
    // Accept session-stored user array (as stored by middleware)
    if (!$sessionUser || empty($sessionUser['id'])) return null;
    // Enrich user with roles if possible
    $uid = (int)$sessionUser['id'];
    $roles = [];
    if (function_exists('getUserRoles')) {
        try {
            $roles = getUserRoles($uid);
        } catch (Throwable $t) {
            $roles = [];
        }
    }
    return [
        'id' => $uid,
        'username' => $sessionUser['username'] ?? null,
        'display_name' => $sessionUser['display_name'] ?? null,
        'roles' => $roles
    ];
}
