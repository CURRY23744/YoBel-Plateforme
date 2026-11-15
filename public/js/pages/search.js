(function () {
  'use strict';

  const { $, esc, api, qs, debounce, ready, fail } = App;
  const input = $('#globalSearch');
  const results = $('#searchResults');
  const SECTIONS = [
    { key: 'projects', label: 'Projets', icon: 'bi-kanban', href: (item) => `/projects/${encodeURIComponent(item.id)}`, title: (item) => `${item.reference || ''} · ${item.name}`, detail: (item) => item.status || '' },
    { key: 'files', label: 'Fichiers', icon: 'bi-file-earmark', href: (item) => `/projects/${encodeURIComponent(item.project_id)}`, title: (item) => item.name, detail: (item) => item.category || 'Fichier du projet' },
    { key: 'tasks', label: 'Tâches', icon: 'bi-check2-square', href: (item) => `/projects/${encodeURIComponent(item.project_id)}`, title: (item) => item.title, detail: (item) => item.status || '' },
    { key: 'decisions', label: 'Décisions', icon: 'bi-journal-check', href: (item) => `/projects/${encodeURIComponent(item.project_id)}`, title: (item) => item.title, detail: (item) => item.status || '' },
    { key: 'discussions', label: 'Discussions', icon: 'bi-chat-square-text', href: (item) => item.project_id ? `/projects/${encodeURIComponent(item.project_id)}` : '/space', title: (item) => item.title, detail: () => 'Discussion' },
    { key: 'messages', label: 'Messages', icon: 'bi-chat-dots', href: (item) => item.discussion?.project_id ? `/projects/${encodeURIComponent(item.discussion.project_id)}` : '/space', title: (item) => item.body, detail: (item) => item.discussion?.title || 'Message' },
  ];
  let requestNumber = 0;

  function renderResults(data) {
    const groups = SECTIONS.map((section) => {
      const items = data[section.key] || [];
      if (!items.length) return '';
      return `<section class="mb-4">
        <h2 class="h6 text-uppercase text-muted mb-2">${esc(section.label)} <span class="badge text-bg-light">${items.length}</span></h2>
        <div class="card"><div class="list-group list-group-flush">
          ${items.map((item) => {
            const title = section.title(item) || 'Sans titre';
            const detail = section.detail(item);
            const message = section.key === 'messages' ? item.body : '';
            return `<a class="list-group-item list-group-item-action py-3" href="${esc(section.href(item))}">
              <div class="d-flex gap-3 align-items-start">
                <i class="bi ${section.icon} text-primary fs-5"></i>
                <div class="min-w-0"><div class="fw-semibold text-break">${esc(title)}</div>
                  ${message ? `<div class="small text-muted text-truncate-2">${esc(message.slice(0, 240))}</div>` : ''}
                  ${detail ? `<div class="small text-muted">${esc(detail)}</div>` : ''}
                </div>
                <i class="bi bi-arrow-up-right ms-auto text-muted"></i>
              </div>
            </a>`;
          }).join('')}
        </div></div>
      </section>`;
    }).join('');

    results.innerHTML = groups || '<div class="empty"><i class="bi bi-search"></i>Aucun résultat trouvé pour cette recherche.</div>';
  }

  async function search() {
    const query = input.value.trim();
    if (query.length < 2) {
      results.innerHTML = '<div class="empty"><i class="bi bi-search"></i>Entrez au moins deux caractères pour lancer la recherche.</div>';
      return;
    }

    const requestId = ++requestNumber;
    results.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Recherche en cours…</div>';
    try {
      const data = await api('search', { params: { q: query } });
      if (requestId === requestNumber) renderResults(data);
    } catch (error) {
      if (requestId === requestNumber) {
        results.innerHTML = '<div class="text-center text-danger py-5">La recherche a échoué. Vérifiez votre connexion puis réessayez.</div>';
        fail(error);
      }
    }
  }

  function init() {
    const initialQuery = qs('q');
    if (initialQuery) input.value = initialQuery.slice(0, 100);
    $('#runSearch').addEventListener('click', search);
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        search();
      }
    });
    input.addEventListener('input', debounce(search, 350));
    if (input.value.length >= 2) search();
  }

  ready(init);
})();
