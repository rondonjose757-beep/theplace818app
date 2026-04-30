<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$body     = json_decode(file_get_contents('php://input'), true) ?? [];
$id       = (int)($body['id']       ?? 0);
$nombre   = trim((string)($body['nombre']   ?? ''));
$email    = trim((string)($body['email']    ?? ''));
$rol      = trim((string)($body['rol']      ?? ''));
$password = (string)($body['password'] ?? '');

$rolesValidos = ['administrador', 'dueño', 'cajero'];

if ($nombre === '' || $email === '' || !in_array($rol, $rolesValidos, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'nombre, email y rol válido son obligatorios']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit;
}

$db = getDB();

try {
    if ($id > 0) {
        // Actualizar usuario existente
        // Verificar que el email no esté en uso por otro usuario
        $stCheck = $db->prepare('SELECT id FROM usuarios WHERE email = ? AND id != ? LIMIT 1');
        $stCheck->execute([$email, $id]);
        if ($stCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Ese email ya está registrado']);
            exit;
        }

        $db->prepare('UPDATE usuarios SET nombre = ?, email = ?, rol = ? WHERE id = ?')
           ->execute([$nombre, $email, $rol, $id]);

        echo json_encode(['success' => true, 'id' => $id]);
    } else {
        // Crear nuevo usuario — password obligatorio
        if (strlen($password) < 8) {
            http_response_code(400);
            echo json_encode(['error' => 'La contraseña debe tener al menos 8 caracteres']);
            exit;
        }

        // Verificar email único
        $stCheck = $db->prepare('SELECT id FROM usuarios WHERE email = ? LIMIT 1');
        $stCheck->execute([$email]);
        if ($stCheck->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Ese email ya está registrado']);
            exit;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $db->prepare(
            'INSERT INTO usuarios (nombre, email, password, rol) VALUES (?, ?, ?, ?)'
        )->execute([$nombre, $email, $hash, $rol]);

        echo json_encode(['success' => true, 'id' => (int)$db->lastInsertId()]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de servidor']);
}
