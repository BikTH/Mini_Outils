<?php
// qcm_app/middleware/index.php
// Minimal front controller for API routes. No framework, plain PHP.

// Standard JSON response helpers
function send_json($data, int $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => $status >= 200 && $status < 300 ? 'ok' : 'error', 'data' => $data]);
    exit;
}

function send_error(string $message, int $status = 400, array $extra = null) {
    $payload = ['message' => $message];
    if ($extra && is_array($extra)) {
        $payload = array_merge($payload, $extra);
    }
    send_json($payload, $status);
}

// Basic router
// HTTP method and normalized path
$method = $_SERVER['REQUEST_METHOD'];

// Normalize the request path so that calls like
// /qcm/middleware/index.php/api/exams become /api/exams
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$uriPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

// If the SCRIPT_NAME (e.g. /qcm/middleware/index.php) is present at the
// start of the request URI, remove it. Otherwise try removing the script
// directory (dirname). Fallback to the raw URI path.
$path = $uriPath;
if ($scriptName !== '' && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
} else {
    $scriptDir = rtrim(dirname($scriptName), '/');
    if ($scriptDir !== '' && strpos($path, $scriptDir) === 0) {
        $path = substr($path, strlen($scriptDir));
    }
}

// Ensure we have a leading slash and clean up
$path = '/' . ltrim($path, '/');
if ($path === '') $path = '/';

// Only API under /api/
if (!(strpos($path, '/api/') === 0 || $path === '/api' || $path === '/api/')) {
    send_error('Not Found', 404, ['path' => $path]);
}

$parts = explode('/', trim($path, '/'));
// parts[0] == 'api', parts[1] == resource...

try {
    // route: /api/exams
    if ($method === 'GET' && isset($parts[1]) && $parts[1] === 'exams') {
        require_once __DIR__ . '/routes/exams.php';
        handle_get_exams();
    }

    // route: /api/me
    if ($method === 'GET' && isset($parts[1]) && $parts[1] === 'me') {
        require_once __DIR__ . '/routes/auth.php';
        handle_get_me();
    }

    // route: POST /api/login
    if ($method === 'POST' && isset($parts[1]) && $parts[1] === 'login') {
        require_once __DIR__ . '/routes/auth.php';
        handle_post_login();
    }

    // route: POST /api/logout
    if ($method === 'POST' && isset($parts[1]) && $parts[1] === 'logout') {
        require_once __DIR__ . '/routes/auth.php';
        handle_post_logout();
    }

    // route: /api/admin-challenges/{id}/leaderboard
    if ($method === 'GET' && isset($parts[1]) && $parts[1] === 'admin-challenges' && isset($parts[2]) && isset($parts[3]) && $parts[3] === 'leaderboard') {
        $id = (int)$parts[2];
        require_once __DIR__ . '/routes/admin_challenges.php';
        handle_get_leaderboard($id);
    }

    send_error('API endpoint not found', 404, ['path' => $path]);
} catch (Throwable $e) {
    // Keep errors minimal but return JSON for debugging in local/dev
    send_json(['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine(), 'path' => $path], 500);
}
