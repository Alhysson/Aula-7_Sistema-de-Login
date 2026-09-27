<?php
require_once __DIR__ . '/helper.php';

// Tenta iniciar a sessão se ainda não tiver iniciado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../conexao.php';

/**
 * Autentica o usuário para chamadas da API RESTful.
 * Retorna os dados do usuário autenticado ou encerra a requisição com 401 Unauthorized.
 *
 * @return array Dados do usuário logado ['id', 'nome', 'email', 'tipo']
 */
function requireApiAuth(): array {
    global $conn;

    if (!isset($_SESSION['usuario_id'])) {
        sendJsonResponse(null, 401, "Não autenticado. Por favor, faça login.");
    }

    $id_logado = (int) $_SESSION['usuario_id'];

    $stmt = $conn->prepare("SELECT id, nome, email, tipo FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id_logado);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$usuario) {
        unset($_SESSION['usuario_id']);
        sendJsonResponse(null, 401, "Usuário não encontrado ou sessão inválida.");
    }

    return $usuario;
}

/**
 * Garante que o usuário autenticado tenha permissões de escrita (creator ou admin).
 * Encerra a requisição com 403 Forbidden se o usuário for 'guest'.
 *
 * @param array $usuario Dados do usuário obtidos por requireApiAuth()
 */
function requireApiWritePermission(array $usuario): void {
    if (!in_array($usuario['tipo'], ['creator', 'admin'], true)) {
        sendJsonResponse(null, 403, "Acesso negado. Você não tem permissão para alterar estes dados.");
    }
}
