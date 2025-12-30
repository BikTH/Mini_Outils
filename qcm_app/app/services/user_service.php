<?php
// app/services/user_service.php
require_once __DIR__ . '/../core/database.php';

function createUser(string $username, string $passwordPlain, ?string $displayName = null): int
{
    $pdo = getPDO();
    $hash = password_hash($passwordPlain, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, display_name) VALUES (:username, :hash, :display)");
    $stmt->execute([':username' => $username, ':hash' => $hash, ':display' => $displayName]);
    return (int)$pdo->lastInsertId();
}

function getUserByUsername(string $username): ?array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
    $stmt->execute([':username' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function listUsers(): array
{
    $pdo = getPDO();
    $stmt = $pdo->query("SELECT id, username, display_name, created_at FROM users ORDER BY created_at DESC");
    return $stmt->fetchAll();
}

function assignRole(int $userId, string $roleName): bool
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id FROM roles WHERE name = :name");
    $stmt->execute([':name' => $roleName]);
    $role = $stmt->fetch();
    if (!$role) return false;
    $roleId = (int)$role['id'];

    $stmt2 = $pdo->prepare("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
    return $stmt2->execute([':uid' => $userId, ':rid' => $roleId]);
}

function getUserRoles(int $userId): array
{
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT r.name FROM roles r JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = :uid");
    $stmt->execute([':uid' => $userId]);
    return array_column($stmt->fetchAll(), 'name');
}
