<?php
class FieldController {
    private PDO $db;
    private string $formId;

    public function __construct(string $formId) {
        $this->db     = getDB();
        $this->formId = $formId;
    }

    private function getForm(): array|false {
        $p = JWT::requireAuth();
        $stmt = $this->db->prepare(
            is_numeric($this->formId)
                ? "SELECT * FROM forms WHERE id=? AND created_by=?"
                : "SELECT * FROM forms WHERE uuid=? AND created_by=?"
        );
        $stmt->execute([$this->formId, $p['sub']]);
        return $stmt->fetch();
    }

    public function index(): void {
        $form = $this->getForm();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);
        $stmt = $this->db->prepare("SELECT * FROM fields WHERE form_id=? ORDER BY sort_order ASC, id ASC");
        $stmt->execute([$form['id']]);
        $fields = $stmt->fetchAll();
        foreach ($fields as &$f) {
            $f['options'] = $f['options'] ? json_decode($f['options'], true) : [];
            $f['validation_rules'] = $f['validation_rules'] ? json_decode($f['validation_rules'], true) : [];
        }
        Security::jsonResponse($fields);
    }

    public function create(): void {
        $form = $this->getForm();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);

        $body = json_decode(file_get_contents('php://input'), true);
        $errors = $this->validate($body);
        if ($errors) Security::jsonResponse(['errors' => $errors], 400);

        $validTypes = ['text','email','number','textarea','dropdown','radio','checkbox','file'];
        $type        = in_array($body['field_type'], $validTypes) ? $body['field_type'] : 'text';
        $label       = Security::sanitize($body['label']);
        $placeholder = Security::sanitize($body['placeholder'] ?? '');
        $required    = (int)(bool)($body['is_required'] ?? false);
        $options     = in_array($type, ['dropdown','radio','checkbox']) ? json_encode($body['options'] ?? []) : null;
        $rules       = json_encode($body['validation_rules'] ?? []);

        // Get next sort order
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM fields WHERE form_id=?");
        $stmt->execute([$form['id']]);
        $sortOrder = (int)$stmt->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO fields (form_id, field_type, label, placeholder, is_required, sort_order, options, validation_rules)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$form['id'], $type, $label, $placeholder, $required, $sortOrder, $options, $rules]);
        $id = $this->db->lastInsertId();

        $stmt = $this->db->prepare("SELECT * FROM fields WHERE id=?");
        $stmt->execute([$id]);
        $field = $stmt->fetch();
        $field['options'] = $field['options'] ? json_decode($field['options'], true) : [];
        $field['validation_rules'] = $field['validation_rules'] ? json_decode($field['validation_rules'], true) : [];
        Security::jsonResponse($field, 201);
    }

    public function update(string $fieldId): void {
        $form = $this->getForm();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);

        $stmt = $this->db->prepare("SELECT * FROM fields WHERE id=? AND form_id=?");
        $stmt->execute([$fieldId, $form['id']]);
        $field = $stmt->fetch();
        if (!$field) Security::jsonResponse(['error' => 'Field not found'], 404);

        $body = json_decode(file_get_contents('php://input'), true);
        $validTypes  = ['text','email','number','textarea','dropdown','radio','checkbox','file'];
        $type        = in_array($body['field_type'] ?? '', $validTypes) ? $body['field_type'] : $field['field_type'];
        $label       = Security::sanitize($body['label'] ?? $field['label']);
        $placeholder = Security::sanitize($body['placeholder'] ?? $field['placeholder']);
        $required    = isset($body['is_required']) ? (int)(bool)$body['is_required'] : $field['is_required'];
        $options     = in_array($type, ['dropdown','radio','checkbox']) ? json_encode($body['options'] ?? []) : null;
        $rules       = json_encode($body['validation_rules'] ?? []);

        $stmt = $this->db->prepare("
            UPDATE fields SET field_type=?, label=?, placeholder=?, is_required=?, options=?, validation_rules=?
            WHERE id=?
        ");
        $stmt->execute([$type, $label, $placeholder, $required, $options, $rules, $field['id']]);

        $stmt = $this->db->prepare("SELECT * FROM fields WHERE id=?");
        $stmt->execute([$field['id']]);
        $updated = $stmt->fetch();
        $updated['options'] = $updated['options'] ? json_decode($updated['options'], true) : [];
        $updated['validation_rules'] = $updated['validation_rules'] ? json_decode($updated['validation_rules'], true) : [];
        Security::jsonResponse($updated);
    }

    public function delete(string $fieldId): void {
        $form = $this->getForm();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);

        $stmt = $this->db->prepare("DELETE FROM fields WHERE id=? AND form_id=?");
        $stmt->execute([$fieldId, $form['id']]);
        if (!$stmt->rowCount()) Security::jsonResponse(['error' => 'Field not found'], 404);
        Security::jsonResponse(['message' => 'Field deleted']);
    }

    public function reorder(): void {
        $form = $this->getForm();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);

        $body = json_decode(file_get_contents('php://input'), true);
        $order = $body['order'] ?? []; // array of field IDs in new order

        $stmt = $this->db->prepare("UPDATE fields SET sort_order=? WHERE id=? AND form_id=?");
        foreach ($order as $index => $fieldId) {
            $stmt->execute([$index, $fieldId, $form['id']]);
        }
        Security::jsonResponse(['message' => 'Fields reordered']);
    }

    private function validate(array $body): array {
        $errors = [];
        if (empty($body['field_type'])) $errors[] = 'field_type is required';
        if (empty($body['label']))      $errors[] = 'label is required';
        $choiceTypes = ['dropdown','radio','checkbox'];
        if (in_array($body['field_type'] ?? '', $choiceTypes) && empty($body['options'])) {
            $errors[] = 'options are required for ' . $body['field_type'];
        }
        return $errors;
    }
}