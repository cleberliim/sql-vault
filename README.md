# SQL Vault

Biblioteca pessoal de consultas SQL. Guarde, pesquise, edite e copie suas
queries do dia a dia. Sem execução de queries, sem conexão a banco, sem IA.

## Stack

PHP 8.3 · SQLite (PDO) · Tauri · HTML5 · CSS · JavaScript Vanilla · Monaco Editor · Font Awesome

## Estrutura

```
/app
    /Controllers   → QueryController (endpoints JSON)
    /Models        → Database (conexão + migração), QueryModel (CRUD)
    /Views         → index.php (shell HTML da SPA)
/config
    config.php     → configuração da aplicação (caminho do banco, etc.)
    autoload.php   → autoloader PSR-4-like para o namespace App\
/database          → reservado (migrações futuras, se necessário)
/public             → reservado (não usado no MVP — assets são servidos
                       diretamente da pasta /assets pelo router index.php)
/assets
    /css/style.css → tema escuro
    /js/app.js     → toda a lógica da SPA (fetch, busca, Monaco, CRUD)
    /icons         → ícones do app (a preencher)
/storage
    vault.db       → banco SQLite (criado automaticamente na 1ª execução)
/routes
    web.php        → tabela de rotas (view + API REST)
/src-tauri          → wrapper desktop (Rust) — ver seção Tauri abaixo
index.php           → front controller / router script do PHP
```

## Rodando localmente (sem Tauri, direto no navegador)

Requer PHP 8.3+ com extensão `pdo_sqlite`.

```bash
php -S 127.0.0.1:8756 index.php
```

Abra http://127.0.0.1:8756 no navegador. O banco `storage/vault.db` é
criado automaticamente no primeiro request.

## API REST (usada internamente pela SPA)

| Método | Rota                          | Ação                              |
|--------|-------------------------------|------------------------------------|
| GET    | /api/queries?search=&filter=&categoria= | lista + categorias + contadores |
| GET    | /api/queries/{id}             | detalhe de uma query              |
| POST   | /api/queries                  | criar                              |
| PUT    | /api/queries/{id}             | atualizar                          |
| DELETE | /api/queries/{id}             | excluir                            |
| POST   | /api/queries/{id}/favorite    | alternar favorito                  |
| POST   | /api/queries/{id}/duplicate   | duplicar                           |

## Onde fica o banco de dados (vault.db)

O caminho do banco muda dependendo de como você está rodando o app —
isso confundiu bastante durante os testes, então fica documentado aqui
de uma vez por todas.

| Como você roda o app                         | Onde fica o `vault.db`                                            |
|-----------------------------------------------|---------------------------------------------------------------------|
| `.exe` instalado (produção, `npm run build`)   | `%APPDATA%\cleberlima.sqlvaul\vault.db` *(veja nota do typo abaixo)* |
| `npm run dev` (`tauri dev`)                    | `src-tauri\php\...` **não** — continua em `storage\vault.db` na raiz do projeto |
| `php -S 127.0.0.1:8756 index.php` (sem Tauri)  | `storage\vault.db` na raiz do projeto                                |

Essa lógica está em `config/config.php`: se a variável de ambiente
`VAULT_DB_DIR` existir (só existe quando o app foi iniciado pelo
`main.rs`/Tauri), usa `<VAULT_DB_DIR>\vault.db`; senão cai no padrão
`storage/vault.db`.

**⚠️ Nota sobre o identificador do app:** o `identifier` em
`src-tauri/tauri.conf.json` está gravado como `cleberlima.sqlvaul`
(sem o "t" final — typo de digitação). É esse valor que o Tauri usa pra
nomear a pasta em `%APPDATA%`, então o caminho real observado é
`C:\Users\<usuário>\AppData\Roaming\cleberlima.sqlvaul\vault.db`,
**não** `...sqlvault`. Se quiser corrigir o identifier pra ficar sem
o typo, dá pra fazer, mas isso muda o caminho da pasta de dados — quem já
tiver usado o app precisaria mover o `vault.db` manualmente pra pasta
nova depois da troca.

**Não sei onde está o meu banco de verdade?** Roda isso no PowerShell —
ele varre `AppData\Roaming` e `AppData\Local` procurando qualquer
`vault.db`:

```powershell
Get-ChildItem -Path "$env:APPDATA","$env:LOCALAPPDATA" -Filter "vault.db" -Recurse -ErrorAction SilentlyContinue | Select-Object FullName
```

### Editando o banco diretamente / adicionando dados em massa

O `vault.db` é um arquivo SQLite comum — dá pra abrir com qualquer
cliente SQLite (ex.: [DB Browser for SQLite](https://sqlitebrowser.org/))
apontando pro caminho encontrado acima. **Feche o app antes de editar o
arquivo direto**, para evitar conflito de escrita (o banco roda em modo
WAL — journal em `vault.db-wal`/`vault.db-shm` ao lado do `.db`).

Pra inserir várias queries de uma vez sem precisar de um cliente SQLite
instalado, dá pra usar o próprio `php.exe` embutido do projeto com um
script simples de `INSERT`s. Exemplo (schema completo da tabela
`queries` em `app/Models/Database.php`):

```powershell
& "C:\wamp64\www\-sql-vault\src-tauri\target\release\php\php.exe" caminho\para\seu_script.php "C:\caminho\completo\para\vault.db" "C:\caminho\completo\para\seu_arquivo.sql"
```

onde `seu_script.php` só precisa abrir o `vault.db` via
`new PDO('sqlite:' . $caminho)` e rodar `$pdo->exec(file_get_contents($sqlPath))`
com os `INSERT INTO queries (...) VALUES (...)` do arquivo `.sql`.



O `src-tauri/` está pronto como **esqueleto**, mas não foi compilado nem
testado neste ambiente (o sandbox usado para gerar este projeto não tem
o toolchain Rust/Cargo instalado). Antes de gerar o executável final, você
vai precisar, na sua máquina Windows:

1. Instalar o [Rust](https://rustup.rs/) e as dependências do Tauri v2
   ([pré-requisitos aqui](https://v2.tauri.app/start/prerequisites/)).
2. `npm install` na raiz do projeto (instala o `@tauri-apps/cli`).
3. `npm run dev` para rodar em modo desenvolvimento, ou `npm run build`
   para gerar o instalador NSIS (.exe) em `src-tauri/target/release/bundle`.

### Correções aplicadas após o primeiro teste de build

- `frontendDist` apontava para a raiz do projeto (`"../"`), o que incluía
  `node_modules/`, `src-tauri/` e `src-tauri/target/` — o Tauri recusa
  empacotar isso (`tauri build` falhava). Corrigido: `frontendDist` agora
  aponta para `src-tauri/dist/` (uma pasta isolada com um `index.html`
  placeholder, nunca exibido — a janela real é apontada manualmente para
  o servidor PHP local em `main.rs`). Os arquivos PHP de verdade
  (`index.php`, `app/`, `routes/`, `config/`, `assets/`) são copiados para
  dentro do bundle via `bundle.resources` no `tauri.conf.json`.
- O banco `storage/vault.db` não pode viver dentro da pasta de instalação
  em produção (normalmente somente leitura, ex.: `Program Files`). Agora
  `main.rs` resolve a pasta de dados do usuário (`app_data_dir()`, ex.:
  `%APPDATA%\cleberlima.sqlvaul` no Windows — veja a seção
  "Onde fica o banco de dados" acima para detalhes sobre esse nome) e
  passa para o PHP
  via variável de ambiente `VAULT_DB_DIR`; `config/config.php` usa essa
  variável quando presente e cai no `storage/vault.db` local quando você
  roda só com `php -S` (fora do Tauri).

### Ponto de atenção real (ainda em aberto)

O `src-tauri/src/main.rs` sobe o servidor PHP embutido (`php -S`) como
processo filho e aponta a janela do Tauri para ele. **Isso exige que o
PHP esteja instalado no PATH da máquina do usuário final** — o que
normalmente não é verdade para um usuário comum do Windows baixando um
.exe. Duas soluções, nenhuma implementada aqui porque exigem decisão sua:

- **Opção A (mais simples):** documentar que o app requer PHP 8.3+
  instalado (aceitável se for uso pessoal/interno, como parece ser o caso).
- **Opção B (mais robusta):** embarcar um binário PHP portátil para
  Windows como *sidecar* do Tauri (configurável em `tauri.conf.json` →
  `bundle.externalBin`) e trocar `Command::new("php")` por esse binário.
  Isso elimina a dependência externa mas aumenta o tamanho do instalador.

Se quiser, eu implemento a Opção B no próximo passo.

## Fora do escopo desta versão (aparecem na imagem de referência, mas não no documento de especificação)

A imagem de referência que você enviou mostra alguns elementos que **não
estão no documento de especificação funcional** e por isso não foram
implementados, para não fugir do MVP descrito por escrito:

- **Lixeira** (exclusão suave / soft-delete) — o schema pedido não tem
  campo de exclusão lógica; implementei exclusão definitiva.
- **"Últimas aberturas"** (histórico de quando cada query foi aberta) —
  exigiria um campo/tabela extra não previsto no schema.
- **Badge de dialeto (ex.: "SQL Server")** no rodapé do editor — o schema
  não tem campo de dialeto; o rodapé atual mostra "SQL" fixo.
- **Ordenar / Filtro avançado** na lista — implementei apenas a ordenação
  alfabética fixa por título, que já estava implícita no requisito de
  "biblioteca organizada".

Nenhuma dessas é complexa de adicionar — são só um campo de schema e uma
query a mais em cada caso — mas prefiro confirmar com você antes de
expandir o schema além do que foi especificado.
