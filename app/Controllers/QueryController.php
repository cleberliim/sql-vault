<?php

namespace App\Controllers;

use App\Models\QueryModel;

class QueryController
{
    private QueryModel $model;

    public function __construct()
    {
        $this->model = new QueryModel();
    }

    /**
     * GET /api/queries?search=&filter=&categoria=
     * Retorna lista filtrada + categorias + contadores, tudo em uma
     * única resposta para manter a UI leve e rápida.
     */
    public function index(): void
    {
        $search = $_GET['search'] ?? null;
        $filter = $_GET['filter'] ?? null;
        $categoria = $_GET['categoria'] ?? null;

        json_response([
            'queries'    => $this->model->all($search, $filter, $categoria),
            'categories' => $this->model->categories(),
            'counts'     => $this->model->counts(),
        ]);
    }

    public function show(string $id): void
    {
        $item = $this->model->find((int) $id);

        if (!$item) {
            json_response(['error' => 'Query não encontrada.'], 404);
        }

        json_response($item);
    }

    public function store(): void
    {
        $data = json_body();

        if (clean_str($data['titulo'] ?? '') === '') {
            json_response(['error' => 'O título é obrigatório.'], 422);
        }

        json_response($this->model->create($data), 201);
    }

    public function update(string $id): void
    {
        $data = json_body();

        if (clean_str($data['titulo'] ?? '') === '') {
            json_response(['error' => 'O título é obrigatório.'], 422);
        }

        $item = $this->model->update((int) $id, $data);

        if (!$item) {
            json_response(['error' => 'Query não encontrada.'], 404);
        }

        json_response($item);
    }

    public function destroy(string $id): void
    {
        $ok = $this->model->delete((int) $id);

        json_response(['deleted' => $ok]);
    }

    public function favorite(string $id): void
    {
        $item = $this->model->toggleFavorite((int) $id);

        if (!$item) {
            json_response(['error' => 'Query não encontrada.'], 404);
        }

        json_response($item);
    }

    public function duplicate(string $id): void
    {
        $item = $this->model->duplicate((int) $id);

        if (!$item) {
            json_response(['error' => 'Query não encontrada.'], 404);
        }

        json_response($item, 201);
    }
}
