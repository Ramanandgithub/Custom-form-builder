<?php
class AuthController {
    private PDO $db;

    public function __construct() {
        $this->db = getDB();
    }

    public function login(): void {
        $body = json_decode(file_get_contents('php://input'), true);
        $username = Security::sanitize($body['username'] ?? '');
        $password = $body['password'] ?? '';
        // echo "Login attempt for username/email: $username\n";
        if (!$username || !$password) {
            Security::jsonResponse(['error' => 'Username and password required'], 400);
        }

        $stmt = $this->db->prepare("SELECT id, username, email, password FROM admins WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$username, $username]);
        $admin = $stmt->fetch();

        if (!$admin || !Security::verifyPassword($password, $admin['password'])) {
            Security::jsonResponse(['error' => 'Invalid credentials'], 401);
        }

        $token = JWT::encode([
            'sub'      => $admin['id'],
            'username' => $admin['username'],
            'email'    => $admin['email'],
        ]);

        Security::jsonResponse([
            'token' => $token,
            'admin' => [
                'id'       => $admin['id'],
                'username' => $admin['username'],
                'email'    => $admin['email'],
            ]
        ]);
    }

    public function me(): void {
        $payload = JWT::requireAuth();
        $stmt = $this->db->prepare("SELECT id, username, email, created_at FROM admins WHERE id = ?");
        $stmt->execute([$payload['sub']]);
        $admin = $stmt->fetch();
        if (!$admin) Security::jsonResponse(['error' => 'Not found'], 404);
        Security::jsonResponse($admin);
    }
}