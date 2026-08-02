# SQL Vault

Biblioteca pessoal para armazenar, organizar e pesquisar consultas SQL.

O SQL Vault permite cadastrar, editar, pesquisar, favoritar e copiar queries de forma rápida, utilizando um banco SQLite local. A aplicação não executa consultas nem realiza conexões com bancos de dados.

## Tecnologias

- PHP 8.3
- SQLite (PDO)
- Tauri
- HTML5
- CSS3
- JavaScript (Vanilla)
- Monaco Editor
- Font Awesome

## Requisitos

- PHP 8.3 ou superior
- Extensão `pdo_sqlite` habilitada
- Node.js
- Rust (para compilação do executável)

## Estrutura do projeto

```text
/app
    Controllers
    Models
    Views

/assets
    css
    js
    icons

/config
    autoload.php
    config.php

/database

/routes
    web.php

/storage
    vault.db

/src-tauri

index.php
```

## Instalação

### 1. Instale as dependências

```bash
npm install
```

### 2. Execute o servidor PHP

```bash
php -S 127.0.0.1:8756 index.php
```

Abra:

```text
http://127.0.0.1:8756
```

Na primeira execução será criado automaticamente:

```text
storage/vault.db
```

## Executando com Tauri

Modo desenvolvimento:

```bash
npm run dev
```

Gerar instalador:

```bash
npm run build
```

O instalador será gerado em:

```text
src-tauri/target/release/bundle/
```

## Banco de dados

Durante o desenvolvimento:

```text
storage/vault.db
```

Após a instalação do aplicativo:

```text
%APPDATA%\cleberlima.sqlvaul\
```

> O nome da pasta depende do `identifier` definido em `src-tauri/tauri.conf.json`.

## Configuração

As configurações da aplicação ficam em:

```text
config/config.php
```

Nesse arquivo é possível alterar:

- caminho do banco SQLite;
- configurações gerais da aplicação;
- variáveis utilizadas pelo ambiente Tauri.

## API interna

| Método | Endpoint | Descrição |
|---------|----------|-----------|
| GET | `/api/queries` | Listar consultas |
| GET | `/api/queries/{id}` | Obter consulta |
| POST | `/api/queries` | Criar consulta |
| PUT | `/api/queries/{id}` | Atualizar consulta |
| DELETE | `/api/queries/{id}` | Excluir consulta |
| POST | `/api/queries/{id}/favorite` | Favoritar |
| POST | `/api/queries/{id}/duplicate` | Duplicar |

## Dependências para compilação

Instale:

- Rust
- Cargo
- Dependências do Tauri v2
- Node.js

Depois execute:

```bash
npm install
npm run build
```

## Licença

Projeto para uso pessoal e interno.
