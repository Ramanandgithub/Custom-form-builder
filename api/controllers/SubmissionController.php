<?php
class SubmissionController {
    private PDO $db;
    private string $formId;

    public function __construct(string $formId) {
        $this->db     = getDB();
        $this->formId = $formId;
    }

    private function getFormByUuidOrId(): array|false {
        $stmt = $this->db->prepare(
            is_numeric($this->formId)
                ? "SELECT * FROM forms WHERE id=?"
                : "SELECT * FROM forms WHERE uuid=?"
        );
        $stmt->execute([$this->formId]);
        return $stmt->fetch();
    }

    public function index(): void {
        JWT::requireAuth();
        $form = $this->getFormByUuidOrId();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);

        $stmt = $this->db->prepare("
            SELECT s.*, 
                   GROUP_CONCAT(CONCAT(f.label, '||', COALESCE(sv.value,'')) ORDER BY f.sort_order SEPARATOR ';;') as values_raw
            FROM submissions s
            LEFT JOIN submission_values sv ON sv.submission_id = s.id
            LEFT JOIN fields f ON f.id = sv.field_id
            WHERE s.form_id = ?
            GROUP BY s.id
            ORDER BY s.submitted_at DESC
        ");
        $stmt->execute([$form['id']]);
        $rows = $stmt->fetchAll();

        $submissions = array_map(function($row) {
            $values = [];
            if ($row['values_raw']) {
                foreach (explode(';;', $row['values_raw']) as $pair) {
                    [$label, $val] = array_pad(explode('||', $pair, 2), 2, '');
                    $values[$label] = $val;
                }
            }
            return [
                'id'           => $row['id'],
                'submitted_at' => $row['submitted_at'],
                'ip_address'   => $row['ip_address'],
                'values'       => $values,
            ];
        }, $rows);

        Security::jsonResponse($submissions);
    }

    public function submit(): void {
        $form = $this->getFormByUuidOrId();
        if (!$form || !$form['is_active']) {
            Security::jsonResponse(['error' => 'Form not found or inactive'], 404);
        }

        // Get fields
        $stmt = $this->db->prepare("SELECT * FROM fields WHERE form_id=? ORDER BY sort_order ASC");
        $stmt->execute([$form['id']]);
        $fields = $stmt->fetchAll();

        $errors = [];
        $values = [];

        // Handle multipart or JSON
        $isMultipart = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'multipart');
        $body = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?? []);

        foreach ($fields as $field) {
            $key = 'field_' . $field['id'];
            $raw = $body[$key] ?? null;

            if ($field['field_type'] === 'file') {
                if (isset($_FILES[$key]) && $_FILES[$key]['error'] !== UPLOAD_ERR_NO_FILE) {
                    $fileErrors = Security::validateFileUpload($_FILES[$key]);
                    if ($fileErrors) {
                        $errors[$field['label']] = $fileErrors;
                        continue;
                    }
                    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
                    $ext = pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                    $filename = bin2hex(random_bytes(16)) . '.' . strtolower($ext);
                    move_uploaded_file($_FILES[$key]['tmp_name'], UPLOAD_DIR . $filename);
                    $values[$field['id']] = $filename;
                } elseif ($field['is_required']) {
                    $errors[$field['label']] = ['This field is required'];
                }
                continue;
            }

            if ($field['field_type'] === 'checkbox' && is_array($raw)) {
                $raw = implode(', ', array_map([Security::class, 'sanitize'], $raw));
            } else {
                $raw = Security::sanitize($raw ?? '');
            }

            // Validation
            if ($field['is_required'] && !Security::validateRequired($raw)) {
                $errors[$field['label']] = ['This field is required'];
                continue;
            }
            if ($raw !== '') {
                if ($field['field_type'] === 'email' && !Security::validateEmail($raw)) {
                    $errors[$field['label']] = ['Invalid email address'];
                }
                if ($field['field_type'] === 'number') {
                    $rules = $field['validation_rules'] ? json_decode($field['validation_rules'], true) : [];
                    $min = isset($rules['min']) ? (float)$rules['min'] : null;
                    $max = isset($rules['max']) ? (float)$rules['max'] : null;
                    if (!Security::validateNumber($raw, $min, $max)) {
                        $errors[$field['label']] = ['Invalid number' . ($min !== null ? " (min: $min)" : '') . ($max !== null ? " (max: $max)" : '')];
                    }
                }
            }
            $values[$field['id']] = $raw;
        }

        if ($errors) Security::jsonResponse(['errors' => $errors], 422);

        $this->db->beginTransaction();
        try {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = Security::sanitize($_SERVER['HTTP_USER_AGENT'] ?? '');
            $stmt = $this->db->prepare("INSERT INTO submissions (form_id, ip_address, user_agent) VALUES (?, ?, ?)");
            $stmt->execute([$form['id'], $ip, $ua]);
            $submissionId = $this->db->lastInsertId();

            $stmt = $this->db->prepare("INSERT INTO submission_values (submission_id, field_id, value) VALUES (?, ?, ?)");
            foreach ($values as $fieldId => $value) {
                $stmt->execute([$submissionId, $fieldId, $value]);
            }
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            Security::jsonResponse(['error' => 'Submission failed'], 500);
        }

        Security::jsonResponse(['message' => 'Form submitted successfully', 'id' => $submissionId], 201);
    }

    public function export(): void {
        JWT::requireAuth();
        $form = $this->getFormByUuidOrId();
        if (!$form) Security::jsonResponse(['error' => 'Form not found'], 404);

        $stmt = $this->db->prepare("SELECT * FROM fields WHERE form_id=? ORDER BY sort_order ASC");
        $stmt->execute([$form['id']]);
        $fields = $stmt->fetchAll();

        $stmt = $this->db->prepare("SELECT * FROM submissions WHERE form_id=? ORDER BY submitted_at DESC");
        $stmt->execute([$form['id']]);
        $submissions = $stmt->fetchAll();

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="submissions_' . $form['uuid'] . '.csv"');
        $out = fopen('php://output', 'w');

        // Header row
        $headers = ['Submission ID', 'Submitted At', 'IP Address'];
        foreach ($fields as $f) $headers[] = $f['label'];
        fputcsv($out, $headers);

        // Data rows
        $valStmt = $this->db->prepare("SELECT field_id, value FROM submission_values WHERE submission_id=?");
        foreach ($submissions as $sub) {
            $valStmt->execute([$sub['id']]);
            $vals = array_column($valStmt->fetchAll(), 'value', 'field_id');
            $row = [$sub['id'], $sub['submitted_at'], $sub['ip_address']];
            foreach ($fields as $f) $row[] = $vals[$f['id']] ?? '';
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}