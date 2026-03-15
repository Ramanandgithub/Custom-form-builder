<?php
/**
 * REST API Router
 * All API requests go through /api/index.php
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/JWT.php';
require_once __DIR__ . '/../includes/Security.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
// echo $_SERVER['REQUEST_URI'];
$method = $_SERVER['REQUEST_METHOD'];
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri    = preg_replace('#^/api#', '', $uri);
$uri    = trim($uri, '/');
$parts  = explode('/', $uri);

$resource = $parts[0] ?? '';
$id       = $parts[1] ?? null;
$sub      = $parts[2] ?? null;

// Route dispatcher
switch ($resource) {
    case 'auth':
        require __DIR__ . '/controllers/AuthController.php';
        $ctrl = new AuthController();
        if ($id === 'login' && $method === 'POST') $ctrl->login();
        elseif ($id === 'me' && $method === 'GET') $ctrl->me();
        else Security::jsonResponse(['error' => 'Not found'], 404);
        break;

    case 'forms':
        require __DIR__ . '/controllers/FormController.php';
        $ctrl = new FormController();
        if (!$id) {
            if ($method === 'GET')  $ctrl->index();
            elseif ($method === 'POST') $ctrl->create();
            else Security::jsonResponse(['error' => 'Method not allowed'], 405);
        } elseif ($sub === 'fields') {
            $fieldId = $parts[3] ?? null;
            require __DIR__ . '/controllers/FieldController.php';
            $fc = new FieldController($id);
            if (!$fieldId) {
                if ($method === 'GET')  $fc->index();
                elseif ($method === 'POST') $fc->create();
                else Security::jsonResponse(['error' => 'Method not allowed'], 405);
            } else {
                if ($method === 'PUT')    $fc->update($fieldId);
                elseif ($method === 'DELETE') $fc->delete($fieldId);
                else Security::jsonResponse(['error' => 'Method not allowed'], 405);
            }
        } elseif ($sub === 'submissions') {
            require __DIR__ . '/controllers/SubmissionController.php';
            $sc = new SubmissionController($id);
            if ($method === 'GET') $sc->index();
            else Security::jsonResponse(['error' => 'Method not allowed'], 405);
        } elseif ($sub === 'export') {
            require __DIR__ . '/controllers/SubmissionController.php';
            $sc = new SubmissionController($id);
            if ($method === 'GET') $sc->export();
            else Security::jsonResponse(['error' => 'Method not allowed'], 405);
        } elseif ($sub === 'reorder') {
            require __DIR__ . '/controllers/FieldController.php';
            $fc = new FieldController($id);
            if ($method === 'PUT') $fc->reorder();
            else Security::jsonResponse(['error' => 'Method not allowed'], 405);
        } else {
            if ($method === 'GET')    $ctrl->show($id);
            elseif ($method === 'PUT')    $ctrl->update($id);
            elseif ($method === 'DELETE') $ctrl->delete($id);
            else Security::jsonResponse(['error' => 'Method not allowed'], 405);
        }
        break;

    case 'submit':
        require __DIR__ . '/controllers/SubmissionController.php';
        $sc = new SubmissionController($id);
        if ($method === 'POST') $sc->submit();
        else Security::jsonResponse(['error' => 'Method not allowed'], 405);
        break;

    case 'public':
        // Public form fetch (no auth)
        require __DIR__ . '/controllers/FormController.php';
        $ctrl = new FormController(false);
        if ($id && $method === 'GET') $ctrl->publicShow($id);
        else Security::jsonResponse(['error' => 'Not found'], 404);
        break;

    default:
        Security::jsonResponse(['error' => 'Not found'], 404);
}