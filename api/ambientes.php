<?php
require_once __DIR__ . '/auth.php';

$usuarioLogado = requireApiAuth();
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        handleGetAmbientes();
        break;

    case 'POST':
        requireApiWritePermission($usuarioLogado);
        handleCreateAmbiente();
        break;

    default:
        sendJsonResponse(null, 405, "Método {$method} não suportado neste recurso.");
}

function handleGetAmbientes(): void {
    global $conn;

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM ambientes WHERE id_ambiente = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $ambiente = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ambiente) {
            sendJsonResponse(null, 404, "Ambiente não encontrado.");
        }
        sendJsonResponse($ambiente, 200);
    } else {
        $result = $conn->query("SELECT * FROM ambientes ORDER BY nome ASC");
        $ambientes = $result->fetch_all(MYSQLI_ASSOC);
        sendJsonResponse($ambientes, 200, "Lista de ambientes recuperada.");
    }
}

function handleCreateAmbiente(): void {
    global $conn;

    $body = getRequestBody();

    $nome = trim($body['nome'] ?? '');
    $tipo = trim($body['tipo'] ?? '');
    $andar = (int) ($body['andar'] ?? 0);

    if (empty($nome) || empty($tipo)) {
        sendJsonResponse(null, 400, "Nome e tipo do ambiente são obrigatórios.");
    }

    $stmt = $conn->prepare("INSERT INTO ambientes (nome, tipo, andar) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $nome, $tipo, $andar);

    if ($stmt->execute()) {
        $novoId = $stmt->insert_id;
        $stmt->close();
        sendJsonResponse([
            'id_ambiente' => $novoId,
            'nome' => $nome,
            'tipo' => $tipo,
            'andar' => $andar
        ], 201, "Ambiente criado com sucesso.");
    } else {
        $error = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao criar ambiente.", ['detail' => $error]);
    }
}
