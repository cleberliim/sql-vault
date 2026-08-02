<?php

namespace App\Models;

use PDO;

class QueryModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    /**
     * Lista queries, com filtro opcional de busca (titulo, categoria,
     * descricao, tags, sql_text) e filtro de favoritas.
     */
    public function all(?string $search = null, ?string $filter = null, ?string $categoria = null): array
    {
        $sql = "SELECT * FROM queries WHERE 1=1";
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= " AND (titulo LIKE :s1 OR categoria LIKE :s2 OR descricao LIKE :s3 OR tags LIKE :s4 OR sql_text LIKE :s5)";
            $term = '%' . $search . '%';
            $params[':s1'] = $term;
            $params[':s2'] = $term;
            $params[':s3'] = $term;
            $params[':s4'] = $term;
            $params[':s5'] = $term;
        }

        if ($filter === 'favoritas') {
            $sql .= " AND favorito = 1";
        }

        if ($categoria !== null && $categoria !== '') {
            $sql .= " AND categoria = :categoria";
            $params[':categoria'] = $categoria;
        }

        $sql .= " ORDER BY titulo COLLATE NOCASE ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM queries WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function create(array $data): array
    {
        $now = now_datetime();

        $stmt = $this->db->prepare("
            INSERT INTO queries (titulo, categoria, descricao, tags, sql_text, favorito, criado_em, atualizado_em)
            VALUES (:titulo, :categoria, :descricao, :tags, :sql_text, :favorito, :criado_em, :atualizado_em)
        ");

        $stmt->execute([
            ':titulo'        => clean_str($data['titulo'] ?? ''),
            ':categoria'     => clean_str($data['categoria'] ?? '') ?: 'Geral',
            ':descricao'     => clean_str($data['descricao'] ?? ''),
            ':tags'          => clean_str($data['tags'] ?? ''),
            ':sql_text'      => (string) ($data['sql_text'] ?? ''),
            ':favorito'      => !empty($data['favorito']) ? 1 : 0,
            ':criado_em'     => $now,
            ':atualizado_em' => $now,
        ]);

        return $this->find((int) $this->db->lastInsertId());
    }

    public function update(int $id, array $data): ?array
    {
        $existing = $this->find($id);

        if (!$existing) {
            return null;
        }

        $stmt = $this->db->prepare("
            UPDATE queries SET
                titulo = :titulo,
                categoria = :categoria,
                descricao = :descricao,
                tags = :tags,
                sql_text = :sql_text,
                atualizado_em = :atualizado_em
            WHERE id = :id
        ");

        $stmt->execute([
            ':titulo'        => clean_str($data['titulo'] ?? $existing['titulo']),
            ':categoria'     => clean_str($data['categoria'] ?? $existing['categoria']) ?: 'Geral',
            ':descricao'     => clean_str($data['descricao'] ?? $existing['descricao']),
            ':tags'          => clean_str($data['tags'] ?? $existing['tags']),
            ':sql_text'      => (string) ($data['sql_text'] ?? $existing['sql_text']),
            ':atualizado_em' => now_datetime(),
            ':id'            => $id,
        ]);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM queries WHERE id = :id");

        return $stmt->execute([':id' => $id]);
    }

    public function toggleFavorite(int $id): ?array
    {
        $existing = $this->find($id);

        if (!$existing) {
            return null;
        }

        $newValue = $existing['favorito'] ? 0 : 1;

        $stmt = $this->db->prepare("UPDATE queries SET favorito = :fav, atualizado_em = :dt WHERE id = :id");
        $stmt->execute([':fav' => $newValue, ':dt' => now_datetime(), ':id' => $id]);

        return $this->find($id);
    }

    public function duplicate(int $id): ?array
    {
        $existing = $this->find($id);

        if (!$existing) {
            return null;
        }

        return $this->create([
            'titulo'    => $existing['titulo'] . ' (cópia)',
            'categoria' => $existing['categoria'],
            'descricao' => $existing['descricao'],
            'tags'      => $existing['tags'],
            'sql_text'  => $existing['sql_text'],
            'favorito'  => 0,
        ]);
    }

    /**
     * Categorias distintas cadastradas, com contagem de queries em cada uma.
     */
    public function categories(): array
    {
        $stmt = $this->db->query("
            SELECT categoria, COUNT(*) AS total
            FROM queries
            GROUP BY categoria
            ORDER BY categoria COLLATE NOCASE ASC
        ");

        return $stmt->fetchAll();
    }

    public function counts(): array
    {
        $total = (int) $this->db->query("SELECT COUNT(*) FROM queries")->fetchColumn();
        $favoritas = (int) $this->db->query("SELECT COUNT(*) FROM queries WHERE favorito = 1")->fetchColumn();

        return ['todas' => $total, 'favoritas' => $favoritas];
    }
}
