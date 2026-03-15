<?php
// Use 127.0.0.1 to force TCP (avoids local socket path issues like /run/mysqld/mysqld.sock).
define('DB_HOST', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'form_builder');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_general_ci');

define('JWT_SECRET', 'ELKLDKJFSFUJSIFJKSNFMSNIFHUSHFJFKOSFNYETYUJOKFMKNFNF');
define('JWT_EXPIRY', 3600); // 1 hour

define('BASE_URL', 'http://localhost:8000');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

// Set to true when troubleshooting locally so you can see why DB connections fail.
define('APP_DEBUG', true);

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            $payload = ['error' => 'Database connection failed'];
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $payload['details'] = $e->getMessage();
            }
            die(json_encode($payload));
        }
    }
    return $pdo;
}