<?php
class FormController {
    private PDO $db;
    private bool $requireAuth;

    public function __construct(bool $requireAuth = true) {
        $this->db = getDB();
        $this->requireAuth = $requireAuth;
    }

    private function auth(): array {
        return JWT::requireAuth();
    }

    public function index(): void {
        try{
            $payload = $this->auth();
        $stmt = $this->db->prepare("
            SELECT f.*, a.username as created_by_name,
                   (SELECT COUNT(*) FROM fields WHERE form_id = f.id) as field_count,
                   (SELECT COUNT(*) FROM submissions WHERE form_id = f.id) as submission_count
            FROM forms f
            JOIN admins a ON a.id = f.created_by
            WHERE f.created_by = ?
            ORDER BY f.created_at DESC
        ");
        $stmt->execute([$payload['sub']]);
        Security::jsonResponse($stmt->fetchAll());

        }catch(Exception $e){
            Security::jsonResponse(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
        
    }

    public function show(string $id): void {
        $this->auth();
        $form = $this->getForm($id);
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);
        $form['fields'] = $this->getFields($form['id']);
        Security::jsonResponse($form);
    }

    public function publicShow(string $uuid): void {
        $stmt = $this->db->prepare("SELECT * FROM forms WHERE uuid = ? AND is_active = 1");
        $stmt->execute([$uuid]);
        $form = $stmt->fetch();
        if (!$form) Security::jsonResponse(['error' => 'Form not found or inactive'], 404);
        $form['fields'] = $this->getFields($form['id']);
        Security::jsonResponse($form);
    }

    public function create(): void {
        $payload = $this->auth();
        $body = json_decode(file_get_contents('php://input'), true);

        $name = Security::sanitize($body['name'] ?? '');
        $desc = Security::sanitize($body['description'] ?? '');

        if (!$name) Security::jsonResponse(['error' => 'Form name is required'], 400);

        $uuid = $this->generateUuid();
        $stmt = $this->db->prepare("INSERT INTO forms (uuid, name, description, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$uuid, $name, $desc, $payload['sub']]);
        $id = $this->db->lastInsertId();

        $form = $this->getFormById($id);
        $form['fields'] = [];
        Security::jsonResponse($form, 201);
    }

    public function update(string $id): void {
        $payload = $this->auth();
        $form = $this->getForm($id);
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);
        if ($form['created_by'] != $payload['sub']) Security::jsonResponse(['error' => 'Forbidden'], 403);

        $body = json_decode(file_get_contents('php://input'), true);
        $name      = Security::sanitize($body['name'] ?? $form['name']);
        $desc      = Security::sanitize($body['description'] ?? $form['description']);
        $is_active = isset($body['is_active']) ? (int)(bool)$body['is_active'] : $form['is_active'];

        $stmt = $this->db->prepare("UPDATE forms SET name=?, description=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $desc, $is_active, $form['id']]);

        $updated = $this->getFormById($form['id']);
        $updated['fields'] = $this->getFields($form['id']);
        Security::jsonResponse($updated);
    }

    public function delete(string $id): void {
        $payload = $this->auth();
        $form = $this->getForm($id);
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);
        if ($form['created_by'] != $payload['sub']) Security::jsonResponse(['error' => 'Forbidden'], 403);

        $stmt = $this->db->prepare("DELETE FROM forms WHERE id=?");
        $stmt->execute([$form['id']]);
        Security::jsonResponse(['message' => 'Form deleted']);
    }

    private function getForm(string $id): array|false {
        // Accept UUID or numeric ID
        if (is_numeric($id)) {
            $stmt = $this->db->prepare("SELECT * FROM forms WHERE id=?");
        } else {
            $stmt = $this->db->prepare("SELECT * FROM forms WHERE uuid=?");
        }
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function getFormById(int $id): array|false {
        $stmt = $this->db->prepare("SELECT * FROM forms WHERE id=?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    private function getFields(int $formId): array {
        $stmt = $this->db->prepare("SELECT * FROM fields WHERE form_id=? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$formId]);
        $fields = $stmt->fetchAll();
        foreach ($fields as &$f) {
            $f['options'] = $f['options'] ? json_decode($f['options'], true) : [];
            $f['validation_rules'] = $f['validation_rules'] ? json_decode($f['validation_rules'], true) : [];
        }
        return $fields;
    }

    private function generateUuid(): string {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}