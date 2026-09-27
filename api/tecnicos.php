<?php
require_once __DIR__ . '/auth.php';

$usuarioLogado = requireApiAuth();
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        handleGetTecnicos();
        break;

    case 'POST':
        requireApiWritePermission($usuarioLogado);
        handleCreateTecnico();
        break;

    case 'PUT':
    case 'PATCH':
        requireApiWritePermission($usuarioLogado);
        handleUpdateTecnico();
        break;

    case 'DELETE':
        requireApiWritePermission($usuarioLogado);
        handleDeleteTecnico();
        break;

    default:
        sendJsonResponse(null, 405, "Método {$method} não suportado neste recurso.");
}

function handleGetTecnicos(): void {
    global $conn;

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("SELECT * FROM tecnicos WHERE id_tecnico = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $tecnico = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$tecnico) {
            sendJsonResponse(null, 404, "Técnico não encontrado.");
        }
        sendJsonResponse($tecnico, 200);
    } else {
        $result = $conn->query("SELECT * FROM tecnicos ORDER BY nome ASC");
        $tecnicos = $result->fetch_all(MYSQLI_ASSOC);
        sendJsonResponse($tecnicos, 200, "Lista de técnicos recuperada.");
    }
}

function handleCreateTecnico(): void {
    global $conn;

    $body = getRequestBody();

    $nome = trim($body['nome'] ?? '');
    $matricula = trim($body['matricula'] ?? '');
    $email = trim($body['email'] ?? '');

    if ($nome === '' || $matricula === '') {
        sendJsonResponse(null, 400, "Nome e matrícula são obrigatórios.");
    }

    try {
        $stmt = $conn->prepare("INSERT INTO tecnicos (nome, matricula, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nome, $matricula, $email);
        $stmt->execute();
        $novoId = $stmt->insert_id;
        $stmt->close();

        sendJsonResponse([
            'id_tecnico' => $novoId,
            'nome' => $nome,
            'matricula' => $matricula,
            'email' => $email
        ], 201, "Técnico cadastrado com sucesso.");
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            sendJsonResponse(null, 409, "Matrícula '{$matricula}' já está cadastrada para outro técnico.");
        }
        sendJsonResponse(null, 500, "Erro ao cadastrar técnico.", ['detail' => $e->getMessage()]);
    }
}

function handleUpdateTecnico(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_tecnico'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID do técnico não informado.");
    }

    $nome = trim($body['nome'] ?? '');
    $matricula = trim($body['matricula'] ?? '');
    $email = trim($body['email'] ?? '');

    if ($nome === '' || $matricula === '') {
        sendJsonResponse(null, 400, "Nome e matrícula são obrigatórios.");
    }

    try {
        $stmt = $conn->prepare("UPDATE tecnicos SET nome = ?, matricula = ?, email = ? WHERE id_tecnico = ?");
        $stmt->bind_param("sssi", $nome, $matricula, $email, $id);
        $stmt->execute();

        if ($stmt->affected_rows === 0) {
            $stmtCheck = $conn->prepare("SELECT id_tecnico FROM tecnicos WHERE id_tecnico = ?");
            $stmtCheck->bind_param("i", $id);
            $stmtCheck->execute();
            $exists = (bool) $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if (!$exists) {
                sendJsonResponse(null, 404, "Técnico ID {$id} não encontrado.");
            }
        }
        $stmt->close();

        sendJsonResponse([
            'id_tecnico' => $id,
            'nome' => $nome,
            'matricula' => $matricula,
            'email' => $email
        ], 200, "Técnico atualizado com sucesso.");
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1062) {
            sendJsonResponse(null, 409, "Matrícula '{$matricula}' já em uso por outro técnico.");
        }
        sendJsonResponse(null, 500, "Erro ao atualizar técnico.", ['detail' => $e->getMessage()]);
    }
}

function handleDeleteTecnico(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_tecnico'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID do técnico não informado.");
    }

    try {
        $stmt = $conn->prepare("DELETE FROM tecnicos WHERE id_tecnico = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $stmt->close();
            sendJsonResponse(['id_tecnico' => $id], 200, "Técnico excluído com sucesso.");
        } else {
            $stmt->close();
            sendJsonResponse(null, 404, "Técnico ID {$id} não encontrado.");
        }
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() === 1451) {
            sendJsonResponse(null, 409, "Conflito de integridade: não é possível excluir o técnico pois ele possui histórico de manutenções registradas.");
        }
        sendJsonResponse(null, 500, "Erro ao excluir técnico.", ['detail' => $e->getMessage()]);
    }
}
