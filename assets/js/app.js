(function () {
    'use strict';

    // ===== Estado da aplicação =====
    const state = {
        search: '',
        filter: 'todas',      // 'todas' | 'favoritas'
        categoria: null,      // string | null
        selectedId: null,
        mode: 'empty',         // 'empty' | 'view' | 'edit' | 'new'
        queries: [],
        categories: [],
    };

    let monacoReady = false;
    let viewEditor = null;
    let formEditor = null;
    let searchDebounceTimer = null;
    let pendingDeleteId = null;

    // ===== Elementos fixos =====
    const el = {
        searchInput: document.getElementById('search-input'),
        btnNewQuery: document.getElementById('btn-new-query'),
        queryList: document.getElementById('query-list'),
        listCountLabel: document.getElementById('list-count-label'),
        categoriesList: document.getElementById('categories-list'),
        countTodas: document.getElementById('count-todas'),
        countFavoritas: document.getElementById('count-favoritas'),
        detailPanel: document.getElementById('detail-panel'),
        toast: document.getElementById('toast'),
        toastMessage: document.getElementById('toast-message'),
        confirmModal: document.getElementById('confirm-modal'),
        confirmCancel: document.getElementById('confirm-cancel'),
        confirmDelete: document.getElementById('confirm-delete'),
    };

    // ===== Monaco loader (AMD via cdnjs) =====
    // Protegido: se o CDN falhar (sem internet, firewall, DNS, etc.), o resto do app
    // (botões, listeners, CRUD) continua funcionando normalmente — só o editor de código
    // fica indisponível e cai no <textarea> de fallback (ver renderDetail/renderFormMode).
    try {
        if (typeof require === 'undefined') {
            throw new Error('RequireJS (loader.min.js) não carregou — provável falta de internet/CDN bloqueado.');
        }
        require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs' } });
        require([ 'vs/editor/editor.main' ], function () {
            monaco.editor.defineTheme('vault-dark', {
                base: 'vs-dark',
                inherit: true,
                rules: [],
                colors: {
                    'editor.background': '#12151c',
                    'editor.lineHighlightBackground': '#161a22',
                },
            });
            monacoReady = true;
            // Se o usuário já clicou em algo antes do Monaco carregar, renderiza agora.
            renderDetail();
        }, function (err) {
            console.error('Falha ao carregar módulo do Monaco Editor:', err);
        });
    } catch (err) {
        console.error('Monaco Editor indisponível, seguindo sem editor avançado:', err);
    }

    // ===== Utilidades =====

    function debounce(fn, delay) {
        return function (...args) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(() => fn(...args), delay);
        };
    }

    function showToast(message) {
        el.toastMessage.textContent = message;
        el.toast.classList.add('show');
        clearTimeout(showToast._t);
        showToast._t = setTimeout(() => el.toast.classList.remove('show'), 2200);
    }

    function formatDate(sqlDate) {
        if (!sqlDate) return '';
        const [ datePart, timePart ] = sqlDate.split(' ');
        const [ y, m, d ] = datePart.split('-');
        return `${d}/${m}/${y}${timePart ? ' ' + timePart.slice(0, 5) : ''}`;
    }

    async function api(path, options = {}) {
        const res = await fetch(path, {
            headers: { 'Content-Type': 'application/json' },
            ...options,
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok) {
            throw new Error(data.error || 'Erro na requisição.');
        }
        return data;
    }

    // ===== Carregamento de dados =====

    async function loadList() {
        const params = new URLSearchParams();
        if (state.search) params.set('search', state.search);
        if (state.filter === 'favoritas') params.set('filter', 'favoritas');
        if (state.categoria) params.set('categoria', state.categoria);

        const data = await api('/api/queries?' + params.toString());
        state.queries = data.queries;
        state.categories = data.categories;

        el.countTodas.textContent = data.counts.todas;
        el.countFavoritas.textContent = data.counts.favoritas;

        renderCategories();
        renderList();
        renderCategoriesDatalist();
    }

    // ===== Renderização: Sidebar de categorias =====

    function renderCategories() {
        el.categoriesList.innerHTML = '';
        state.categories.forEach((cat) => {
            const item = document.createElement('div');
            item.className = 'sidebar-item' + (state.filter === 'categoria' && state.categoria === cat.categoria ? ' active' : '');
            item.innerHTML = `
                <i class="fa-regular fa-folder"></i>
                <span>${escapeHtml(cat.categoria)}</span>
                <span class="count">${cat.total}</span>
            `;
            item.addEventListener('click', () => {
                state.filter = 'categoria';
                state.categoria = cat.categoria;
                updateSidebarActiveState();
                loadList();
            });
            el.categoriesList.appendChild(item);
        });
    }

    function renderCategoriesDatalist() {
        const datalist = document.getElementById('categorias-datalist');
        if (!datalist) return;
        datalist.innerHTML = state.categories
            .map((c) => `<option value="${escapeAttr(c.categoria)}">`)
            .join('');
    }

    function updateSidebarActiveState() {
        document.querySelectorAll('.sidebar-item[data-filter]').forEach((it) => {
            const f = it.getAttribute('data-filter');
            it.classList.toggle('active', state.filter === f);
        });
        document.querySelectorAll('#categories-list .sidebar-item').forEach((it, idx) => {
            const cat = state.categories[ idx ];
            it.classList.toggle('active', state.filter === 'categoria' && cat && state.categoria === cat.categoria);
        });
    }

    document.querySelectorAll('.sidebar-item[data-filter]').forEach((item) => {
        item.addEventListener('click', () => {
            state.filter = item.getAttribute('data-filter');
            state.categoria = null;
            updateSidebarActiveState();
            loadList();
        });
    });

    // ===== Renderização: Lista central =====

    function renderList() {
        el.listCountLabel.textContent = state.queries.length === 1
            ? '1 consulta'
            : `${state.queries.length} consultas`;

        el.queryList.innerHTML = '';

        if (state.queries.length === 0) {
            el.queryList.innerHTML = `
                <div class="empty-state" style="height:200px;">
                    <i class="fa-regular fa-face-frown"></i>
                    <span>Nenhuma consulta encontrada.</span>
                </div>`;
            return;
        }

        state.queries.forEach((q) => {
            const item = document.createElement('div');
            item.className = 'list-item' + (state.selectedId === q.id ? ' active' : '');
            item.dataset.id = q.id;

            const tags = (q.tags || '')
                .split(',')
                .map((t) => t.trim())
                .filter(Boolean)
                .slice(0, 3);

            item.innerHTML = `
                <div class="list-item-top">
                    <i class="fa-regular fa-file-lines"></i>
                    <span class="list-item-title${q.titulo ? '' : ' empty-title'}">${escapeHtml(q.titulo || '(sem título)')}</span>
                    ${q.favorito ? '<i class="fa-solid fa-star"></i>' : ''}
                </div>
                <div class="list-item-meta">
                    <span class="badge">${escapeHtml(q.categoria)}</span>
                    ${tags.map((t) => `<span class="badge">${escapeHtml(t)}</span>`).join('')}
                    <span class="list-item-date">${formatDate(q.atualizado_em)}</span>
                </div>
            `;

            item.addEventListener('click', () => selectQuery(q.id));
            el.queryList.appendChild(item);
        });
    }

    // ===== Seleção / Renderização do painel de detalhes =====

    async function selectQuery(id) {
        state.selectedId = id;
        state.mode = 'view';
        renderList();
        renderDetail();
    }

    function renderDetail() {
        el.detailPanel.innerHTML = '';

        if (state.mode === 'empty' || (!state.selectedId && state.mode !== 'new')) {
            el.detailPanel.innerHTML = `
                <div class="empty-state">
                    <i class="fa-regular fa-folder-open"></i>
                    <span>Selecione uma consulta ao lado<br>ou crie uma nova query.</span>
                </div>`;
            return;
        }

        const query = state.queries.find((q) => q.id === state.selectedId);

        if (state.mode === 'view' && query) {
            renderViewMode(query);
        } else if (state.mode === 'edit' && query) {
            renderFormMode(query);
        } else if (state.mode === 'new') {
            renderFormMode(null);
        }
    }

    function renderViewMode(query) {
        const tpl = document.getElementById('tpl-detail-view').content.cloneNode(true);

        tpl.querySelector('[data-field="titulo"]').textContent = query.titulo;
        tpl.querySelector('[data-field="categoria"]').textContent = query.categoria;
        tpl.querySelector('[data-field="descricao"]').textContent = query.descricao || 'Sem descrição.';

        const lines = (query.sql_text || '').split('\n').length;
        const chars = (query.sql_text || '').length;
        tpl.querySelector('[data-field="stats"]').textContent = `Linhas: ${lines}    Caracteres: ${chars}`;

        const tagsContainer = tpl.querySelector('#detail-tags');
        const tags = (query.tags || '').split(',').map((t) => t.trim()).filter(Boolean);
        tagsContainer.innerHTML = tags.map((t) => `<span class="badge-tag">#${escapeHtml(t)}</span>`).join('');

        const starBtn = tpl.querySelector('#btn-toggle-favorite');
        if (query.favorito) starBtn.classList.add('active');

        el.detailPanel.appendChild(tpl);

        // Editor somente leitura
        const container = document.getElementById('monaco-container');
        if (monacoReady) {
            viewEditor = monaco.editor.create(container, {
                value: query.sql_text || '',
                language: 'sql',
                theme: 'vault-dark',
                readOnly: true,
                minimap: { enabled: false },
                fontFamily: "'JetBrains Mono', 'Fira Code', Consolas, monospace",
                fontSize: 13,
                automaticLayout: true,
                scrollBeyondLastLine: false,
                lineNumbersMinChars: 3,
            });
        } else {
            // Fallback sem Monaco (sem internet/CDN indisponível): textarea simples.
            const fallback = document.createElement('textarea');
            fallback.className = 'monaco-fallback-textarea';
            fallback.readOnly = true;
            fallback.value = query.sql_text || '';
            fallback.style.cssText = 'width:100%;height:100%;resize:none;background:#12151c;color:#e6e6e6;border:none;padding:12px;font-family:"JetBrains Mono","Fira Code",Consolas,monospace;font-size:13px;';
            container.appendChild(fallback);
        }

        document.getElementById('btn-copy-sql').addEventListener('click', () => copySql(query.sql_text));
        document.getElementById('btn-edit').addEventListener('click', () => {
            state.mode = 'edit';
            renderDetail();
        });
        document.getElementById('btn-duplicate').addEventListener('click', () => duplicateQuery(query.id));
        document.getElementById('btn-delete').addEventListener('click', () => askDelete(query.id));
        document.getElementById('btn-toggle-favorite').addEventListener('click', () => toggleFavorite(query.id));
    }

    function renderFormMode(query) {
        const tpl = document.getElementById('tpl-detail-form').content.cloneNode(true);
        const isNew = !query;

        tpl.querySelector('#form-title-label').textContent = isNew ? 'Nova Query' : 'Editar Query';

        el.detailPanel.appendChild(tpl);

        const inputTitulo = document.getElementById('input-titulo');
        const inputCategoria = document.getElementById('input-categoria');
        const inputTags = document.getElementById('input-tags');
        const inputDescricao = document.getElementById('input-descricao');

        if (query) {
            inputTitulo.value = query.titulo;
            inputCategoria.value = query.categoria;
            inputTags.value = query.tags;
            inputDescricao.value = query.descricao;
        }

        const editorContainer = document.getElementById('monaco-form-container');
        let fallbackTextarea = null;
        if (monacoReady) {
            formEditor = monaco.editor.create(editorContainer, {
                value: query ? query.sql_text : '',
                language: 'sql',
                theme: 'vault-dark',
                minimap: { enabled: false },
                fontFamily: "'JetBrains Mono', 'Fira Code', Consolas, monospace",
                fontSize: 13,
                automaticLayout: true,
                scrollBeyondLastLine: false,
                lineNumbersMinChars: 3,
            });
        } else {
            // Fallback sem Monaco (sem internet/CDN indisponível): textarea editável simples.
            fallbackTextarea = document.createElement('textarea');
            fallbackTextarea.className = 'monaco-fallback-textarea';
            fallbackTextarea.value = query ? (query.sql_text || '') : '';
            fallbackTextarea.style.cssText = 'width:100%;height:100%;resize:none;background:#12151c;color:#e6e6e6;border:none;padding:12px;font-family:"JetBrains Mono","Fira Code",Consolas,monospace;font-size:13px;';
            editorContainer.appendChild(fallbackTextarea);
        }

        if (isNew) {
            setTimeout(() => inputTitulo.focus(), 50);
        }

        document.getElementById('btn-cancel-form').addEventListener('click', () => {
            state.mode = query ? 'view' : 'empty';
            renderDetail();
        });

        document.getElementById('btn-save-form').addEventListener('click', () => {
            saveForm(query ? query.id : null, {
                titulo: inputTitulo.value,
                categoria: inputCategoria.value,
                tags: inputTags.value,
                descricao: inputDescricao.value,
                sql_text: formEditor ? formEditor.getValue() : (fallbackTextarea ? fallbackTextarea.value : ''),
            });
        });
    }

    // ===== Ações: CRUD =====

    async function saveForm(id, payload) {
        if (!payload.titulo.trim()) {
            showToast('O título é obrigatório.');
            return;
        }

        try {
            let result;
            if (id) {
                result = await api(`/api/queries/${id}`, { method: 'PUT', body: JSON.stringify(payload) });
            } else {
                result = await api('/api/queries', { method: 'POST', body: JSON.stringify(payload) });
            }

            await loadList();
            state.selectedId = result.id;
            state.mode = 'view';
            renderList();
            renderDetail();
            showToast(id ? 'Query atualizada.' : 'Query criada.');
        } catch (err) {
            showToast(err.message);
        }
    }

    async function toggleFavorite(id) {
        try {
            await api(`/api/queries/${id}/favorite`, { method: 'POST' });
            await loadList();
            renderDetail();
        } catch (err) {
            showToast(err.message);
        }
    }

    async function duplicateQuery(id) {
        try {
            const result = await api(`/api/queries/${id}/duplicate`, { method: 'POST' });
            await loadList();
            state.selectedId = result.id;
            state.mode = 'view';
            renderList();
            renderDetail();
            showToast('Query duplicada.');
        } catch (err) {
            showToast(err.message);
        }
    }

    function askDelete(id) {
        pendingDeleteId = id;
        el.confirmModal.classList.add('show');
    }

    el.confirmCancel.addEventListener('click', () => {
        pendingDeleteId = null;
        el.confirmModal.classList.remove('show');
    });

    el.confirmDelete.addEventListener('click', async () => {
        if (!pendingDeleteId) return;
        try {
            await api(`/api/queries/${pendingDeleteId}`, { method: 'DELETE' });
            if (state.selectedId === pendingDeleteId) {
                state.selectedId = null;
                state.mode = 'empty';
            }
            await loadList();
            renderDetail();
            showToast('Query excluída.');
        } catch (err) {
            showToast(err.message);
        } finally {
            pendingDeleteId = null;
            el.confirmModal.classList.remove('show');
        }
    });

    function copySql(sql) {
        navigator.clipboard.writeText(sql || '').then(() => {
            showToast('SQL copiado.');
        }).catch(() => {
            showToast('Não foi possível copiar o SQL.');
        });
    }

    // ===== Busca em tempo real =====

    const handleSearch = debounce((value) => {
        state.search = value;
        loadList();
    }, 150);

    el.searchInput.addEventListener('input', (e) => handleSearch(e.target.value));

    // ===== Nova Query =====

    el.btnNewQuery.addEventListener('click', () => {
        state.selectedId = null;
        state.mode = 'new';
        renderList();
        renderDetail();
    });

    // ===== Helpers de escape =====

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return escapeHtml(str).replace(/"/g, '&quot;');
    }

    // ===== Inicialização =====

    document.addEventListener('DOMContentLoaded', () => {
        el.searchInput.focus();
    });

    // Executa mesmo se DOMContentLoaded já disparou (script no fim do body)
    el.searchInput.focus();
    loadList();
})();