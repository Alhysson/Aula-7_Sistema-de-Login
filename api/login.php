<?php
require_once __DIR__ . '/helper.php';
require_once __DIR__ . '/../conexao.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$method = getRequestMethod();

if ($method !== 'POST') {
    sendJsonResponse(null, 451 ?? 405, "Método HTTP " . $method . " não permitido. Use POST.");
}

$body = getRequestBody();
$email = trim($body['email'] ?? '');
$senha = $body['senha'] ?? '';

if (empty($email) || empty($senha)) {
    sendJsonResponse(null, 400, "Informe email e senha para realizar o login.");
}

$stmt = $conn->prepare("SELECT id, nome, email, senha, tipo FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();
$conn->close();

if ($usuario && password_verify($senha, $usuario['senha'])) {
    $_SESSION['usuario_id'] = $usuario['id'];
    $_SESSION['usuario'] = $usuario['nome'];

    unset($usuario['senha']);

    sendJsonResponse([
        'user' => $usuario,
        'session_id' => session_id()
    ], 200, "Login efetuado com sucesso.");
} else {
    sendJsonResponse(null, 401, "Email ou senha incorretos.");
}
