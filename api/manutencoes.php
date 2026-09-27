<?php
require_once __DIR__ . '/auth.php';

$usuarioLogado = requireApiAuth();
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        handleGetManutencoes();
        break;

    case 'POST':
        requireApiWritePermission($usuarioLogado);
        handleCreateManutencao();
        break;

    case 'PUT':
    case 'PATCH':
        requireApiWritePermission($usuarioLogado);
        handleUpdateManutencao();
        break;

    case 'DELETE':
        requireApiWritePermission($usuarioLogado);
        handleDeleteManutencao();
        break;

    default:
        sendJsonResponse(null, 405, "Método {$method} não suportado neste recurso.");
}

function handleGetManutencoes(): void {
    global $conn;

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $rackId = isset($_GET['id_rack']) ? (int) $_GET['id_rack'] : 0;

    $sql = "SELECT m.*, r.identificacao AS rack_identificacao, t.nome AS tecnico_nome, t.matricula AS tecnico_matricula
            FROM manutencoes m
            JOIN racks r ON r.id_rack = m.id_rack
            JOIN tecnicos t ON t.id_tecnico = m.id_tecnico";

    if ($id > 0) {
        $sql .= " WHERE m.id_manutencao = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $manutencao = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$manutencao) {
            sendJsonResponse(null, 404, "Registro de manutenção não encontrado.");
        }
        sendJsonResponse($manutencao, 200);
    } elseif ($rackId > 0) {
        $sql .= " WHERE m.id_rack = ? ORDER BY m.data_servico DESC, m.id_manutencao DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $rackId);
        $stmt->execute();
        $manutencoes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        sendJsonResponse($manutencoes, 200, "Manutenções do rack ID {$rackId} recuperadas.");
    } else {
        $sql .= " ORDER BY m.data_servico DESC, m.id_manutencao DESC";
        $result = $conn->query($sql);
        $manutencoes = $result->fetch_all(MYSQLI_ASSOC);

        sendJsonResponse($manutencoes, 200, "Lista de manutenções recuperada.");
    }
}

function handleCreateManutencao(): void {
    global $conn;

    $body = getRequestBody();

    $id_rack = (int) ($body['id_rack'] ?? 0);
    $id_tecnico = (int) ($body['id_tecnico'] ?? 0);
    $data_servico = trim($body['data_servico'] ?? '');
    $descricao = trim($body['descricao'] ?? '');

    if ($id_rack <= 0 || $id_tecnico <= 0 || empty($data_servico) || empty($descricao)) {
        sendJsonResponse(null, 400, "Preencha todos os campos obrigatórios: 'id_rack', 'id_tecnico', 'data_servico' e 'descricao'.");
    }

    $stmt = $conn->prepare("INSERT INTO manutencoes (id_rack, id_tecnico, data_servico, descricao) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $id_rack, $id_tecnico, $data_servico, $descricao);

    if ($stmt->execute()) {
        $novoId = $stmt->insert_id;
        $stmt->close();
        sendJsonResponse([
            'id_manutencao' => $novoId,
            'id_rack' => $id_rack,
            'id_tecnico' => $id_tecnico,
            'data_servico' => $data_servico,
            'descricao' => $descricao
        ], 201, "Manutenção registrada com sucesso.");
    } else {
        $error = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao registrar manutenção.", ['detail' => $error]);
    }
}

function handleUpdateManutencao(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_manutencao'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID da manutenção não informado.");
    }

    $id_rack = (int) ($body['id_rack'] ?? 0);
    $id_tecnico = (int) ($body['id_tecnico'] ?? 0);
    $data_servico = trim($body['data_servico'] ?? '');
    $descricao = trim($body['descricao'] ?? '');

    if ($id_rack <= 0 || $id_tecnico <= 0 || empty($data_servico) || empty($descricao)) {
        sendJsonResponse(null, 400, "Preencha todos os campos obrigatórios: 'id_rack', 'id_tecnico', 'data_servico' e 'descricao'.");
    }

    $stmt = $conn->prepare("UPDATE manutencoes SET id_rack = ?, id_tecnico = ?, data_servico = ?, descricao = ? WHERE id_manutencao = ?");
    $stmt->bind_param("iissi", $id_rack, $id_tecnico, $data_servico, $descricao, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
            $stmtCheck = $conn->prepare("SELECT id_manutencao FROM manutencoes WHERE id_manutencao = ?");
            $stmtCheck->bind_param("i", $id);
            $stmtCheck->execute();
            $exists = (bool) $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if (!$exists) {
                sendJsonResponse(null, 404, "Manutenção ID {$id} não encontrada.");
            }
        }
        $stmt->close();
        sendJsonResponse([
            'id_manutencao' => $id,
            'id_rack' => $id_rack,
            'id_tecnico' => $id_tecnico,
            'data_servico' => $data_servico,
            'descricao' => $descricao
        ], 200, "Manutenção atualizada com sucesso.");
    } else {
        $error = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao atualizar manutenção.", ['detail' => $error]);
    }
}

function handleDeleteManutencao(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_manutencao'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID da manutenção não informado.");
    }

    $stmt = $conn->prepare("DELETE FROM manutencoes WHERE id_manutencao = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt->close();
        sendJsonResponse(['id_manutencao' => $id], 200, "Registro de manutenção excluído com sucesso.");
    } else {
        $stmt->close();
        sendJsonResponse(null, 404, "Manutenção ID {$id} não encontrada.");
    }
}
