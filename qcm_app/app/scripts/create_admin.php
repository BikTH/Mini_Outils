<?php
// CLI script to create an admin user securely.
// Usage: php create_admin.php username password
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../services/user_service.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

$argv = $_SERVER['argv'];
if (count($argv) < 3) {
    echo "Usage: php create_admin.php <username> <password>\n";
    exit(1);
}

$username = $argv[1];
$password = $argv[2];

$existing = getUserByUsername($username);
if ($existing) {
    echo "User $username already exists.\n";
    exit(1);
}

$id = createUser($username, $password, $username);
assignRole($id, 'admin');
echo "Admin user created: id={$id}, username={$username}\n";
