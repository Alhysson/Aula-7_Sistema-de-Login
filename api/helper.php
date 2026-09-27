<?php
// Configura os cabeçalhos padrão para respostas da API RESTful
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Trata requisições preflight OPTIONS para CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Envia uma resposta JSON padronizada com o código HTTP adequado.
 *
 * @param mixed $data Dados a serem retornados
 * @param int $statusCode Código HTTP de status (200, 201, 400, 401, 403, 404, 409, 500, etc.)
 * @param string|null $message Mensagem opcional de sucesso ou erro
 * @param mixed $errors Detalhes adicionais dos erros
 */
function sendJsonResponse($data = null, int $statusCode = 200, ?string $message = null, $errors = null): void {
    http_response_code($statusCode);

    $response = [
        'success' => ($statusCode >= 200 && $statusCode < 300),
        'status' => $statusCode
    ];

    if ($message !== null) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        $response['data'] = $data;
    }

    if ($errors !== null) {
        $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}

/**
 * Obtém o corpo da requisição de forma limpa (suporta JSON no body ou $_POST).
 *
 * @return array
 */
function getRequestBody(): array {
    $rawInput = file_get_contents('php://input');
    $json = json_decode($rawInput, true);

    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        return $json;
    }

    return $_POST;
}

/**
 * Retorna o método HTTP da requisição atual em maiúsculas (GET, POST, PUT, DELETE, etc.).
 *
 * @return string
 */
function getRequestMethod(): string {
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Handler global para captura de erros e exceções não tratadas na API.
 */
set_exception_handler(function (Throwable $e) {
    sendJsonResponse(
        null,
        500,
        "Erro interno no servidor.",
        ['detail' => $e->getMessage()]
    );
});
