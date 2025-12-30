<?php
// middleware/middlewares/auth_middleware.php
require_once __DIR__ . '/../../app/core/auth.php';

function require_auth(): void
{
    if (!isAuthenticated()) {
        // redirect to login
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/') . '/?action=login');
        exit;
    }
}
