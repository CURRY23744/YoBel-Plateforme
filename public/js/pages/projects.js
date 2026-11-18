(function () {
  'use strict';

  const { $, $$, api, esc, L, fmtDate, formModal, field, pager, confirm: confirmBox, toast, fail } = App;
  const rows = $('#projectRows');
  const pageSize = 20;
  let page = 1;
  let types = [];
  let currentUser;

  const STATUSES = [
    ['idea', 'Idée'],
    ['feasibility', 'Étude de faisabilité'],
    ['validated', 'Validé'],
    ['in_progress', 'En cours'],
    ['on_hold', 'En pause'],
    ['completed', 'Terminé'],
    ['abandoned', 'Abandonné'],
    ['archived', 'Archivé'],
  ];
  const PRIORITIES = [['low', 'Faible'], ['medium', 'Moyenne'], ['high', 'Haute'], ['critical', 'Critique']];
  const asOptions = (items) => items.map(([value, label]) => [value, label]);
  const typeLabel = (project) => project.type?.name || types.find((type) => type.id === project.project_type_id)?.name || '—';

  function currentFilters() {
    return {
      q: $('#projectSearch').value.trim(),
      from: $('#projectDateFrom').value,
      to: $('#projectDateTo').value,
      project_type_id: $('#projectType').value,
      status: $('#projectStatus').value,
      sort: $('#projectSort').value,
      direction: $('#projectDirection').value,
      include_archived: $('#includeArchived').checked,
      overdue: $('#overdueOnly').checked,
      per_page: pageSize,
      page,
    };
  }

  function syncUrl(filters) {
    const params = new URLSearchParams();
    Object.entries(filters).forEach(([key, value]) => {
      if (value !== '' && value !== false && value !== pageSize && value !== 1) params.set(key, String(value));
    });
    const query = params.toString();
    history.replaceState(null, '', `${location.pathname}${query ? `?${query}` : ''}`);
  }

  function renderStats(stats) {
    const byStatus = stats.by_status || {};
    const byPriority = stats.by_priority || {};
    const critical = (Number(byPriority.high) || 0) + (Number(byPriority.critical) || 0);
    const cards = [
      ['bi-kanban', 'Projets visibles', stats.total || 0, ''],
      ['bi-lightning-charge', 'En cours', byStatus.in_progress || 0, 'teal'],
      ['bi-exclamation-triangle', 'En retard', stats.overdue || 0, 'red'],
      ['bi-flag', 'Priorité haute ou critique', critical, 'amber'],
    ];
    $('#projectStats').innerHTML = cards.map(([icon, label, value, tone]) => `
      <div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
        <div class="kpi"><div class="ico ${tone}"><i class="bi ${icon}"></i></div>
          <div><div class="num">${esc(value)}</div><div class="lbl">${esc(label)}</div></div>
        </div>
      </div></div>`).join('');
  }

  function renderRows(projects) {
    if (!projects.length) {
      rows.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="bi bi-folder2-open fs-3 d-block mb-2"></i>Aucun projet ne correspond à ces filtres.</td></tr>';
      return;
    }

    rows.innerHTML = projects.map((project) => {
      const color = /^#[\da-f]{6}$/i.test(project.type?.color || '') ? project.type.color : '#6c757d';
      const progress = Math.max(0, Math.min(100, Number(project.progress) || 0));
      const archived = project.is_archived || project.status === 'archived';
      return `<tr>
        <td>
          <a class="fw-semibold" href="/projects/${encodeURIComponent(project.id)}">${esc(project.name)}</a>
          <div class="small text-muted">${esc(project.reference)}${project.client?.name ? ` · ${esc(project.client.name)}` : ''}</div>
          ${project.tags?.length ? `<div class="mt-1 d-flex flex-wrap gap-1">${project.tags.map((tag) => {
            const tagColor = /^#[\da-f]{6}$/i.test(tag.color || '') ? tag.color : '#667085';
            return `<span class="badge rounded-pill" style="background-color:${tagColor}">${esc(tag.name)}</span>`;
          }).join('')}</div>` : ''}
        </td>
        <td><span class="badge rounded-pill" style="background-color:${color}">${esc(typeLabel(project))}</span></td>
        <td>${esc(project.manager?.name || (project.manager ? `${project.manager.first_name || ''} ${project.manager.last_name || ''}`.trim() : '—'))}</td>
        <td><span class="badge-soft bs-${esc(project.status)}">${esc(L.status[project.status] || project.status)}</span>${archived ? '<span class="visually-hidden"> (archivé)</span>' : ''}</td>
        <td style="min-width:120px"><div class="d-flex align-items-center gap-2">
          <div class="progress flex-grow-1" role="progressbar" aria-label="Progression du projet" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100" style="height:6px">
            <div class="progress-bar" style="width:${progress}%"></div>
          </div><span class="small">${progress}%</span></div></td>
        <td><span class="badge-soft bp-${esc(project.priority)}">${esc(L.priority[project.priority] || project.priority || '—')}</span></td>
        <td>${esc(project.planned_end_date ? fmtDate(project.planned_end_date) : '—')}</td>
        <td><a class="btn btn-sm btn-light-soft" href="/projects/${encodeURIComponent(project.id)}" aria-label="Ouvrir ${esc(project.name)}"><i class="bi bi-arrow-right"></i></a></td>
      </tr>`;
    }).join('');
  }

  async function loadProjects(resetPage = false) {
    if (resetPage) page = 1;
    rows.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des projets…</td></tr>';
    const filters = currentFilters();
    syncUrl(filters);
    try {
      const [result, stats] = await Promise.all([
        api('projects', { params: filters }),
        api('projects/stats'),
      ]);
      renderRows(result.data || []);
      pager($('#projectPager'), result, (nextPage) => { page = nextPage; loadProjects(); });
      renderStats(stats);
    } catch (error) {
      rows.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-danger">Impossible de charger les projets. Réessayez.</td></tr>';
      fail(error);
    }
  }

  async function projectOptions() {
    const activeUsers = currentUser.role === 'admin'
      ? allActiveUsers()
      : api('users', { params: { is_active: 1, per_page: 100 } }).then((result) => result.data || []);
    const [availableTypes, clientResult, availableTags, users] = await Promise.all([
      api('project-types'),
      api('clients', { params: { per_page: 100 } }),
      api('tags'),
      activeUsers,
    ]);
    return {
      types: availableTypes,
      clients: clientResult.data || [],
      tags: availableTags,
      users,
    };
  }

  async function allActiveUsers() {
    const users = [];
    let pageNumber = 1;
    let lastPage = 1;

    do {
      const result = await api('users', { params: { is_active: 1, per_page: 100, page: pageNumber } });
      users.push(...(result.data || []));
      lastPage = Number(result.last_page) || 1;
      pageNumber += 1;
    } while (pageNumber <= lastPage);

    return users;
  }

  function projectForm(project, options, permissions = {}) {
    const isNew = !project;
    const forms = [
      field({ name: 'name', label: 'Nom du projet', required: true, value: project?.name || '', col: 'col-md-8' }),
      field({ name: 'project_type_id', label: 'Catégorie', required: true, type: 'select', blank: 'Choisir une catégorie', value: project?.project_type_id || '', options: options.types.map((type) => [type.id, type.name]), col: 'col-md-4' }),
      field({ name: 'client_id', label: 'Client', type: 'select', blank: 'Aucun client', value: project?.client_id || '', options: options.clients.map((client) => [client.id, client.name]), col: 'col-md-6' }),
      field({ name: 'status', label: 'Statut', type: 'select', value: project?.status || 'idea', options: asOptions(project ? STATUSES : STATUSES.filter(([value]) => value !== 'archived')), col: 'col-md-3' }),
      field({ name: 'priority', label: 'Priorité', type: 'select', value: project?.priority || 'medium', options: asOptions(PRIORITIES), col: 'col-md-3' }),
      field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: project?.description || '' }),
      field({ name: 'objectives', label: 'Objectifs', type: 'textarea', rows: 2, value: project?.objectives || '' }),
      field({ name: 'start_date', label: 'Date de début', type: 'date', value: project?.start_date || '', col: 'col-md-6' }),
      field({ name: 'planned_end_date', label: 'Échéance prévue', type: 'date', value: project?.planned_end_date || '', col: 'col-md-6' }),
      field({ name: 'progress', label: 'Progression (%)', type: 'number', min: 0, max: 100, value: project?.progress ?? 0, col: 'col-md-4' }),
      field({ name: 'budget_amount', label: 'Budget', type: 'number', min: 0, step: '0.01', value: project?.budget_amount ?? '', col: 'col-md-4' }),
      field({ name: 'budget_currency', label: 'Devise (code ISO)', value: project?.budget_currency || 'EUR', col: 'col-md-4' }),
      field({ name: 'technologies', label: 'Technologies (une par ligne)', type: 'textarea', rows: 2, value: (project?.technologies || []).join('\n') }),
      field({ name: 'repository_url', label: 'Dépôt de code', type: 'url', value: project?.repository_url || '', col: 'col-md-4' }),
      field({ name: 'staging_url', label: 'Environnement de préproduction', type: 'url', value: project?.staging_url || '', col: 'col-md-4' }),
      field({ name: 'production_url', label: 'Environnement de production', type: 'url', value: project?.production_url || '', col: 'col-md-4' }),
      field({ name: 'tags', label: 'Étiquettes', type: 'select', multiple: true, size: 4, value: (project?.tags || []).map((tag) => tag.id), options: options.tags.map((tag) => [tag.id, tag.name]), help: 'Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs étiquettes.' }),
    ];
    if (isNew && currentUser.role === 'admin') {
      forms.push(`<div class="col-12">
        <label class="form-label" for="projectAccessSearch">Membres autorisés à voir le projet</label>
        <input class="form-control mb-2" type="search" id="projectAccessSearch" placeholder="Rechercher un membre" aria-label="Rechercher un membre">
        <div class="border rounded p-2" id="projectAccessUsers" style="max-height: 220px; overflow-y: auto;">
          ${options.users.map((user) => `
            <label class="project-access-user d-flex align-items-center gap-2 p-2 rounded" data-user-search="${esc(`${user.first_name} ${user.last_name} ${user.email}`.toLowerCase())}">
              <input class="form-check-input m-0" type="checkbox" name="project_access_user_${esc(user.id)}">
              <span><span class="d-block fw-semibold">${esc(`${user.first_name} ${user.last_name}`)}</span><span class="small text-muted">${esc(user.email)}</span></span>
            </label>`).join('') || '<div class="text-muted p-2">Aucun utilisateur actif disponible.</div>'}
        </div>
        <div class="form-text">Seuls ces membres pourront voir le projet. Le responsable désigné et les administrateurs conservent leur accès.</div>
      </div>`);
    }
    if (isNew || permissions.manage) {
      forms.splice(3, 0, field({
        name: 'manager_id',
        label: 'Responsable du projet',
        type: 'select',
        blank: 'Aucun responsable',
        value: project?.manager_id || '',
        options: options.users.map((user) => [user.id, `${user.first_name} ${user.last_name}`]),
      }));
    }
    return forms.join('');
  }

  async function openCreateProject() {
    try {
      const options = await projectOptions();
      formModal({
        title: 'Créer un projet',
        html: `<div class="row g-3">${projectForm(null, options)}</div>`,
        size: 'modal-lg',
        submit: 'Créer le projet',
        onOpen: (form) => {
          const search = $('#projectAccessSearch', form);
          search?.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            $$('.project-access-user', form).forEach((option) => {
              option.hidden = !option.dataset.userSearch.includes(query);
            });
          });
        },
        onSubmit: async (data) => {
          if (currentUser.role === 'admin') {
            data.access_user_ids = options.users
              .filter((user) => data[`project_access_user_${user.id}`])
              .map((user) => user.id);
            options.users.forEach((user) => delete data[`project_access_user_${user.id}`]);
          }
          data.technologies = (data.technologies || '').split(/\r?\n/).map((value) => value.trim()).filter(Boolean);
          if (!data.technologies.length) data.technologies = null;
          const created = await api('projects', { method: 'POST', body: data });
          toast('Projet créé.');
          location.href = `/projects/${encodeURIComponent(created.id)}`;
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  function populateFilters(query) {
    const params = new URLSearchParams(query);
    $('#projectSearch').value = params.get('q') || '';
    $('#projectDateFrom').value = params.get('from') || '';
    $('#projectDateTo').value = params.get('to') || '';
    $('#projectStatus').value = params.get('status') || '';
    $('#projectType').value = params.get('project_type_id') || '';
    $('#projectSort').value = params.get('sort') || 'created_at';
    $('#projectDirection').value = params.get('direction') || 'desc';
    $('#includeArchived').checked = params.get('include_archived') === '1' || params.get('include_archived') === 'true';
    $('#overdueOnly').checked = params.get('overdue') === '1' || params.get('overdue') === 'true';
    page = Math.max(1, Number(params.get('page')) || 1);
  }

  App.ready(async (user) => {
    currentUser = user;
    types = await api('project-types');
    $('#projectType').insertAdjacentHTML('beforeend', types.map((type) => `<option value="${esc(type.id)}">${esc(type.name)}</option>`).join(''));
    $('#projectStatus').insertAdjacentHTML('beforeend', STATUSES.map(([value, label]) => `<option value="${value}">${label}</option>`).join(''));
    populateFilters(location.search);

    if (['admin', 'project_manager'].includes(user.role)) {
      const createButton = $('#createProject');
      createButton.classList.remove('d-none');
      createButton.addEventListener('click', openCreateProject);
    }

    $('#projectFilters').addEventListener('submit', (event) => {
      event.preventDefault();
      loadProjects(true);
    });
    $$('#projectFilters select, #projectDateFrom, #projectDateTo, #includeArchived, #overdueOnly').forEach((input) => input.addEventListener('change', () => loadProjects(true)));
    let searchTimeout;
    $('#projectSearch').addEventListener('input', () => {
      clearTimeout(searchTimeout);
      searchTimeout = setTimeout(() => loadProjects(true), 300);
    });

    loadProjects();
  });
})();
