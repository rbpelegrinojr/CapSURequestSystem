<?php
require_once __DIR__ . '/config.php';

function get_db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            die('<div style="font-family:sans-serif;padding:20px;color:red;"><h2>Database Connection Error</h2><p>' . htmlspecialchars($e->getMessage()) . '</p><p>Please check your database configuration in <code>includes/config.php</code></p></div>');
        }
    }
    return $pdo;
}
