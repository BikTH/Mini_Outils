<?php
// app/core/auth.php
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/../services/user_service.php';

// session is started in public entrypoint; avoid double session_start here

function login(string $username, string $password): bool
{
    $user = getUserByUsername($username);
    if (!$user) return false;
    if (!password_verify($password, $user['password_hash'])) return false;
    // store minimal user info in session
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'display_name' => $user['display_name'] ?? null
    ];
    return true;
}

function logout(): void
{
    unset($_SESSION['user']);
}

function isAuthenticated(): bool
{
    return !empty($_SESSION['user']['id']);
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function userHasRole(string $roleName): bool
{
    if (!isAuthenticated()) return false;
    $user = currentUser();
    $roles = getUserRoles((int)$user['id']);
    return in_array($roleName, $roles, true);
}
