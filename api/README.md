# Documentação da API RESTful — Gestão de Racks & Login

Esta API permite gerenciar os dados do sistema através de requisições HTTP RESTful utilizando cargas em formato **JSON**.

---

## Estrutura da API

Todas as rotas estão localizadas na pasta `api/` e retornam dados no seguinte padrão JSON:

```json
{
  "success": true,
  "status": 200,
  "message": "Mensagem descritiva opcional",
  "data": { ... }
}
```

Em caso de erro:

```json
{
  "success": false,
  "status": 400,
  "message": "Descrição do erro",
  "errors": { ... }
}
```

---

## Códigos de Status HTTP Tratados

| Código | Significado | Exemplo de Uso |
|---|---|---|
| **200 OK** | Sucesso | Consulta ou atualização realizada com sucesso |
| **201 Created** | Criado | Novo registro inserido |
| **400 Bad Request** | Dados inválidos | Campos obrigatórios ausentes ou tipo incorreto |
| **401 Unauthorized** | Não autenticado | Usuário sem sessão ativa no login |
| **403 Forbidden** | Acesso negado | Usuário do tipo `guest` tentando criar/alterar |
| **404 Not Found** | Não encontrado | Registro com o ID informado não existe |
| **409 Conflict** | Conflito | Exclusão de rack/técnico com dependências (FK integrity 1451) ou matrícula duplicada (1062) |
| **405 Method Not Allowed** | Método não permitido | Tentativa de usar método HTTP inválido |
| **500 Internal Error** | Erro de servidor | Falha no banco de dados |

---

## Endpoints Disponíveis

### 1. Autenticação

#### POST `/api/login.php`
- **Body JSON**:
  ```json
  {
    "email": "usuario@unesc.br",
    "senha": "suasenhaaqui"
  }
  ```
- **Resposta (200 OK)**:
  ```json
  {
    "success": true,
    "status": 200,
    "message": "Login efetuado com sucesso.",
    "data": {
      "user": {
        "id": 1,
        "nome": "Admin",
        "email": "usuario@unesc.br",
        "tipo": "creator"
      },
      "session_id": "..."
    }
  }
  ```

---

### 2. Racks

#### GET `/api/racks.php`
- Lista todos os racks.
- Opcional: `/api/racks.php?id=1` busca um rack específico.

#### POST `/api/racks.php` *(Requer privilégio creator/admin)*
- **Body JSON**:
  ```json
  {
    "identificacao": "Rack-Lab3",
    "tamanho_us": 44,
    "data_instalacao": "2024-05-10",
    "id_ambiente": 2
  }
  ```

#### PUT `/api/racks.php?id=1` *(Requer privilégio creator/admin)*
- **Body JSON**: Atualiza os dados do rack indicado.

#### DELETE `/api/racks.php?id=1` *(Requer privilégio creator/admin)*
- Exclui o rack. Retorna `409 Conflict` se houver equipamentos/manutenções vinculados.

---

### 3. Equipamentos

- `GET /api/equipamentos.php` (Filtro por rack: `?id_rack=1` ou por id: `?id=1`)
- `POST /api/equipamentos.php`
- `PUT /api/equipamentos.php?id=1`
- `DELETE /api/equipamentos.php?id=1`

---

### 4. Técnicos

- `GET /api/tecnicos.php`
- `POST /api/tecnicos.php`
- `PUT /api/tecnicos.php?id=1`
- `DELETE /api/tecnicos.php?id=1` (Trata violação de matrícula `409 Conflict`)

---

### 5. Manutenções

- `GET /api/manutencoes.php` (Filtro por rack: `?id_rack=1`)
- `POST /api/manutencoes.php`
- `PUT /api/manutencoes.php?id=1`
- `DELETE /api/manutencoes.php?id=1`

---

### 6. Ambientes

- `GET /api/ambientes.php`
- `POST /api/ambientes.php`

---

## Como Testar via Terminal (cURL)

#### Exemplo de Login e salvamento de Cookies:
```bash
curl -X POST http://localhost:8090/api/login.php \
  -H "Content-Type: application/json" \
  -d "{\"email\": \"admin@unesc.br\", \"senha\": \"123456\"}" \
  -c cookies.txt
```

#### Exemplo de Listagem de Racks:
```bash
curl -X GET http://localhost:8090/api/racks.php \
  -b cookies.txt
```

#### Exemplo de Cadastro de Rack (POST):
```bash
curl -X POST http://localhost:8090/api/racks.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d "{\"identificacao\": \"Rack-Novo\", \"tamanho_us\": 24, \"id_ambiente\": 1}"
```
