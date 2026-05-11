<?php
session_start();
require_once __DIR__ . '/db.php';

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        $base = get_admin_base_url();
        header('Location: ' . $base . '/index');
        exit;
    }
}

function get_admin_base_url() {
    $script = $_SERVER['SCRIPT_NAME'];
    $dir = dirname($script);
    if (basename($dir) === 'admin') {
        return rtrim($dir, '/');
    }
    return rtrim($dir, '/') . '/admin';
}

function admin_login($username, $password) {
    $db = get_db();
    $stmt = $db->prepare('SELECT * FROM admins WHERE username = ?');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']    = $admin['id'];
        $_SESSION['admin_name']  = $admin['name'];
        $_SESSION['admin_email'] = $admin['email'];
        return true;
    }
    return false;
}

function admin_logout() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: index');
    exit;
}
