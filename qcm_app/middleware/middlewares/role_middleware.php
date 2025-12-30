<?php
// middleware/middlewares/role_middleware.php
require_once __DIR__ . '/../../app/core/auth.php';

function require_role(string $role): void
{
    if (!isAuthenticated() || !userHasRole($role)) {
        // forbidden
        http_response_code(403);
        echo "<h1>403 — Accès refusé</h1><p>Vous n'avez pas les droits requis pour accéder à cette page.</p>";
        exit;
    }
}
