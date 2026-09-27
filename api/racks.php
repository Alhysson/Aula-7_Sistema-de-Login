<?php
require_once __DIR__ . '/auth.php';

$usuarioLogado = requireApiAuth();
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        handleGetRacks();
        break;

    case 'POST':
        requireApiWritePermission($usuarioLogado);
        handleCreateRack();
        break;

    case 'PUT':
    case 'PATCH':
        requireApiWritePermission($usuarioLogado);
        handleUpdateRack();
        break;

    case 'DELETE':
        requireApiWritePermission($usuarioLogado);
        handleDeleteRack();
        break;

    default:
        sendJsonResponse(null, 405, "Método {$method} não suportado neste recurso.");
}

/**
 * Tratamento de GET /api/racks.php e GET /api/racks.php?id=X
 */
function handleGetRacks(): void {
    global $conn;

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id > 0) {
        $sql = "SELECT r.*, a.nome AS ambiente_nome, a.tipo AS ambiente_tipo, a.andar AS ambiente_andar
                FROM racks r
                JOIN ambientes a ON a.id_ambiente = r.id_ambiente
                WHERE r.id_rack = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rack = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$rack) {
            sendJsonResponse(null, 404, "Rack não encontrado.");
        }

        sendJsonResponse($rack, 200);
    } else {
        $sql = "SELECT r.*, a.nome AS ambiente_nome, a.tipo AS ambiente_tipo, a.andar AS ambiente_andar
                FROM racks r
                JOIN ambientes a ON a.id_ambiente = r.id_ambiente
                ORDER BY r.id_rack DESC";
        $result = $conn->query($sql);
        $racks = $result->fetch_all(MYSQLI_ASSOC);

        sendJsonResponse($racks, 200, "Lista de racks recuperada com sucesso.");
    }
}

/**
 * Tratamento de POST /api/racks.php (Criação)
 */
function handleCreateRack(): void {
    global $conn;

    $body = getRequestBody();

    $identificacao = trim($body['identificacao'] ?? '');
    $tamanho_us = (int) ($body['tamanho_us'] ?? 0);
    $data_instalacao = !empty($body['data_instalacao']) ? $body['data_instalacao'] : null;
    $id_ambiente = (int) ($body['id_ambiente'] ?? 0);

    if ($identificacao === '' || $tamanho_us <= 0 || $id_ambiente <= 0) {
        sendJsonResponse(null, 400, "Dados inválidos. Informe 'identificacao', 'tamanho_us' > 0 e 'id_ambiente' > 0.");
    }

    // Valida se o ambiente existe
    $stmtAmb = $conn->prepare("SELECT id_ambiente FROM ambientes WHERE id_ambiente = ?");
    $stmtAmb->bind_param("i", $id_ambiente);
    $stmtAmb->execute();
    if (!$stmtAmb->get_result()->fetch_assoc()) {
        $stmtAmb->close();
        sendJsonResponse(null, 400, "O ambiente especificado (id_ambiente: {$id_ambiente}) não existe.");
    }
    $stmtAmb->close();

    $stmt = $conn->prepare("INSERT INTO racks (identificacao, tamanho_us, data_instalacao, id_ambiente) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sisi", $identificacao, $tamanho_us, $data_instalacao, $id_ambiente);

    if ($stmt->execute()) {
        $novoId = $stmt->insert_id;
        $stmt->close();
        
        sendJsonResponse([
            'id_rack' => $novoId,
            'identificacao' => $identificacao,
            'tamanho_us' => $tamanho_us,
            'data_instalacao' => $data_instalacao,
            'id_ambiente' => $id_ambiente
        ], 201, "Rack criado com sucesso.");
    } else {
        $errorMsg = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao inserir rack no banco de dados.", ['detail' => $errorMsg]);
    }
}

/**
 * Tratamento de PUT/PATCH /api/racks.php?id=X
 */
function handleUpdateRack(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_rack'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID do rack não informado para atualização.");
    }

    // Verifica se existe
    $stmtCheck = $conn->prepare("SELECT id_rack FROM racks WHERE id_rack = ?");
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    if (!$stmtCheck->get_result()->fetch_assoc()) {
        $stmtCheck->close();
        sendJsonResponse(null, 404, "Rack ID {$id} não encontrado.");
    }
    $stmtCheck->close();

    $identificacao = trim($body['identificacao'] ?? '');
    $tamanho_us = (int) ($body['tamanho_us'] ?? 0);
    $data_instalacao = !empty($body['data_instalacao']) ? $body['data_instalacao'] : null;
    $id_ambiente = (int) ($body['id_ambiente'] ?? 0);

    if ($identificacao === '' || $tamanho_us <= 0 || $id_ambiente <= 0) {
        sendJsonResponse(null, 400, "Campos obrigatórios inválidos: 'identificacao', 'tamanho_us' e 'id_ambiente'.");
    }

    $stmt = $conn->prepare("UPDATE racks SET identificacao = ?, tamanho_us = ?, data_instalacao = ?, id_ambiente = ? WHERE id_rack = ?");
    $stmt->bind_param("sisii", $identificacao, $tamanho_us, $data_instalacao, $id_ambiente, $id);

    if ($stmt->execute()) {
        $stmt->close();
        sendJsonResponse([
            'id_rack' => $id,
            'identificacao' => $identificacao,
            'tamanho_us' => $tamanho_us,
            'data_instalacao' => $data_instalacao,
            'id_ambiente' => $id_ambiente
        ], 200, "Rack atualizado com sucesso.");
    } else {
        $errorMsg = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao atualizar rack.", ['detail' => $errorMsg]);
    }
}

/**
 * Tratamento de DELETE /api/racks.php?id=X
 */
function handleDeleteRack(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_rack'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID do rack não informado para exclusão.");
    }

    try {
        $stmt = $conn->prepare("DELETE FROM racks WHERE id_rack = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $stmt->close();
            sendJsonResponse(['id_rack' => $id], 200, "Rack excluído com sucesso.");
        } else {
            $stmt->close();
            sendJsonResponse(null, 404, "Rack ID {$id} não encontrado para exclusão.");
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1451) {
            sendJsonResponse(null, 409, "Conflito de integridade: não é possível excluir este rack pois há equipamentos ou manutenções associados.");
        }
        sendJsonResponse(null, 500, "Erro ao tentar excluir o rack.", ['detail' => $e->getMessage()]);
    }
}
