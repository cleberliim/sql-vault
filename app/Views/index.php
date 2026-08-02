<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SQL Vault</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="app">

    <!-- Barra de título -->
    <div class="titlebar">
        <i class="fa-solid fa-vault logo-icon"></i>
        <span class="title">SQL Vault</span>
    </div>

    <!-- Barra de busca e ações -->
    <div class="toolbar">
        <div class="search-box">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" id="search-input" placeholder="Pesquisar consultas..." autocomplete="off">
        </div>
        <button class="btn btn-primary" id="btn-new-query">
            <i class="fa-solid fa-plus"></i> Nova Query
        </button>
        <button class="btn btn-icon" id="btn-settings" title="Configurações">
            <i class="fa-solid fa-gear"></i>
        </button>
    </div>

    <div class="layout">

        <!-- Sidebar de categorias -->
        <aside class="sidebar">
            <div class="sidebar-item active" data-filter="todas">
                <i class="fa-solid fa-table-cells-large"></i>
                <span>Todas</span>
                <span class="count" id="count-todas">0</span>
            </div>
            <div class="sidebar-item" data-filter="favoritas">
                <i class="fa-solid fa-star"></i>
                <span>Favoritas</span>
                <span class="count" id="count-favoritas">0</span>
            </div>

            <div class="sidebar-section-title">
                <span>Categorias</span>
            </div>
            <div id="categories-list"></div>
        </aside>

        <!-- Lista central -->
        <section class="list-panel">
            <div class="list-panel-header">
                <span id="list-count-label">0 consultas</span>
            </div>
            <div class="list-scroll" id="query-list"></div>
        </section>

        <!-- Painel de detalhes / formulário -->
        <section class="detail-panel" id="detail-panel">
            <div class="empty-state" id="empty-state">
                <i class="fa-regular fa-folder-open"></i>
                <span>Selecione uma consulta ao lado<br>ou crie uma nova query.</span>
            </div>
        </section>

    </div>
</div>

<!-- Toast -->
<div class="toast" id="toast">
    <i class="fa-solid fa-circle-check"></i>
    <span id="toast-message">SQL copiado.</span>
</div>

<!-- Modal de confirmação de exclusão -->
<div class="modal-overlay" id="confirm-modal">
    <div class="modal-box">
        <h3>Excluir consulta?</h3>
        <p>Esta ação não pode ser desfeita. A consulta será removida permanentemente do Vault.</p>
        <div class="modal-actions">
            <button class="btn" id="confirm-cancel">Cancelar</button>
            <button class="btn btn-danger" id="confirm-delete">Excluir</button>
        </div>
    </div>
</div>

<!-- Templates -->
<template id="tpl-detail-view">
    <div class="detail-header">
        <div class="detail-title-row">
            <span class="detail-title" data-field="titulo"></span>
            <button class="star-toggle" id="btn-toggle-favorite" title="Favoritar">
                <i class="fa-solid fa-star"></i>
            </button>
        </div>
        <div class="detail-actions">
            <button class="btn" id="btn-copy-sql"><i class="fa-regular fa-copy"></i> Copiar SQL</button>
            <button class="btn" id="btn-edit"><i class="fa-regular fa-pen-to-square"></i> Editar</button>
            <button class="btn" id="btn-duplicate"><i class="fa-regular fa-clone"></i> Duplicar</button>
            <button class="btn btn-danger" id="btn-delete"><i class="fa-regular fa-trash-can"></i> Excluir</button>
        </div>
    </div>
    <div class="detail-meta-row">
        <span class="badge-category" data-field="categoria"></span>
        <div id="detail-tags"></div>
    </div>
    <div class="detail-description">
        <div class="label">Descrição</div>
        <p data-field="descricao"></p>
    </div>
    <div class="editor-wrapper">
        <div id="monaco-container"></div>
    </div>
    <div class="detail-footer">
        <span data-field="stats"></span>
        <span class="dot-lang">SQL</span>
    </div>
</template>

<template id="tpl-detail-form">
    <div class="detail-header">
        <div class="detail-title-row">
            <span class="detail-title" id="form-title-label">Nova Query</span>
        </div>
        <div class="detail-actions">
            <button class="btn" id="btn-cancel-form">Cancelar</button>
            <button class="btn btn-primary" id="btn-save-form"><i class="fa-solid fa-floppy-disk"></i> Salvar</button>
        </div>
    </div>
    <div class="detail-form">
        <div class="form-row">
            <label for="input-titulo">Título</label>
            <input type="text" id="input-titulo" placeholder="Ex: Buscar veículos ativos">
        </div>
        <div class="form-row-inline">
            <div class="form-row">
                <label for="input-categoria">Categoria</label>
                <input type="text" id="input-categoria" placeholder="Ex: Frota" list="categorias-datalist">
                <datalist id="categorias-datalist"></datalist>
            </div>
            <div class="form-row">
                <label for="input-tags">Tags (separadas por vírgula)</label>
                <input type="text" id="input-tags" placeholder="join, update, placa">
            </div>
        </div>
        <div class="form-row">
            <label for="input-descricao">Descrição</label>
            <textarea id="input-descricao" placeholder="O que essa consulta faz?"></textarea>
        </div>
        <div class="form-row">
            <label>SQL</label>
            <div class="form-editor-wrapper">
                <div id="monaco-form-container" style="width:100%;height:100%;"></div>
            </div>
        </div>
    </div>
</template>

<script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs/loader.min.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
