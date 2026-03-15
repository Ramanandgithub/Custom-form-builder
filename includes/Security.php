<?php
class Security {
    public static function sanitize(mixed $input): mixed {
        if (is_array($input)) {
            return array_map([self::class, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim((string)$input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function sanitizeEmail(string $email): string {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
    }

    public static function validateEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateRequired(mixed $value): bool {
        return isset($value) && trim((string)$value) !== '';
    }

    public static function validateNumber(mixed $value, ?float $min = null, ?float $max = null): bool {
        if (!is_numeric($value)) return false;
        $num = (float)$value;
        if ($min !== null && $num < $min) return false;
        if ($max !== null && $num > $max) return false;
        return true;
    }

    public static function generateCsrf(): string {
        if (session_status() === PHP_SESSION_NONE) session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(string $token): bool {
        if (session_status() === PHP_SESSION_NONE) session_start();
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function jsonResponse(mixed $data, int $code = 200): never {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function validateFileUpload(array $file): array {
        $errors = [];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'File upload error: ' . $file['error'];
            return $errors;
        }
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = 'File size exceeds limit (5MB)';
        }
        $allowed = ['image/jpeg','image/png','image/gif','application/pdf','text/plain',
                    'application/msword','application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowed)) {
            $errors[] = 'File type not allowed';
        }
        return $errors;
    }
}