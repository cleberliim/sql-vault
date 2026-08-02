<?php

// Em produção (build Tauri), a pasta de instalação costuma ser somente
// leitura, então o processo Rust (src-tauri/src/main.rs) passa a pasta
// de dados gravável do usuário via variável de ambiente VAULT_DB_DIR.
// Rodando localmente com `php -S` (fora do Tauri), cai no padrão
// storage/vault.db dentro do próprio projeto.
$dataDir = getenv('VAULT_DB_DIR');
$dbPath = $dataDir !== false
    ? rtrim($dataDir, '/\\') . DIRECTORY_SEPARATOR . 'vault.db'
    : __DIR__ . '/../storage/vault.db';

return [
    'app_name' => 'SQL Vault',
    'db_path'  => $dbPath,
    'debug'    => true,
];
