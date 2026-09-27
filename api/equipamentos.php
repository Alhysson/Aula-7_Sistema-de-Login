<?php
require_once __DIR__ . '/auth.php';

$usuarioLogado = requireApiAuth();
$method = getRequestMethod();

switch ($method) {
    case 'GET':
        handleGetEquipamentos();
        break;

    case 'POST':
        requireApiWritePermission($usuarioLogado);
        handleCreateEquipamento();
        break;

    case 'PUT':
    case 'PATCH':
        requireApiWritePermission($usuarioLogado);
        handleUpdateEquipamento();
        break;

    case 'DELETE':
        requireApiWritePermission($usuarioLogado);
        handleDeleteEquipamento();
        break;

    default:
        sendJsonResponse(null, 405, "Método {$method} não suportado neste recurso.");
}

function handleGetEquipamentos(): void {
    global $conn;

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    $rackId = isset($_GET['id_rack']) ? (int) $_GET['id_rack'] : 0;

    $sql = "SELECT e.*, r.identificacao AS rack_identificacao, c.nome AS categoria_nome, f.nome AS fabricante_nome
            FROM equipamentos_rack e
            JOIN racks r ON r.id_rack = e.id_rack
            JOIN categorias_equipamento c ON c.id_categoria = e.id_categoria
            JOIN fabricantes f ON f.id_fabricante = e.id_fabricante";

    if ($id > 0) {
        $sql .= " WHERE e.id_equipamento = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $equipamento = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$equipamento) {
            sendJsonResponse(null, 404, "Equipamento não encontrado.");
        }
        sendJsonResponse($equipamento, 200);
    } elseif ($rackId > 0) {
        $sql .= " WHERE e.id_rack = ? ORDER BY e.id_equipamento DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $rackId);
        $stmt->execute();
        $equipamentos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        sendJsonResponse($equipamentos, 200, "Equipamentos do rack ID {$rackId} recuperados.");
    } else {
        $sql .= " ORDER BY e.id_equipamento DESC";
        $result = $conn->query($sql);
        $equipamentos = $result->fetch_all(MYSQLI_ASSOC);
        sendJsonResponse($equipamentos, 200, "Lista de equipamentos recuperada.");
    }
}

function handleCreateEquipamento(): void {
    global $conn;

    $body = getRequestBody();

    $id_rack = (int) ($body['id_rack'] ?? 0);
    $id_categoria = (int) ($body['id_categoria'] ?? 0);
    $id_fabricante = (int) ($body['id_fabricante'] ?? 0);
    $quantidade_portas = isset($body['quantidade_portas']) && $body['quantidade_portas'] !== '' ? (int) $body['quantidade_portas'] : null;

    if ($id_rack <= 0 || $id_categoria <= 0 || $id_fabricante <= 0) {
        sendJsonResponse(null, 400, "Informe 'id_rack', 'id_categoria' e 'id_fabricante' válidos.");
    }

    $stmt = $conn->prepare("INSERT INTO equipamentos_rack (id_rack, id_categoria, id_fabricante, quantidade_portas) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiii", $id_rack, $id_categoria, $id_fabricante, $quantidade_portas);

    if ($stmt->execute()) {
        $novoId = $stmt->insert_id;
        $stmt->close();
        sendJsonResponse([
            'id_equipamento' => $novoId,
            'id_rack' => $id_rack,
            'id_categoria' => $id_categoria,
            'id_fabricante' => $id_fabricante,
            'quantidade_portas' => $quantidade_portas
        ], 201, "Equipamento adicionado com sucesso.");
    } else {
        $error = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao adicionar equipamento.", ['detail' => $error]);
    }
}

function handleUpdateEquipamento(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_equipamento'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID do equipamento não informado.");
    }

    $id_rack = (int) ($body['id_rack'] ?? 0);
    $id_categoria = (int) ($body['id_categoria'] ?? 0);
    $id_fabricante = (int) ($body['id_fabricante'] ?? 0);
    $quantidade_portas = isset($body['quantidade_portas']) && $body['quantidade_portas'] !== '' ? (int) $body['quantidade_portas'] : null;

    if ($id_rack <= 0 || $id_categoria <= 0 || $id_fabricante <= 0) {
        sendJsonResponse(null, 400, "Campos obrigatórios inválidos: 'id_rack', 'id_categoria', 'id_fabricante'.");
    }

    $stmt = $conn->prepare("UPDATE equipamentos_rack SET id_rack = ?, id_categoria = ?, id_fabricante = ?, quantidade_portas = ? WHERE id_equipamento = ?");
    $stmt->bind_param("iiiii", $id_rack, $id_categoria, $id_fabricante, $quantidade_portas, $id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows === 0) {
            // Pode ter sido ID inexistente ou sem alterações
            $stmtCheck = $conn->prepare("SELECT id_equipamento FROM equipamentos_rack WHERE id_equipamento = ?");
            $stmtCheck->bind_param("i", $id);
            $stmtCheck->execute();
            $exists = (bool) $stmtCheck->get_result()->fetch_assoc();
            $stmtCheck->close();

            if (!$exists) {
                sendJsonResponse(null, 404, "Equipamento ID {$id} não encontrado.");
            }
        }
        $stmt->close();
        sendJsonResponse([
            'id_equipamento' => $id,
            'id_rack' => $id_rack,
            'id_categoria' => $id_categoria,
            'id_fabricante' => $id_fabricante,
            'quantidade_portas' => $quantidade_portas
        ], 200, "Equipamento atualizado com sucesso.");
    } else {
        $error = $stmt->error;
        $stmt->close();
        sendJsonResponse(null, 500, "Erro ao atualizar equipamento.", ['detail' => $error]);
    }
}

function handleDeleteEquipamento(): void {
    global $conn;

    $body = getRequestBody();
    $id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($body['id_equipamento'] ?? 0);

    if ($id <= 0) {
        sendJsonResponse(null, 400, "ID do equipamento não informado.");
    }

    $stmt = $conn->prepare("DELETE FROM equipamentos_rack WHERE id_equipamento = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        $stmt->close();
        sendJsonResponse(['id_equipamento' => $id], 200, "Equipamento excluído com sucesso.");
    } else {
        $stmt->close();
        sendJsonResponse(null, 404, "Equipamento ID {$id} não encontrado.");
    }
}
