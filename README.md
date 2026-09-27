# Gestão de Racks do Bloco B com Sistema de Login — PHP

Sistema desenvolvido para a disciplina de **Desenvolvimento Back-End**, unindo os dois
trabalhos da matéria:

- **1ª avaliação** — modelagem do banco de dados de gestão da infraestrutura de racks de
  rede do Bloco B da UNESC (7 tabelas em 3ª Forma Normal);
- **Aula 7** — autenticação e gerenciamento de sessões em PHP.

O resultado é uma aplicação onde o banco de racks só é acessível depois do login, e o que
cada pessoa pode fazer com os dados depende do seu nível de permissão (**creator**,
**admin** ou **guest**).

---

## Requisitos

| Item | Versão usada | Observação |
|---|---|---|
| PHP | 8.2 | Precisa ser 8.0 ou superior |
| MySQL ou MariaDB | 8.0 | O MariaDB que vem no XAMPP também funciona |
| Servidor web | Apache (XAMPP) | Dá para usar o servidor embutido do PHP |
| Extensão PHP | `mysqli` | Já vem habilitada no XAMPP |

A forma mais simples de ter tudo isso é instalar o [XAMPP](https://www.apachefriends.org/),
que traz Apache, PHP e MySQL juntos.

---

## Setup

### 1. Baixar os arquivos

Clone o repositório dentro da pasta `htdocs` do XAMPP:

```bash
cd C:\xampp\htdocs
git clone https://github.com/LuizPauloC/Aula-7_Sistema-de-Login.git aula7-login
```

O caminho final dos arquivos deve ficar assim:

```
C:\xampp\htdocs\aula7-login\
```

> No Linux/Mac o caminho do `htdocs` costuma ser `/opt/lampp/htdocs` ou `/var/www/html`.

### 2. Criar o banco de dados

Abra o **XAMPP Control Panel** e inicie o **MySQL**. Depois importe o script de criação por
uma das opções abaixo.

**Pelo phpMyAdmin (mais fácil):**

1. Acesse <http://localhost/phpmyadmin>
2. Vá em **Importar**
3. Escolha o arquivo `sistema_login.sql` e clique em **Executar**

**Pelo terminal:**

```bash
mysql -u root -p --default-character-set=utf8mb4 < sistema_login.sql
```

> O `--default-character-set=utf8mb4` é **obrigatório** no Windows. Sem ele o cliente lê o
> arquivo usando a codificação do console e os acentos entram errados no banco
> ("Laboratório" vira "Laborat├│rio").

O script cria o banco `sistema_login` com **8 tabelas**: a de autenticação e as 7 do
trabalho de modelagem.

| Tabela | Função |
|---|---|
| `usuarios` | Contas de acesso com nome, email, senha em hash e tipo |
| `ambientes` | Espaços físicos do Bloco B (sala, corredor, laboratório) |
| `racks` | Gabinetes instalados, ligados a um ambiente |
| `categorias_equipamento` | Catálogo de tipos (switch, roteador, patch panel) |
| `fabricantes` | Marcas e contatos de suporte |
| `equipamentos_rack` | Dispositivos dentro de cada rack |
| `tecnicos` | Equipe de TI responsável pelas manutenções |
| `manutencoes` | Histórico de intervenções feitas nos racks |

As 7 tabelas de racks vêm com dados de exemplo já cadastrados. Rode o script **apenas uma
vez**, senão os dados de exemplo serão inseridos novamente.

### 3. Configurar a conexão

Abra o `conexao.php` e ajuste os dados de acesso ao seu MySQL:

```php
$servername = "localhost";
$username   = "root";
$password   = "";          // senha do seu MySQL — no XAMPP o padrão é vazio
$database   = "sistema_login";
```

Se o seu MySQL tem senha no usuário `root`, preencha a variável `$password`.

### 4. Subir o servidor

**Opção A — Apache do XAMPP:**

Inicie o **Apache** no XAMPP Control Panel e acesse:

```
http://localhost/aula7-login/
```

**Opção B — servidor embutido do PHP** (não precisa do Apache):

```bash
cd C:\xampp\htdocs\aula7-login
C:\xampp\php\php.exe -S 127.0.0.1:8090
```

E acesse <http://127.0.0.1:8090>.

### 5. Criar o primeiro usuário

Acesse `register.php` e cadastre-se normalmente.

> **O primeiro usuário cadastrado vira `creator` automaticamente.** Todos os cadastros
> seguintes entram como `guest` e precisam ser promovidos por um creator ou admin dentro
> do painel.

---

## Como usar

1. **Cadastro** (`register.php`) — nome, email e senha (mínimo 6 caracteres, com confirmação)
2. **Login** (`login.php`) — abre a sessão e leva ao painel
3. **Painel** (`painel.php`) — resumo da infraestrutura e, para creator/admin, gerenciamento de usuários
4. **Racks, Equipamentos, Manutenções e Técnicos** — consulta para todos; cadastro, edição e exclusão para creator/admin
5. **Cadastros** (`cadastros.php`) — consulta das tabelas de apoio (ambientes, categorias e fabricantes)
6. **Recuperar senha** (`recuperar_senha.php`) — informa o email e define a nova senha
7. **Sair** (`logout.php`) — destrói a sessão

Nos campos de senha há um ícone de olho que mostra ou esconde o que foi digitado. Quando
um formulário é recusado, nome e email voltam preenchidos; a senha, por segurança, é
sempre redigitada.

### Tipos de usuário

| Tipo | Consulta os dados | Cadastra / edita / exclui | Gerencia usuários | Pode ser rebaixado |
|---|---|---|---|---|
| `creator` | Sim | Sim | Sim | **Não** — o cargo é permanente |
| `admin` | Sim | Sim | Sim, inclusive outros admins | Sim |
| `guest` | Sim | Não | Não | — |

Regras aplicadas **pelo servidor**:

- O `creator` **não pode** ter o tipo alterado por ninguém
- Um `admin` **pode** alterar o tipo de outro `admin`
- Um `guest` consulta tudo, mas não altera nada
- Ninguém pode ser promovido a `creator` — o cargo só existe no primeiro cadastro
- Quem não está logado é redirecionado para o login

As validações não estão apenas na interface: mesmo enviando uma requisição direto para
os arquivos `processa_*.php` (por `curl` ou Postman, sem passar pela tela), as regras
continuam valendo. O arquivo `verifica_login.php` é incluído no topo de toda página
restrita e busca o tipo do usuário **no banco**, não na sessão — assim, se um admin for
rebaixado, ele perde o acesso na hora, sem esperar o logout.

### Integridade referencial

As chaves estrangeiras impedem apagar um registro que ainda tem dependentes. Ao tentar
excluir um rack que possui equipamentos ou manutenções, ou um técnico que já aparece no
histórico, o sistema mostra uma mensagem explicando o motivo em vez de quebrar. A
matrícula do técnico é `UNIQUE`, então cadastrar uma repetida também é recusado com aviso.

---

## Estrutura dos arquivos

```
aula7-login/
├── conexao.php               # Conexão com o MySQL (ajuste as credenciais aqui)
├── sistema_login.sql         # Script de criação do banco e das 8 tabelas
├── estilo.css                # Estilo de todas as páginas
├── script.js                 # Mostrar/esconder senha
├── index.php                 # Menu inicial
├── assets/                   # Logo, ícone do site e os olhos de mostrar senha
│
│   # --- Autenticação ---
├── register.php              # Formulário de cadastro
├── processa_registro.php     # Valida, define o tipo e grava o usuário
├── login.php                 # Formulário de login
├── processa_login.php        # Confere a senha e abre a sessão
├── recuperar_senha.php       # Formulário de nova senha
├── processa_recuperacao.php  # Valida e regrava a senha
├── logout.php                # Destrói a sessão
│
│   # --- Compartilhados pelas páginas restritas ---
├── verifica_login.php        # Exige login e descobre o tipo do usuário
├── menu.php                  # Barra de navegação
│
│   # --- API RESTful ---
├── api/
│   ├── helper.php            # Formatação JSON e manipulação de requisições
│   ├── auth.php              # Middleware de verificação de sessão e permissões para API
│   ├── login.php             # Endpoint REST POST de autenticação
│   ├── racks.php             # CRUD RESTful para Racks
│   ├── equipamentos.php      # CRUD RESTful para Equipamentos
│   ├── tecnicos.php          # CRUD RESTful para Técnicos
│   ├── manutencoes.php       # CRUD RESTful para Manutenções
│   ├── ambientes.php         # Endpoints para Ambientes
│   └── README.md             # Documentação dos endpoints REST
│
│   # --- Painel e gestão de racks ---
├── painel.php                # Resumo da infraestrutura + gerenciamento de usuários
├── processa_tipo.php         # Aplica as regras e altera o tipo de um usuário
├── racks.php                 # Lista e formulário de racks
├── processa_racks.php        # Cadastra, edita e exclui racks
├── equipamentos.php          # Lista e formulário de equipamentos
├── processa_equipamentos.php # Cadastra, edita e exclui equipamentos
├── manutencoes.php           # Lista e formulário de manutenções
├── processa_manutencoes.php  # Cadastra, edita e exclui manutenções
├── tecnicos.php              # Lista e formulário de técnicos
├── processa_tecnicos.php     # Cadastra, edita e exclui técnicos
└── cadastros.php             # Consulta das tabelas de apoio
```

Os arquivos de página utilizam a estrutura tradicional de formulários web, enquanto a pasta `api/` disponibiliza endpoints RESTful modernos em JSON (GET, POST, PUT, DELETE) com tratamento de status HTTP e controle de acesso baseado em cargos.

---

## Segurança implementada

| Proteção | Como | Onde |
|---|---|---|
| Senhas nunca salvas em texto | `password_hash()` / `password_verify()` (bcrypt) | cadastro, login, recuperação |
| SQL injection | Prepared statements (`prepare` + `bind_param`) em todas as queries | todos os arquivos com banco |
| XSS | `htmlspecialchars()` em tudo que é impresso na tela | páginas com saída de dados |
| Acesso indevido à área restrita | Verificação de `$_SESSION` no topo de `painel.php` | painel |
| Escalada de privilégio | 5 validações no servidor antes do `UPDATE` | `processa_tipo.php` |
| Enumeração de usuários | Email errado e senha errada retornam a mesma mensagem | `processa_login.php` |

O tipo do usuário é lido **do banco** a cada carregamento de página, não da sessão. Assim,
se um admin for rebaixado, a mudança vale imediatamente, sem precisar esperar o logout.

### Limitação conhecida

A recuperação de senha pede apenas o email cadastrado, sem confirmar que a pessoa realmente
tem acesso àquela caixa de entrada. Em um sistema real seria necessário enviar um link com
token por email. Isso ficou fora do escopo da atividade, mas é uma limitação assumida.

---

## Problemas comuns

**`Access denied for user 'root'@'localhost'`**
A senha em `conexao.php` não bate com a do seu MySQL. No XAMPP o padrão é senha vazia (`""`).

**`Uncaught mysqli_sql_exception: Duplicate entry ... for key 'usuarios.email'`**
Esse email já está cadastrado — a coluna é `UNIQUE`. Use outro email ou recupere a senha.

**A página abre mas mostra outro site (ou dá 404) em `http://localhost/`**
Já existe um Virtual Host configurado no Apache apontando para outro projeto. Use o servidor
embutido do PHP (Opção B do passo 4) ou configure um Virtual Host para o `htdocs`.

**`Unknown database 'sistema_login'`**
O script `sistema_login.sql` não foi importado. Volte ao passo 2.

**A porta 80 está ocupada e o Apache não inicia**
Normalmente é o Skype, o IIS ou outro servidor. Troque a porta do Apache nas configurações
do XAMPP ou use o servidor embutido do PHP.

---

## Equipe

Trabalho desenvolvido para a disciplina de Desenvolvimento Back-End —
Prof. Állan Stieg Candeia — Curso de Sistemas de Informação.
