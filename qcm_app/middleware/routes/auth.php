<?php
// qcm_app/middleware/routes/auth.php
// Handlers for /api/me, /api/login, /api/logout

require_once __DIR__ . '/../../app/services/auth_service.php';

function handle_get_me() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $resp = ['authenticated' => false];
    if (!empty($_SESSION['user']['id'])) {
        $u = currentUser($_SESSION['user']);
        if ($u) {
            $resp = ['authenticated' => true, 'user' => $u];
        }
    }
    send_json($resp);
}

function handle_post_login() {
    // Accept JSON or form data
    $input = file_get_contents('php://input');
    $data = [];
    if ($input) {
        $decoded = json_decode($input, true);
        if (is_array($decoded)) $data = $decoded;
    }
    // Fallback to $_POST
    $username = $data['username'] ?? $_POST['username'] ?? null;
    $password = $data['password'] ?? $_POST['password'] ?? null;
    if (!$username || !$password) {
        send_error('missing_credentials', 400);
    }

    $user = authenticate($username, $password);
    if (!$user) {
        send_error('invalid_credentials', 401);
    }

    // Manage session here (middleware is authoritative)
    if (session_status() === PHP_SESSION_NONE) session_start();
    $_SESSION['user'] = $user; // minimal user stored

    send_json(['authenticated' => true, 'user' => currentUser($_SESSION['user'])]);
}

function handle_post_logout() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // clear session user
    unset($_SESSION['user']);
    // Optionally destroy session cookie without destroying other session data
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']
        );
    }
    // keep session id but clear user; send confirmation
    send_json(['authenticated' => false]);
}
