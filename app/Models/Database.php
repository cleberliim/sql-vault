<?php

namespace App\Models;

use PDO;
use PDOException;

/**
 * Conexão única (singleton) com o banco SQLite via PDO.
 * Cria o arquivo storage/vault.db e a tabela `queries` automaticamente
 * na primeira execução.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/config.php';
            $dbPath = $config['db_path'];

            $dbDir = dirname($dbPath);
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0775, true);
            }

            try {
                $pdo = new PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $pdo->exec('PRAGMA foreign_keys = ON');
                $pdo->exec('PRAGMA journal_mode = WAL');
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['error' => 'Falha ao conectar ao banco: ' . $e->getMessage()]));
            }

            self::$instance = $pdo;
            self::migrate($pdo);
        }

        return self::$instance;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS queries (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                titulo        TEXT NOT NULL,
                categoria     TEXT NOT NULL DEFAULT 'Geral',
                descricao     TEXT NOT NULL DEFAULT '',
                tags          TEXT NOT NULL DEFAULT '',
                sql_text      TEXT NOT NULL DEFAULT '',
                favorito      INTEGER NOT NULL DEFAULT 0,
                criado_em     TEXT NOT NULL,
                atualizado_em TEXT NOT NULL
            )
        ");

        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_queries_categoria ON queries(categoria)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_queries_favorito ON queries(favorito)");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_queries_titulo ON queries(titulo)");
    }
}
