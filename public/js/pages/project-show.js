(function () {
  'use strict';

  const { $, $$, api, esc, L, fmtDate, fmtDateTime, money, fmtSize, richText, field, formModal, modal, pager, confirm: confirmBox, download, preview, toast, fail } = App;
  const root = $('#projectPage');
  const projectId = root?.dataset.projectId;
  const ROLE_LABELS = {
    manager: 'Responsable',
    developer: 'Développeur',
    designer: 'Designer',
    tester: 'Testeur',
    analyst: 'Analyste',
    client_contact: 'Contact client',
    other: 'Autre',
  };
  const MEMBER_PRESETS = [['reader', 'Lecteur'], ['contributor', 'Contributeur'], ['editor', 'Éditeur'], ['manager', 'Gestionnaire']];
  const MILESTONE_STATUSES = [['pending', 'À venir'], ['in_progress', 'En cours'], ['completed', 'Atteint'], ['missed', 'Manqué']];
  const TASK_STATUSES = [['todo', 'À faire'], ['in_progress', 'En cours'], ['review', 'En revue'], ['done', 'Terminé'], ['cancelled', 'Annulé']];
  const DECISION_STATUSES = [['proposed', 'Proposée'], ['validated', 'Validée'], ['rejected', 'Rejetée'], ['superseded', 'Remplacée']];
  const FILE_CATEGORIES = [['specification', 'Cahier des charges'], ['mockup', 'Maquette'], ['contract', 'Contrat'], ['deliverable', 'Livrable'], ['report', 'Rapport'], ['image', 'Image'], ['other', 'Autre']];

  let project;
  let currentUser;
  let team = [];
  let projectEvents = [];
  let milestones = [];
  let folders = [];
  let selectedFolder = '';
  let activeTab = 'overview';
  let discussionPage = 1;
  let loadedTabs = new Set();
  const asOptions = (items) => items.map(([value, label]) => [value, label]);
  const allowed = (ability) => Boolean(project?.my_permissions?.[ability]);
  const userName = (user) => user?.name || `${user?.first_name || ''} ${user?.last_name || ''}`.trim() || 'Compte supprimé';
  const dateInput = (value) => value ? String(value).slice(0, 10) : '';
  const externalUrl = (value) => /^https?:\/\//i.test(String(value || '')) ? String(value) : '';

  function contentMessage(targetId, text, danger = false) {
    const target = $(`#${targetId}`);
    target.innerHTML = `<div class="py-4 text-center ${danger ? 'text-danger' : 'text-muted'}">${esc(text)}</div>`;
  }

  function renderHeader() {
    $('#projectTitle').textContent = project.name;
    $('#projectSubtitle').innerHTML = `<span class="font-monospace">${esc(project.reference)}</span>${project.type?.name ? ` · ${esc(project.type.name)}` : ''}${project.client?.name ? ` · ${esc(project.client.name)}` : ''}`;
    const actions = [];
    actions.push(`<a class="btn btn-light-soft" href="/assistant?project_id=${encodeURIComponent(project.id)}"><i class="bi bi-stars me-1"></i>Demander à Yobel</a>`);
    if (allowed('edit')) actions.push('<button type="button" class="btn btn-outline-primary" id="editProject"><i class="bi bi-pencil me-1"></i>Modifier</button>');
    if (allowed('manage') && !project.is_archived) actions.push('<button type="button" class="btn btn-outline-secondary" id="archiveProject"><i class="bi bi-archive me-1"></i>Archiver</button>');
    if (allowed('manage') && project.is_archived) actions.push('<button type="button" class="btn btn-outline-secondary" id="restoreProject"><i class="bi bi-arrow-counterclockwise me-1"></i>Restaurer</button>');
    if (['admin', 'project_manager'].includes(currentUser.role)) actions.push('<button type="button" class="btn btn-light-soft" id="duplicateProject"><i class="bi bi-copy me-1"></i>Dupliquer</button>');
    if (currentUser.role === 'admin') actions.push('<button type="button" class="btn btn-outline-danger" id="deleteProject" aria-label="Supprimer le projet"><i class="bi bi-trash"></i></button>');
    $('#projectActions').innerHTML = actions.join('');

    $('#editProject')?.addEventListener('click', editProject);
    $('#archiveProject')?.addEventListener('click', archiveProject);
    $('#restoreProject')?.addEventListener('click', restoreProject);
    $('#duplicateProject')?.addEventListener('click', duplicateProject);
    $('#deleteProject')?.addEventListener('click', deleteProject);
    if (currentUser.role !== 'admin' && currentUser.role !== 'project_manager') {
      $('[data-project-tab="evaluation"]')?.closest('li')?.remove();
    }
  }

  function renderOverview() {
    const color = /^#[\da-f]{6}$/i.test(project.type?.color || '') ? project.type.color : '#6c757d';
    const members = (project.members || []).filter((member) => !member.left_at);
    const completedMilestones = (project.milestones || []).filter((milestone) => milestone.status === 'completed').length;
    const progress = Math.max(0, Math.min(100, Number(project.progress) || 0));
    const dateLine = [
      project.start_date ? `Début : ${fmtDate(project.start_date)}` : null,
      project.planned_end_date ? `Échéance : ${fmtDate(project.planned_end_date)}` : null,
      project.actual_end_date ? `Fin réelle : ${fmtDate(project.actual_end_date)}` : null,
    ].filter(Boolean).join(' · ');
    const tags = (project.tags || []).map((tag) => {
      const tagColor = /^#[\da-f]{6}$/i.test(tag.color || '') ? tag.color : '#667085';
      return `<span class="badge rounded-pill me-1" style="background-color:${tagColor}">${esc(tag.name)}</span>`;
    }).join('');

    $('#projectOverview').innerHTML = `
      <div class="col-12 col-xl-8">
        <div class="card h-100"><div class="card-body">
          <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div><span class="badge rounded-pill" style="background-color:${color}">${esc(project.type?.name || 'Catégorie')}</span>
              <span class="badge-soft bs-${esc(project.status)} ms-1">${esc(L.status[project.status] || project.status)}</span>
              <span class="badge-soft bp-${esc(project.priority)} ms-1">${esc(L.priority[project.priority] || project.priority)}</span>
            </div><span class="small text-muted">${esc(dateLine || 'Dates non définies')}</span>
          </div>
          <h2 class="h5">Description</h2>
          <p class="text-break">${project.description ? esc(project.description).replace(/\n/g, '<br>') : '<span class="text-muted">Aucune description renseignée.</span>'}</p>
          <h2 class="h6 mt-4">Objectifs</h2>
          <p class="text-break">${project.objectives ? esc(project.objectives).replace(/\n/g, '<br>') : '<span class="text-muted">Aucun objectif renseigné.</span>'}</p>
          ${tags ? `<div class="mt-3">${tags}</div>` : ''}
        </div></div>
      </div>
      <div class="col-12 col-xl-4">
        <div class="card mb-3"><div class="card-body">
          <div class="d-flex justify-content-between align-items-center mb-2"><strong>Progression</strong><span>${progress}%</span></div>
          <div class="progress mb-3" role="progressbar" aria-label="Progression du projet" aria-valuenow="${progress}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width:${progress}%"></div>
          </div>
          <div class="info-grid">
            <div><dt>Responsable</dt><dd>${esc(userName(project.manager))}</dd></div>
            <div><dt>Équipe</dt><dd>${members.length} membre(s)</dd></div>
            <div><dt>Jalons atteints</dt><dd>${completedMilestones} / ${(project.milestones || []).length}</dd></div>
            <div><dt>Budget</dt><dd>${project.budget_amount === null ? '—' : esc(money(project.budget_amount, project.budget_currency || 'EUR'))}</dd></div>
            <div><dt>Coût réel</dt><dd>${project.actual_cost === null ? '—' : esc(money(project.actual_cost, project.budget_currency || 'EUR'))}</dd></div>
            <div><dt>Santé</dt><dd>${esc({ on_track: 'Dans les temps', at_risk: 'À risque', late: 'En retard' }[project.health] || 'Non renseignée')}</dd></div>
          </div>
          ${project.technologies?.length ? `<div class="mt-3"><dt class="small text-muted text-uppercase">Technologies</dt><dd class="mb-0">${project.technologies.map((technology) => `<span class="badge-soft bs-idea me-1">${esc(technology)}</span>`).join('')}</dd></div>` : ''}
        </div></div>
        ${(externalUrl(project.repository_url) || externalUrl(project.staging_url) || externalUrl(project.production_url)) ? `<div class="card"><div class="card-header">Liens du projet</div><div class="list-group list-group-flush">
          ${externalUrl(project.repository_url) ? `<a class="list-group-item list-group-item-action" href="${esc(externalUrl(project.repository_url))}" target="_blank" rel="noopener noreferrer"><i class="bi bi-git me-2"></i>Dépôt de code</a>` : ''}
          ${externalUrl(project.staging_url) ? `<a class="list-group-item list-group-item-action" href="${esc(externalUrl(project.staging_url))}" target="_blank" rel="noopener noreferrer"><i class="bi bi-box-arrow-up-right me-2"></i>Préproduction</a>` : ''}
          ${externalUrl(project.production_url) ? `<a class="list-group-item list-group-item-action" href="${esc(externalUrl(project.production_url))}" target="_blank" rel="noopener noreferrer"><i class="bi bi-globe me-2"></i>Production</a>` : ''}
        </div></div>` : ''}
      </div>`;
  }

  async function projectOptions() {
    const [availableTypes, clientResult, availableTags, userResult] = await Promise.all([
      api('project-types', { params: { include_inactive: currentUser.role === 'admin' } }),
      api('clients', { params: { per_page: 100 } }),
      api('tags'),
      allowed('manage') ? api('users', { params: { is_active: 1, per_page: 100 } }) : Promise.resolve({ data: [] }),
    ]);
    const projectTypes = [...availableTypes];
    if (project?.type && !projectTypes.some((type) => Number(type.id) === Number(project.type.id))) projectTypes.push(project.type);
    const clients = clientResult.data || [];
    if (project?.client && !clients.some((client) => Number(client.id) === Number(project.client.id))) clients.push(project.client);
    const users = userResult.data || [];
    if (project?.manager && !users.some((user) => Number(user.id) === Number(project.manager.id))) users.push(project.manager);
    return { types: projectTypes, clients, tags: availableTags, users };
  }

  function projectEditFields(options) {
    return [
      field({ name: 'name', label: 'Nom du projet', required: true, value: project.name, col: 'col-md-8' }),
      field({ name: 'project_type_id', label: 'Catégorie', required: true, type: 'select', value: project.project_type_id, options: options.types.map((type) => [type.id, type.name]), col: 'col-md-4' }),
      field({ name: 'client_id', label: 'Client', type: 'select', blank: 'Aucun client', value: project.client_id || '', options: options.clients.map((client) => [client.id, client.name]), col: 'col-md-6' }),
      field({ name: 'status', label: 'Statut', type: 'select', value: project.status, options: asOptions([['idea', 'Idée'], ['feasibility', 'Étude de faisabilité'], ['validated', 'Validé'], ['in_progress', 'En cours'], ['on_hold', 'En pause'], ['completed', 'Terminé'], ['abandoned', 'Abandonné'], ...(allowed('manage') || project.status === 'archived' ? [['archived', 'Archivé']] : [])]), col: 'col-md-3' }),
      field({ name: 'priority', label: 'Priorité', type: 'select', value: project.priority, options: asOptions([['low', 'Faible'], ['medium', 'Moyenne'], ['high', 'Haute'], ['critical', 'Critique']]), col: 'col-md-3' }),
      field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: project.description || '' }),
      field({ name: 'objectives', label: 'Objectifs', type: 'textarea', rows: 2, value: project.objectives || '' }),
      field({ name: 'start_date', label: 'Date de début', type: 'date', value: dateInput(project.start_date), col: 'col-md-6' }),
      field({ name: 'planned_end_date', label: 'Échéance prévue', type: 'date', value: dateInput(project.planned_end_date), col: 'col-md-6' }),
      field({ name: 'progress', label: 'Progression (%)', type: 'number', min: 0, max: 100, value: project.progress ?? 0, col: 'col-md-4' }),
      field({ name: 'budget_amount', label: 'Budget', type: 'number', min: 0, step: '0.01', value: project.budget_amount ?? '', col: 'col-md-4' }),
      field({ name: 'budget_currency', label: 'Devise (code ISO)', value: project.budget_currency || 'EUR', col: 'col-md-4' }),
      field({ name: 'actual_cost', label: 'Coût réel', type: 'number', min: 0, step: '0.01', value: project.actual_cost ?? '', col: 'col-md-4' }),
      field({ name: 'health', label: 'Santé du projet', type: 'select', blank: 'Non renseignée', value: project.health || '', options: [['on_track', 'Dans les temps'], ['at_risk', 'À risque'], ['late', 'En retard']], col: 'col-md-4' }),
      ...(allowed('manage') ? [field({ name: 'manager_id', label: 'Responsable', type: 'select', blank: 'Aucun responsable', value: project.manager_id || '', options: options.users.map((user) => [user.id, userName(user)]), col: 'col-md-4' })] : []),
      field({ name: 'technologies', label: 'Technologies (une par ligne)', type: 'textarea', rows: 2, value: (project.technologies || []).join('\n') }),
      field({ name: 'repository_url', label: 'Dépôt de code', type: 'url', value: project.repository_url || '', col: 'col-md-4' }),
      field({ name: 'staging_url', label: 'Préproduction', type: 'url', value: project.staging_url || '', col: 'col-md-4' }),
      field({ name: 'production_url', label: 'Production', type: 'url', value: project.production_url || '', col: 'col-md-4' }),
      field({ name: 'tags', label: 'Étiquettes', type: 'select', multiple: true, size: 4, value: (project.tags || []).map((tag) => tag.id), options: options.tags.map((tag) => [tag.id, tag.name]), help: 'Maintenez Ctrl (ou Cmd sur Mac) pour sélectionner plusieurs étiquettes.' }),
    ].join('');
  }

  async function editProject() {
    try {
      const options = await projectOptions();
      formModal({
        title: 'Modifier le projet',
        html: `<div class="row g-3">${projectEditFields(options)}</div>`,
        size: 'modal-lg',
        onSubmit: async (data) => {
          data.technologies = (data.technologies || '').split(/\r?\n/).map((value) => value.trim()).filter(Boolean);
          if (!data.technologies.length) data.technologies = null;
          await api(`projects/${projectId}`, { method: 'PUT', body: data });
          await reloadProject();
          toast('Projet mis à jour.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function archiveProject() {
    if (!await confirmBox('Archiver ce projet ? Il sera masqué de la liste par défaut.')) return;
    try {
      project = await api(`projects/${projectId}/archive`, { method: 'POST' });
      await reloadProject();
      toast('Projet archivé.');
    } catch (error) {
      fail(error);
    }
  }

  async function restoreProject() {
    formModal({
      title: 'Restaurer le projet',
      html: field({ name: 'status', label: 'Nouveau statut', type: 'select', required: true, value: 'in_progress', options: asOptions([['idea', 'Idée'], ['feasibility', 'Étude de faisabilité'], ['validated', 'Validé'], ['in_progress', 'En cours'], ['on_hold', 'En pause'], ['completed', 'Terminé'], ['abandoned', 'Abandonné']]) }),
      onSubmit: async (data) => {
        project = await api(`projects/${projectId}/restore`, { method: 'POST', body: data });
        await reloadProject();
        toast('Projet restauré.');
      },
    });
  }

  async function duplicateProject() {
    formModal({
      title: 'Dupliquer le projet',
      html: `<div class="row g-3">
        ${field({ name: 'name', label: 'Nom du nouveau projet', value: `${project.name} (copie)`, required: true })}
        ${field({ name: 'copy_members', label: 'Copier les membres actifs', type: 'checkbox', value: false })}
        ${field({ name: 'copy_folders', label: 'Copier l’arborescence des dossiers (sans les fichiers)', type: 'checkbox', value: false })}
      </div>`,
      onSubmit: async (data) => {
        const copy = await api(`projects/${projectId}/duplicate`, { method: 'POST', body: data });
        location.href = `/projects/${encodeURIComponent(copy.id)}`;
      },
    });
  }

  async function deleteProject() {
    if (!await confirmBox('Supprimer définitivement ce projet et ses éléments associés ? Cette action est irréversible.', 'Supprimer')) return;
    try {
      await api(`projects/${projectId}`, { method: 'DELETE' });
      toast('Projet supprimé.');
      location.href = '/projects';
    } catch (error) {
      fail(error);
    }
  }

  async function reloadProject() {
    const tab = activeTab;
    project = await api(`projects/${projectId}`);
    renderHeader();
    renderOverview();
    loadedTabs = new Set(['overview']);
    if (tab !== 'overview' && tabLoaders[tab]) {
      await tabLoaders[tab]();
      loadedTabs.add(tab);
    }
  }

  function memberRows() {
    if (!team.length) return '<tr><td colspan="5" class="text-center text-muted py-4">Aucun membre n’est encore affecté à ce projet.</td></tr>';
    return team.map((member) => `<tr>
      <td><div class="fw-semibold">${esc(userName(member.user))}</div><div class="small text-muted">${esc(member.user?.email || '')}</div></td>
      <td>${esc(ROLE_LABELS[member.project_role] || member.project_role)}</td>
      <td>${esc(member.allocation_percent ? `${member.allocation_percent}%` : '—')}</td>
      <td><div class="d-flex flex-wrap gap-1">${['view', 'comment', 'upload', 'edit', 'delete', 'manage'].filter((key) => member[`can_${key}`] || key === 'view' && member.can_view).map((key) => `<span class="badge-soft bs-idea">${esc({ view: 'Lecture', comment: 'Commentaire', upload: 'Dépôt', edit: 'Modification', delete: 'Suppression', manage: 'Gestion' }[key])}</span>`).join('')}</div></td>
      <td>${allowed('manage') ? `<div class="d-flex justify-content-end gap-1">
        <button type="button" class="btn btn-sm btn-light-soft" data-edit-member="${esc(member.user_id)}" aria-label="Modifier les droits de ${esc(userName(member.user))}"><i class="bi bi-pencil"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-member="${esc(member.user_id)}" aria-label="Retirer ${esc(userName(member.user))}"><i class="bi bi-person-dash"></i></button>
      </div>` : ''}</td>
    </tr>`).join('');
  }

  async function loadTeam() {
    team = await api(`projects/${projectId}/members`);
    const users = team.map((member) => `<tr><td>${esc(userName(member.user))}</td></tr>`).join('');
    $('#projectTeam').innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Équipe du projet</h2><div class="small text-muted">${team.length} membre(s) actif(s)</div></div>
        <div class="d-flex flex-wrap gap-2">
          ${currentUser.role === 'admin' ? '<button type="button" class="btn btn-outline-primary btn-sm" id="manageProjectAccess"><i class="bi bi-shield-lock me-1"></i>Gérer les accès</button>' : ''}
          ${allowed('manage') ? '<button type="button" class="btn btn-primary btn-sm" id="addMember"><i class="bi bi-person-plus me-1"></i>Ajouter un membre</button>' : ''}
        </div>
      </div>
      <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Membre</th><th>Rôle projet</th><th>Allocation</th><th>Droits</th><th></th></tr></thead><tbody>${memberRows()}</tbody></table></div>`;
    $('#addMember')?.addEventListener('click', addMember);
    $('#manageProjectAccess')?.addEventListener('click', manageProjectAccess);
    $$('[data-edit-member]').forEach((button) => button.addEventListener('click', () => editMember(Number(button.dataset.editMember))));
    $$('[data-remove-member]').forEach((button) => button.addEventListener('click', () => removeMember(Number(button.dataset.removeMember))));
  }

  async function manageProjectAccess() {
    try {
      const users = [];
      let page = 1;
      let lastPage = 1;

      do {
        const result = await api('users', { params: { is_active: 1, per_page: 100, page } });
        users.push(...(result.data || []));
        lastPage = Number(result.last_page) || 1;
        page += 1;
      } while (page <= lastPage);

      const visibleIds = new Set(team.filter((member) => !member.left_at && member.can_view).map((member) => Number(member.user_id)));
      const options = users.map((user) => `
        <label class="project-access-user d-flex align-items-center gap-2 border rounded p-2 mb-2" data-user-search="${esc(`${user.first_name} ${user.last_name} ${user.email}`.toLowerCase())}">
          <input class="form-check-input m-0" type="checkbox" name="access_user_${esc(user.id)}" ${visibleIds.has(Number(user.id)) ? 'checked' : ''}>
          <span><span class="d-block fw-semibold">${esc(userName(user))}</span><span class="small text-muted">${esc(user.email)}</span></span>
        </label>`).join('');

      formModal({
        title: 'Accès au projet',
        size: 'modal-lg',
        submit: 'Enregistrer les accès',
        html: `<p class="small text-muted">Seuls les membres sélectionnés pourront voir ce projet et ses contenus. Les autres membres seront retirés du projet. Les administrateurs conservent leur accès.</p>
          <input class="form-control mb-3" type="search" id="projectAccessSearch" placeholder="Rechercher un membre" aria-label="Rechercher un membre">
          <div class="project-access-list" style="max-height: 55vh; overflow-y: auto;">${options || '<div class="text-muted">Aucun utilisateur actif disponible.</div>'}</div>`,
        onOpen: (form) => {
          const search = $('#projectAccessSearch', form);
          search.addEventListener('input', () => {
            const query = search.value.trim().toLowerCase();
            $$('.project-access-user', form).forEach((option) => {
              option.hidden = !option.dataset.userSearch.includes(query);
            });
          });
        },
        onSubmit: async (data) => {
          const userIds = users
            .filter((user) => data[`access_user_${user.id}`])
            .map((user) => user.id);
          await api(`projects/${projectId}/access`, { method: 'PUT', body: { user_ids: userIds } });
          await reloadProject();
          toast('Les accès au projet ont été mis à jour.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function memberOptions() {
    const result = await api('users', { params: { is_active: 1, per_page: 100 } });
    return (result.data || []).filter((user) => !team.some((member) => Number(member.user_id) === Number(user.id)));
  }

  function memberFields(member, users = []) {
    return `<div class="row g-3">
      ${users.length || !member ? field({ name: 'user_id', label: 'Utilisateur', type: 'select', required: true, blank: 'Choisir un utilisateur', options: users.map((user) => [user.id, `${user.first_name} ${user.last_name} (${user.email})`]) }) : ''}
      ${field({ name: 'project_role', label: 'Rôle dans le projet', type: 'select', value: member?.project_role || 'developer', options: Object.entries(ROLE_LABELS) })}
      ${field({ name: 'preset', label: 'Modèle de droits', type: 'select', value: member ? '' : 'contributor', blank: 'Personnalisé', options: MEMBER_PRESETS })}
      ${field({ name: 'allocation_percent', label: 'Allocation (%)', type: 'number', min: 1, max: 100, value: member?.allocation_percent || '', col: 'col-md-4' })}
      ${field({ name: 'notes', label: 'Notes', type: 'textarea', rows: 2, value: member?.notes || '', col: 'col-md-8' })}
    </div>`;
  }

  async function addMember() {
    try {
      const users = await memberOptions();
      if (!users.length) {
        toast('Aucun utilisateur actif à ajouter.', 'info');
        return;
      }
      formModal({
        title: 'Ajouter un membre',
        html: memberFields(null, users),
        onSubmit: async (data) => {
          await api(`projects/${projectId}/members`, { method: 'POST', body: data });
          await reloadProject();
          toast('Membre ajouté.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function editMember(userId) {
    const member = team.find((item) => Number(item.user_id) === userId);
    if (!member) return;
    formModal({
      title: `Droits de ${esc(userName(member.user))}`,
      html: memberFields(member),
      onSubmit: async (data) => {
        await api(`projects/${projectId}/members/${userId}`, { method: 'PUT', body: data });
        await loadTeam();
        toast('Droits du membre mis à jour.');
      },
    });
  }

  async function removeMember(userId) {
    const member = team.find((item) => Number(item.user_id) === userId);
    if (!member || !await confirmBox(`Retirer ${userName(member.user)} du projet ?`)) return;
    try {
      await api(`projects/${projectId}/members/${userId}`, { method: 'DELETE' });
      await reloadProject();
      toast('Membre retiré du projet.');
    } catch (error) {
      fail(error);
    }
  }

  function milestoneRows() {
    if (!milestones.length) return '<tr><td colspan="4" class="text-center text-muted py-4">Aucun jalon pour le moment.</td></tr>';
    return milestones.map((milestone) => `<tr>
      <td><div class="fw-semibold">${esc(milestone.title)}</div>${milestone.description ? `<div class="small text-muted">${esc(milestone.description)}</div>` : ''}</td>
      <td>${esc(milestone.due_date ? fmtDate(milestone.due_date) : '—')}</td>
      <td><span class="badge-soft bs-${esc(milestone.status)}">${esc({ pending: 'À venir', in_progress: 'En cours', completed: 'Atteint', missed: 'Manqué' }[milestone.status] || milestone.status)}</span></td>
      <td>${allowed('edit') ? `<div class="d-flex justify-content-end gap-1">
        <button type="button" class="btn btn-sm btn-light-soft" data-edit-milestone="${esc(milestone.id)}" aria-label="Modifier ${esc(milestone.title)}"><i class="bi bi-pencil"></i></button>
        <button type="button" class="btn btn-sm btn-outline-danger" data-delete-milestone="${esc(milestone.id)}" aria-label="Supprimer ${esc(milestone.title)}"><i class="bi bi-trash"></i></button>
      </div>` : ''}</td>
    </tr>`).join('');
  }

  async function loadMilestones() {
    milestones = await api(`projects/${projectId}/milestones`);
    $('#projectMilestones').innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Jalons</h2><div class="small text-muted">${milestones.filter((item) => item.status === 'completed').length} sur ${milestones.length} atteint(s)</div></div>
        ${allowed('edit') ? '<button type="button" class="btn btn-primary btn-sm" id="addMilestone"><i class="bi bi-plus-lg me-1"></i>Nouveau jalon</button>' : ''}
      </div>
      <div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Jalon</th><th>Échéance</th><th>État</th><th></th></tr></thead><tbody>${milestoneRows()}</tbody></table></div>`;
    $('#addMilestone')?.addEventListener('click', () => editMilestone());
    $$('[data-edit-milestone]').forEach((button) => button.addEventListener('click', () => editMilestone(milestones.find((item) => Number(item.id) === Number(button.dataset.editMilestone)))));
    $$('[data-delete-milestone]').forEach((button) => button.addEventListener('click', () => deleteMilestone(milestones.find((item) => Number(item.id) === Number(button.dataset.deleteMilestone)))));
  }

  function milestoneFields(milestone) {
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: milestone?.title || '' })}
      ${field({ name: 'due_date', label: 'Échéance', type: 'date', value: dateInput(milestone?.due_date), col: 'col-md-6' })}
      ${field({ name: 'status', label: 'Statut', type: 'select', value: milestone?.status || 'pending', options: MILESTONE_STATUSES, col: 'col-md-6' })}
      ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: milestone?.description || '' })}
    </div>`;
  }

  function editMilestone(milestone) {
    formModal({
      title: milestone ? 'Modifier le jalon' : 'Nouveau jalon',
      html: milestoneFields(milestone),
      onSubmit: async (data) => {
        await api(milestone ? `milestones/${milestone.id}` : `projects/${projectId}/milestones`, { method: milestone ? 'PUT' : 'POST', body: data });
        await reloadProject();
        toast(milestone ? 'Jalon mis à jour.' : 'Jalon créé.');
      },
    });
  }

  async function deleteMilestone(milestone) {
    if (!milestone || !await confirmBox(`Supprimer le jalon « ${milestone.title} » ?`)) return;
    try {
      await api(`milestones/${milestone.id}`, { method: 'DELETE' });
      await reloadProject();
      toast('Jalon supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  function taskFields(task) {
    const members = (team.length ? team : project.members || []).filter((member) => !member.left_at);
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: task?.title || '' })}
      ${field({ name: 'description', label: 'Description', type: 'textarea', value: task?.description || '' })}
      ${field({ name: 'status', label: 'Statut', type: 'select', value: task?.status || 'todo', options: TASK_STATUSES, col: 'col-md-6' })}
      ${field({ name: 'priority', label: 'Priorité', type: 'select', value: task?.priority || 'medium', options: [['low', 'Faible'], ['medium', 'Moyenne'], ['high', 'Haute'], ['critical', 'Critique']], col: 'col-md-6' })}
      ${field({ name: 'assignee_id', label: 'Assigné à', type: 'select', blank: 'Non assignée', value: task?.assignee_id || '', options: members.map((member) => [member.user_id, userName(member.user)]), col: 'col-md-6' })}
      ${['admin', 'project_manager'].includes(currentUser.role) ? '<div class="col-12"><div class="small text-muted" id="taskAssigneeAvailability" role="status">Choisissez une personne pour consulter ses disponibilités.</div></div>' : ''}
      ${field({ name: 'milestone_id', label: 'Jalon associé', type: 'select', blank: 'Aucun jalon', value: task?.milestone_id || '', options: milestones.map((milestone) => [milestone.id, milestone.title]), col: 'col-md-6' })}
      ${field({ name: 'start_date', label: 'Début', type: 'date', value: dateInput(task?.start_date), col: 'col-md-6' })}
      ${field({ name: 'due_date', label: 'Échéance', type: 'date', value: dateInput(task?.due_date), col: 'col-md-6' })}
      ${field({ name: 'estimated_minutes', label: 'Durée estimée (minutes)', type: 'number', min: 0, value: task?.estimated_minutes ?? '', col: 'col-md-6' })}
    </div>`;
  }

  function weekStartForDate(value) {
    const date = value ? new Date(`${value}T00:00:00`) : new Date();
    date.setDate(date.getDate() - ((date.getDay() + 6) % 7));
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function attachTaskAvailability(form) {
    if (!['admin', 'project_manager'].includes(currentUser.role)) return;
    const assignee = $('[name="assignee_id"]', form);
    const hint = $('#taskAssigneeAvailability', form);
    let requestId = 0;

    const refresh = async () => {
      const currentRequest = ++requestId;
      if (!assignee.value) {
        hint.textContent = 'Choisissez une personne pour consulter ses disponibilités.';
        return;
      }

      const assigneeMember = (team.length ? team : project.members || [])
        .find((member) => Number(member.user_id) === Number(assignee.value));
      if (assigneeMember?.user?.role === 'guest') {
        hint.textContent = 'Le planning n’est pas disponible pour les comptes invités.';
        return;
      }

      const currentDate = new Date();
      const today = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(currentDate.getDate()).padStart(2, '0')}`;
      const date = $('[name="start_date"]', form).value || $('[name="due_date"]', form).value || today;
      hint.textContent = 'Chargement des disponibilités…';

      try {
        const schedule = await api('planning', {
          params: {
            user_id: assignee.value,
            week_start: weekStartForDate(date),
          },
        });
        if (currentRequest !== requestId) return;
        const day = schedule.days.find((item) => item.date === date);
        if (!day || !day.is_configured) {
          hint.textContent = 'Aucune disponibilité renseignée pour cette date.';
        } else if (day.is_available) {
          hint.textContent = `Disponible le ${day.label.toLowerCase()} de ${day.start_time || '—'} à ${day.end_time || '—'}${day.source === 'exception' ? ' (exceptionnel)' : ''}.`;
        } else {
          hint.textContent = `Indisponible le ${day.label.toLowerCase()}${day.reason ? ` : ${day.reason}` : ''}. L’affectation reste possible.`;
        }
      } catch (error) {
        if (currentRequest === requestId) {
          hint.textContent = 'La disponibilité ne peut pas être consultée pour le moment.';
          fail(error);
        }
      }
    };

    assignee.addEventListener('change', refresh);
    $('[name="start_date"]', form).addEventListener('change', refresh);
    $('[name="due_date"]', form).addEventListener('change', refresh);
    refresh();
  }

  async function loadTasks(nextPage = 1) {
    const result = await api('tasks', { params: { project_id: projectId, per_page: 20, page: nextPage } });
    const tasks = result.data || [];
    const canEdit = allowed('edit');
    $('#projectTasks').innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Tâches du projet</h2><div class="small text-muted">${result.total || tasks.length} tâche(s)</div></div>
        ${canEdit ? '<button type="button" class="btn btn-primary btn-sm" id="addTask"><i class="bi bi-plus-lg me-1"></i>Nouvelle tâche</button>' : ''}
      </div>
      ${tasks.length ? `<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Tâche</th><th>Assignée à</th><th>Échéance</th><th>Statut</th><th>Priorité</th><th></th></tr></thead><tbody>
      ${tasks.map((task) => {
        const canUpdate = canEdit || Number(task.assignee_id) === Number(currentUser.id);
        return `<tr>
          <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none fw-semibold" data-show-task="${esc(task.id)}">${esc(task.title)}</button>${task.description ? `<div class="small text-muted text-truncate-2">${esc(task.description)}</div>` : ''}</td>
          <td>${esc(userName(task.assignee))}</td>
          <td>${esc(task.due_date ? fmtDate(task.due_date) : '—')}</td>
          <td>${canUpdate ? `<select class="form-select form-select-sm" data-task-status="${esc(task.id)}" aria-label="Statut de ${esc(task.title)}">${TASK_STATUSES.map(([value, label]) => `<option value="${value}"${task.status === value ? ' selected' : ''}>${label}</option>`).join('')}</select>` : `<span class="badge-soft bs-${esc(task.status)}">${esc(L.task[task.status] || task.status)}</span>`}</td>
          <td><span class="badge-soft bp-${esc(task.priority)}">${esc(L.priority[task.priority] || task.priority)}</span></td>
          <td>${canEdit ? `<div class="d-flex justify-content-end gap-1">
            <button type="button" class="btn btn-sm btn-light-soft" data-edit-task="${esc(task.id)}" aria-label="Modifier ${esc(task.title)}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-task="${esc(task.id)}" aria-label="Supprimer ${esc(task.title)}"><i class="bi bi-trash"></i></button>
          </div>` : ''}</td>
        </tr>`;
      }).join('')}
      </tbody></table></div><div id="projectTasksPager" class="mt-3"></div>` : '<div class="text-center text-muted py-4">Aucune tâche pour le moment.</div>'}`;
    $('#addTask')?.addEventListener('click', () => editTask());
    $$('[data-show-task]').forEach((button) => button.addEventListener('click', () => showTask(tasks.find((task) => Number(task.id) === Number(button.dataset.showTask)))));
    $$('[data-edit-task]').forEach((button) => button.addEventListener('click', () => editTask(tasks.find((task) => Number(task.id) === Number(button.dataset.editTask)))));
    $$('[data-delete-task]').forEach((button) => button.addEventListener('click', () => deleteTask(tasks.find((task) => Number(task.id) === Number(button.dataset.deleteTask)))));
    $$('[data-task-status]').forEach((input) => input.addEventListener('change', async () => {
      try {
        await api(`tasks/${input.dataset.taskStatus}`, { method: 'PUT', body: { status: input.value } });
        toast('Statut de la tâche mis à jour.');
      } catch (error) {
        fail(error);
        loadTasks();
      }
    }));
    pager($('#projectTasksPager'), result, loadTasks);
  }

  async function showTask(task) {
    if (!task) return;
    try {
      const details = await api(`tasks/${task.id}`);
      const body = `<dl class="info-grid">
        <div><dt>Statut</dt><dd>${esc(L.task[details.status] || details.status)}</dd></div>
        <div><dt>Priorité</dt><dd>${esc(L.priority[details.priority] || details.priority)}</dd></div>
        <div><dt>Assignée à</dt><dd>${esc(userName(details.assignee))}</dd></div>
        <div><dt>Créée par</dt><dd>${esc(userName(details.creator))}</dd></div>
        <div><dt>Début</dt><dd>${esc(details.start_date ? fmtDate(details.start_date) : '—')}</dd></div>
        <div><dt>Échéance</dt><dd>${esc(details.due_date ? fmtDate(details.due_date) : '—')}</dd></div>
        <div><dt>Durée estimée</dt><dd>${details.estimated_minutes ? `${esc(details.estimated_minutes)} min` : '—'}</dd></div>
      </dl>
      ${details.description ? `<div class="mt-3"><h3 class="h6">Description</h3><div class="text-break">${esc(details.description).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${details.subtasks?.length ? `<div class="mt-3"><h3 class="h6">Sous-tâches</h3><ul class="mb-0">${details.subtasks.map((subtask) => `<li>${esc(subtask.title)} · ${esc(L.task[subtask.status] || subtask.status)}</li>`).join('')}</ul></div>` : ''}`;
      modal({ title: esc(details.title), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function editTask(task) {
    formModal({
      title: task ? 'Modifier la tâche' : 'Nouvelle tâche',
      html: taskFields(task),
      onOpen: (form) => attachTaskAvailability(form),
      onSubmit: async (data) => {
        await api(task ? `tasks/${task.id}` : `projects/${projectId}/tasks`, { method: task ? 'PUT' : 'POST', body: data });
        loadedTabs.delete('tasks');
        await loadTasks();
        toast(task ? 'Tâche mise à jour.' : 'Tâche créée.');
      },
    });
  }

  async function deleteTask(task) {
    if (!task || !await confirmBox(`Supprimer la tâche « ${task.title} » ?`)) return;
    try {
      await api(`tasks/${task.id}`, { method: 'DELETE' });
      await loadTasks();
      toast('Tâche supprimée.');
    } catch (error) {
      fail(error);
    }
  }

  function folderName(folder) {
    const parents = [];
    let current = folder;
    while (current) {
      parents.unshift(current.name);
      current = folders.find((item) => Number(item.id) === Number(current.parent_id));
    }
    return parents.join(' / ');
  }

  function folderAllows(folder, ability) {
    return allowed(ability) && (!folder || Boolean(folder.my_permissions?.[ability]));
  }

  async function loadFiles(nextPage = 1) {
    const params = { per_page: 20, page: nextPage };
    if (selectedFolder) params.folder_id = selectedFolder;
    const [folderList, result] = await Promise.all([
      api(`projects/${projectId}/folders`),
      api(`projects/${projectId}/files`, { params }),
    ]);
    folders = folderList;
    const fileItems = result.data || [];
    const folderChoices = folders.filter((folder) => {
      if (!selectedFolder) return true;
      if (selectedFolder === 'root') return !folder.parent_id;
      return Number(folder.parent_id || 0) === Number(selectedFolder);
    });
    $('#projectFiles').innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
        <div><h2 class="h5 mb-0">Fichiers et dossiers</h2><div class="small text-muted">Les dossiers restreints n’apparaissent que pour les personnes autorisées.</div></div>
        <div class="d-flex gap-2">
          <select class="form-select form-select-sm" id="folderFilter" aria-label="Filtrer par dossier">
            <option value="">Tous les fichiers</option><option value="root"${selectedFolder === 'root' ? ' selected' : ''}>Racine du projet</option>
            ${folders.map((folder) => `<option value="${esc(folder.id)}"${String(selectedFolder) === String(folder.id) ? ' selected' : ''}>${esc(folderName(folder))}${folder.is_restricted ? ' · restreint' : ''}</option>`).join('')}
          </select>
          ${allowed('upload') ? '<button type="button" class="btn btn-outline-primary btn-sm" id="addFolder"><i class="bi bi-folder-plus me-1"></i>Dossier</button><button type="button" class="btn btn-primary btn-sm" id="uploadFile"><i class="bi bi-upload me-1"></i>Déposer</button>' : ''}
        </div>
      </div>
      ${folderChoices.length ? `<div class="d-flex flex-wrap gap-2 mb-3">${folderChoices.map((folder) => `<div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-light-soft" data-open-folder="${esc(folder.id)}"><i class="bi bi-folder2 me-1"></i>${esc(folder.name)}${folder.is_restricted ? ' <i class="bi bi-lock-fill" aria-label="Dossier restreint"></i>' : ''} <span class="text-muted">(${esc(folder.files_count || 0)})</span></button>
        ${allowed('manage') ? `<button type="button" class="btn btn-light-soft" data-folder-permissions="${esc(folder.id)}" aria-label="Gérer les accès au dossier ${esc(folder.name)}"><i class="bi bi-shield-lock"></i></button>` : ''}
        ${folderAllows(folder, 'edit') ? `<button type="button" class="btn btn-light-soft" data-edit-folder="${esc(folder.id)}" aria-label="Modifier le dossier ${esc(folder.name)}"><i class="bi bi-pencil"></i></button>` : ''}
        ${folderAllows(folder, 'delete') ? `<button type="button" class="btn btn-outline-danger" data-delete-folder="${esc(folder.id)}" aria-label="Supprimer le dossier ${esc(folder.name)}"><i class="bi bi-trash"></i></button>` : ''}
      </div>`).join('')}</div>` : ''}
      ${fileItems.length ? `<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Fichier</th><th>Catégorie</th><th>Version</th><th>Déposé par</th><th>Date</th><th></th></tr></thead><tbody>
        ${fileItems.map((file) => `<tr>
          <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-file="${esc(file.id)}"><i class="bi bi-file-earmark me-1 text-primary"></i>${esc(file.name)}</button><div class="small text-muted">${esc(file.original_name)} · ${esc(fmtSize(file.size || 0))}</div></td>
          <td>${esc(Object.fromEntries(FILE_CATEGORIES)[file.category] || file.category)}</td>
          <td>v${esc(file.version_number || 1)}</td>
          <td>${esc(userName(file.uploader))}</td>
          <td>${esc(file.created_at ? fmtDate(file.created_at) : '—')}</td>
          <td><div class="d-flex justify-content-end gap-1">
            <button type="button" class="btn btn-sm btn-light-soft" data-download-file="${esc(file.id)}" aria-label="Télécharger ${esc(file.name)}"><i class="bi bi-download"></i></button>
            <button type="button" class="btn btn-sm btn-light-soft" data-preview-file="${esc(file.id)}" aria-label="Prévisualiser ${esc(file.name)}"><i class="bi bi-eye"></i></button>
            <button type="button" class="btn btn-sm btn-light-soft" data-file-versions="${esc(file.id)}" aria-label="Voir les versions de ${esc(file.name)}"><i class="bi bi-clock-history"></i></button>
            ${folderAllows(folders.find((folder) => Number(folder.id) === Number(file.folder_id)), 'upload') ? `<button type="button" class="btn btn-sm btn-light-soft" data-file-version="${esc(file.id)}" aria-label="Ajouter une version de ${esc(file.name)}"><i class="bi bi-arrow-repeat"></i></button>` : ''}
            ${folderAllows(folders.find((folder) => Number(folder.id) === Number(file.folder_id)), 'edit') ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-file="${esc(file.id)}" aria-label="Modifier ${esc(file.name)}"><i class="bi bi-pencil"></i></button>` : ''}
            ${folderAllows(folders.find((folder) => Number(folder.id) === Number(file.folder_id)), 'delete') ? `<button type="button" class="btn btn-sm btn-outline-danger" data-delete-file="${esc(file.id)}" aria-label="Supprimer ${esc(file.name)}"><i class="bi bi-trash"></i></button>` : ''}
          </div></td>
        </tr>`).join('')}
      </tbody></table></div>` : '<div class="text-center text-muted py-4">Aucun fichier dans ce dossier.</div>'}
      <div id="filePager" class="mt-3"></div>`;
    $('#folderFilter').addEventListener('change', (event) => {
      selectedFolder = event.target.value;
      loadFiles();
    });
    $$('[data-open-folder]').forEach((button) => button.addEventListener('click', () => {
      selectedFolder = String(button.dataset.openFolder);
      loadFiles();
    }));
    $$('[data-edit-folder]').forEach((button) => button.addEventListener('click', () => editFolder(folders.find((folder) => Number(folder.id) === Number(button.dataset.editFolder)))));
    $$('[data-delete-folder]').forEach((button) => button.addEventListener('click', () => deleteFolder(folders.find((folder) => Number(folder.id) === Number(button.dataset.deleteFolder)))));
    $$('[data-folder-permissions]').forEach((button) => button.addEventListener('click', () => editFolderPermissions(folders.find((folder) => Number(folder.id) === Number(button.dataset.folderPermissions)))));
    $('#addFolder')?.addEventListener('click', () => editFolder());
    $('#uploadFile')?.addEventListener('click', uploadFile);
    $$('[data-download-file]').forEach((button) => button.addEventListener('click', () => {
      const file = fileItems.find((item) => Number(item.id) === Number(button.dataset.downloadFile));
      download(`files/${file.id}/download`, file.original_name);
    }));
    $$('[data-show-file]').forEach((button) => button.addEventListener('click', () => showFile(fileItems.find((item) => Number(item.id) === Number(button.dataset.showFile)))));
    $$('[data-preview-file]').forEach((button) => button.addEventListener('click', () => {
      const file = fileItems.find((item) => Number(item.id) === Number(button.dataset.previewFile));
      preview(`files/${file.id}/preview`, file.name);
    }));
    $$('[data-file-version]').forEach((button) => button.addEventListener('click', () => addFileVersion(fileItems.find((item) => Number(item.id) === Number(button.dataset.fileVersion)))));
    $$('[data-file-versions]').forEach((button) => button.addEventListener('click', () => showFileVersions(fileItems.find((item) => Number(item.id) === Number(button.dataset.fileVersions)))));
    $$('[data-edit-file]').forEach((button) => button.addEventListener('click', () => editFile(fileItems.find((item) => Number(item.id) === Number(button.dataset.editFile)))));
    $$('[data-delete-file]').forEach((button) => button.addEventListener('click', () => deleteFile(fileItems.find((item) => Number(item.id) === Number(button.dataset.deleteFile)))));
    pager($('#filePager'), result, loadFiles);
  }

  async function showFile(file) {
    if (!file) return;
    try {
      const details = await api(`files/${file.id}`);
      const body = `<dl class="info-grid">
        <div><dt>Nom d’origine</dt><dd>${esc(details.original_name)}</dd></div>
        <div><dt>Catégorie</dt><dd>${esc(Object.fromEntries(FILE_CATEGORIES)[details.category] || details.category)}</dd></div>
        <div><dt>Dossier</dt><dd>${esc(details.folder?.name || 'Racine du projet')}</dd></div>
        <div><dt>Taille</dt><dd>${esc(fmtSize(details.size || 0))}</dd></div>
        <div><dt>Type de fichier</dt><dd>${esc(details.mime_type || '—')}</dd></div>
        <div><dt>Version</dt><dd>v${esc(details.version_number || 1)}${details.is_current ? ' · Actuelle' : ''}</dd></div>
        <div><dt>Déposé par</dt><dd>${esc(userName(details.uploader))}</dd></div>
        <div><dt>Date</dt><dd>${esc(details.created_at ? fmtDateTime(details.created_at) : '—')}</dd></div>
      </dl>
      ${details.description ? `<div class="mt-3"><h3 class="h6">Description</h3><div class="text-break">${esc(details.description).replace(/\n/g, '<br>')}</div></div>` : ''}`;
      modal({ title: esc(details.name), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function folderFields(folder) {
    const parentOptions = folders.filter((item) => (!folder || Number(item.id) !== Number(folder.id))
      && folderAllows(item, 'upload')).map((item) => [item.id, folderName(item)]);
    return `<div class="row g-3">
      ${field({ name: 'name', label: 'Nom du dossier', required: true, value: folder?.name || '' })}
      ${field({ name: 'description', label: 'Description', type: 'textarea', value: folder?.description || '' })}
      ${field({ name: 'parent_id', label: 'Dossier parent', type: 'select', blank: 'Racine du projet', value: folder?.parent_id || '', options: parentOptions })}
      ${allowed('manage') ? field({ name: 'is_restricted', label: 'Restreindre ce dossier aux personnes autorisées', type: 'checkbox', value: folder?.is_restricted || false }) : ''}
    </div>`;
  }

  function editFolder(folder) {
    formModal({
      title: folder ? 'Modifier le dossier' : 'Créer un dossier',
      html: folderFields(folder),
      onSubmit: async (data) => {
        await api(folder ? `folders/${folder.id}` : `projects/${projectId}/folders`, { method: folder ? 'PUT' : 'POST', body: data });
        await loadFiles();
        toast(folder ? 'Dossier mis à jour.' : 'Dossier créé.');
      },
    });
  }

  async function editFolderPermissions(folder) {
    if (!folder) return;
    try {
      const [details, members] = await Promise.all([
        api(`folders/${folder.id}`),
        team.length ? Promise.resolve(team) : api(`projects/${projectId}/members`),
      ]);
      team = members;
      const permissionsByUser = new Map((details.permissions || []).map((user) => [Number(user.id), user.pivot]));
      const permissionFields = team.map((member) => {
        const grant = permissionsByUser.get(Number(member.user_id)) || {};
        const name = userName(member.user);
        return `<div class="border rounded p-3 mb-2">
          <div class="fw-semibold mb-2">${esc(name)}</div>
          <div class="row g-2">
            ${field({ name: `view_${member.user_id}`, label: 'Voir', type: 'checkbox', value: grant.can_view ?? true, col: 'col-6 col-md-3' })}
            ${field({ name: `upload_${member.user_id}`, label: 'Déposer', type: 'checkbox', value: grant.can_upload || false, col: 'col-6 col-md-3' })}
            ${field({ name: `edit_${member.user_id}`, label: 'Modifier', type: 'checkbox', value: grant.can_edit || false, col: 'col-6 col-md-3' })}
            ${field({ name: `delete_${member.user_id}`, label: 'Supprimer', type: 'checkbox', value: grant.can_delete || false, col: 'col-6 col-md-3' })}
          </div>
        </div>`;
      }).join('');
      formModal({
        title: `Accès au dossier « ${esc(folder.name)} »`,
        size: 'modal-lg',
        html: `<div class="row g-3">
          ${field({ name: 'is_restricted', label: 'Restreindre le dossier aux personnes sélectionnées', type: 'checkbox', value: details.is_restricted })}
          <div class="col-12"><div class="small text-muted mb-2">Les droits du projet restent applicables. Seuls les membres actifs de ce projet peuvent recevoir un accès au dossier.</div>${permissionFields || '<div class="text-muted">Aucun membre actif à configurer.</div>'}</div>
        </div>`,
        onSubmit: async (data) => {
          const permissions = team.map((member) => ({
            user_id: member.user_id,
            can_view: Boolean(data[`view_${member.user_id}`]),
            can_upload: Boolean(data[`upload_${member.user_id}`]),
            can_edit: Boolean(data[`edit_${member.user_id}`]),
            can_delete: Boolean(data[`delete_${member.user_id}`]),
          }));
          await api(`folders/${folder.id}/permissions`, {
            method: 'PUT',
            body: { is_restricted: Boolean(data.is_restricted), permissions },
          });
          await loadFiles();
          toast('Accès au dossier mis à jour.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function showFileVersions(file) {
    if (!file) return;
    try {
      const versions = await api(`files/${file.id}/versions`);
      const body = versions.length ? `<div class="table-responsive"><table class="table mb-0"><thead><tr><th>Version</th><th>Fichier</th><th>Déposée par</th><th>Date</th><th></th></tr></thead><tbody>
        ${versions.map((version) => `<tr><td>v${esc(version.version_number)}${version.is_current ? ' <span class="badge text-bg-success">Actuelle</span>' : ''}</td>
          <td>${esc(version.original_name)} · ${esc(fmtSize(version.size || 0))}</td><td>${esc(userName(version.uploader))}</td>
          <td>${esc(version.created_at ? fmtDateTime(version.created_at) : '—')}</td>
          <td><button type="button" class="btn btn-sm btn-light-soft" data-download-version="${esc(version.id)}" aria-label="Télécharger la version ${esc(version.version_number)}"><i class="bi bi-download"></i></button></td>
        </tr>`).join('')}
      </tbody></table></div>` : '<div class="text-muted">Aucune version disponible.</div>';
      const handle = modal({ title: `Versions de « ${esc(file.name)} »`, size: 'modal-lg', body });
      $$('[data-download-version]', handle.el).forEach((button) => {
        const version = versions.find((item) => Number(item.id) === Number(button.dataset.downloadVersion));
        button.addEventListener('click', () => download(`files/${version.id}/download`, version.original_name));
      });
    } catch (error) {
      fail(error);
    }
  }

  async function deleteFolder(folder) {
    if (!folder || !await confirmBox(`Supprimer le dossier « ${folder.name} » ?`)) return;
    try {
      await api(`folders/${folder.id}`, { method: 'DELETE' });
      selectedFolder = '';
      await loadFiles();
      toast('Dossier supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  function uploadFile() {
    const folderOptions = folders.map((folder) => [folder.id, folderName(folder)]);
    formModal({
      title: 'Déposer un fichier',
      html: `<div class="row g-3">
        ${field({ name: 'file', label: 'Fichier (20 Mo maximum)', type: 'file', required: true, accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.md,.png,.jpg,.jpeg,.gif,.webp,.svg,.zip' })}
        ${field({ name: 'name', label: 'Nom affiché', help: 'Facultatif : le nom d’origine sera utilisé par défaut.' })}
        ${field({ name: 'category', label: 'Catégorie', type: 'select', value: 'other', options: FILE_CATEGORIES, col: 'col-md-6' })}
        ${field({ name: 'folder_id', label: 'Dossier', type: 'select', blank: 'Racine du projet', value: selectedFolder && selectedFolder !== 'root' ? selectedFolder : '', options: folderOptions.filter(([id]) => folderAllows(folders.find((folder) => Number(folder.id) === Number(id)), 'upload')), col: 'col-md-6' })}
        ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 2 })}
      </div>`,
      onSubmit: async (data, form) => {
        const payload = new FormData(form);
        if (!payload.get('name')) payload.delete('name');
        if (!payload.get('folder_id')) payload.delete('folder_id');
        await api(`projects/${projectId}/files`, { method: 'POST', form: payload });
        await loadFiles();
        toast('Fichier déposé.');
      },
    });
  }

  function editFile(file) {
    formModal({
      title: 'Modifier le fichier',
      html: `<div class="row g-3">
        ${field({ name: 'name', label: 'Nom affiché', required: true, value: file.name })}
        ${field({ name: 'category', label: 'Catégorie', type: 'select', value: file.category, options: FILE_CATEGORIES, col: 'col-md-6' })}
        ${field({ name: 'folder_id', label: 'Dossier', type: 'select', blank: 'Racine du projet', value: file.folder_id || '', options: folders.filter((folder) => folderAllows(folder, 'upload')).map((folder) => [folder.id, folderName(folder)]), col: 'col-md-6' })}
        ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 2, value: file.description || '' })}
      </div>`,
      onSubmit: async (data) => {
        await api(`files/${file.id}`, { method: 'PUT', body: data });
        await loadFiles();
        toast('Fichier mis à jour.');
      },
    });
  }

  function addFileVersion(file) {
    formModal({
      title: `Nouvelle version de « ${esc(file.name)} »`,
      html: `<div class="row g-3">
        ${field({ name: 'file', label: 'Nouveau fichier', type: 'file', required: true, accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.md,.png,.jpg,.jpeg,.gif,.webp,.svg,.zip' })}
        ${field({ name: 'description', label: 'Description de la version', type: 'textarea', rows: 2, value: file.description || '' })}
      </div>`,
      onSubmit: async (_data, form) => {
        const payload = new FormData(form);
        await api(`files/${file.id}/versions`, { method: 'POST', form: payload });
        await loadFiles();
        toast('Nouvelle version ajoutée.');
      },
    });
  }

  async function deleteFile(file) {
    if (!file || !await confirmBox(`Supprimer le fichier « ${file.name} » ?`)) return;
    try {
      await api(`files/${file.id}`, { method: 'DELETE' });
      await loadFiles();
      toast('Fichier supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  async function loadDiscussions(nextPage = 1) {
    discussionPage = nextPage;
    const result = await api(`projects/${projectId}/discussions`, { params: { page: nextPage, per_page: 20 } });
    const items = result.data || [];
    $('#projectDiscussions').innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Discussions du projet</h2><div class="small text-muted">${result.total || items.length} discussion(s)</div></div>
        ${allowed('comment') ? '<button type="button" class="btn btn-primary btn-sm" id="newDiscussion"><i class="bi bi-plus-lg me-1"></i>Nouvelle discussion</button>' : ''}
      </div>
      ${items.length ? `<div class="list-group list-group-flush">
        ${items.map((discussion) => `<div class="list-group-item px-0">
          <div class="d-flex justify-content-between gap-2">
            <button type="button" class="btn btn-link p-0 text-start text-decoration-none flex-grow-1" data-open-discussion="${esc(discussion.id)}">
              <span class="fw-semibold">${discussion.is_pinned ? '<i class="bi bi-pin-angle-fill me-1 text-primary"></i>' : ''}${esc(discussion.title)}</span>
              ${discussion.is_locked ? '<span class="badge text-bg-secondary ms-1">Verrouillée</span>' : ''}
              <span class="d-block small text-muted">${esc(userName(discussion.creator))} · ${Number(discussion.messages_count) || 0} message(s) · ${esc(discussion.last_message_at ? fmtDateTime(discussion.last_message_at) : fmtDate(discussion.created_at))}</span>
            </button>
            ${(allowed('manage') || Number(discussion.created_by) === Number(currentUser.id)) ? `<div class="d-flex gap-1">
              ${allowed('manage') ? `<button type="button" class="btn btn-sm btn-light-soft" data-pin-discussion="${esc(discussion.id)}" data-pinned="${discussion.is_pinned ? '1' : '0'}" aria-label="${discussion.is_pinned ? 'Désépingler' : 'Épingler'}"><i class="bi bi-pin-angle${discussion.is_pinned ? '-fill' : ''}"></i></button>` : ''}
              ${allowed('manage') ? `<button type="button" class="btn btn-sm btn-light-soft" data-toggle-discussion="${esc(discussion.id)}" data-locked="${discussion.is_locked ? '1' : '0'}" aria-label="${discussion.is_locked ? 'Déverrouiller' : 'Verrouiller'}"><i class="bi bi-${discussion.is_locked ? 'unlock' : 'lock'}"></i></button>` : ''}
              <button type="button" class="btn btn-sm btn-outline-danger" data-delete-discussion="${esc(discussion.id)}" aria-label="Supprimer ${esc(discussion.title)}"><i class="bi bi-trash"></i></button>
            </div>` : ''}
          </div>
        </div>`).join('')}
      </div>` : '<div class="text-center text-muted py-4">Aucune discussion pour le moment.</div>'}
      <div id="discussionPager" class="mt-3"></div>`;
    $('#newDiscussion')?.addEventListener('click', createDiscussion);
    $$('[data-open-discussion]').forEach((button) => button.addEventListener('click', () => openDiscussion(items.find((item) => Number(item.id) === Number(button.dataset.openDiscussion)))));
    $$('[data-pin-discussion]').forEach((button) => button.addEventListener('click', () => toggleDiscussionPin(items.find((item) => Number(item.id) === Number(button.dataset.pinDiscussion)))));
    $$('[data-toggle-discussion]').forEach((button) => button.addEventListener('click', () => toggleDiscussion(items.find((item) => Number(item.id) === Number(button.dataset.toggleDiscussion)))));
    $$('[data-delete-discussion]').forEach((button) => button.addEventListener('click', () => deleteDiscussion(items.find((item) => Number(item.id) === Number(button.dataset.deleteDiscussion)))));
    pager($('#discussionPager'), result, loadDiscussions);
  }

  function createDiscussion() {
    formModal({
      title: 'Nouvelle discussion',
      html: `<div class="row g-3">
        ${field({ name: 'title', label: 'Sujet', required: true })}
        ${field({ name: 'body', label: 'Premier message', type: 'textarea', rows: 4 })}
        ${allowed('manage') ? field({ name: 'is_pinned', label: 'Épingler la discussion', type: 'checkbox' }) : ''}
      </div>`,
      onSubmit: async (data) => {
        await api(`projects/${projectId}/discussions`, { method: 'POST', body: data });
        await loadDiscussions(1);
        toast('Discussion créée.');
      },
    });
  }

  function projectChatMessage(message) {
    const own = Number(message.user_id) === Number(currentUser.id);
    const name = userName(message.user);
    const initials = name.split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
    const tone = Math.abs(Number(message.user_id) || 0) % 5;

    return `<article class="project-chat-row tone-${tone} ${own ? 'is-own' : ''}" data-message-id="${esc(message.id)}">
      <div class="project-chat-avatar tone-${tone}" aria-hidden="true">${esc(initials || '?')}</div>
      <div class="project-chat-bubble">
        <div class="project-chat-meta"><strong>${esc(name)}</strong><time datetime="${esc(message.created_at)}">${esc(fmtDateTime(message.created_at))}</time>${message.edited_at ? '<span class="project-chat-edited">modifié</span>' : ''}</div>
        <div class="project-chat-body text-break">${richText(message.body)}</div>
        ${message.attachments?.length ? `<div class="small mt-2">${message.attachments.map((file) => `<span class="badge-soft bs-idea me-1"><i class="bi bi-paperclip"></i> ${esc(file.original_name)}</span>`).join('')}</div>` : ''}
      </div>
    </article>`;
  }

  function appendProjectChatMessages(container, messages, forceScroll = false) {
    const shouldScroll = forceScroll || container.scrollHeight - container.scrollTop - container.clientHeight < 80;
    const empty = $('.project-chat-empty', container);
    if (messages.length) empty?.remove();

    messages.forEach((message) => {
      if (container.querySelector(`[data-message-id="${Number(message.id)}"]`)) return;
      container.insertAdjacentHTML('beforeend', projectChatMessage(message));
    });

    if (shouldScroll) container.scrollTop = container.scrollHeight;
  }

  async function openDiscussion(discussion) {
    if (!discussion) return;
    const handle = modal({ title: esc(discussion.title), size: 'modal-lg', body: '<div class="text-center text-muted py-4">Chargement des messages…</div>' });
    try {
      let result = await api(`discussions/${discussion.id}/messages`, { params: { per_page: 100 } });
      if (result.last_page > 1) {
        result = await api(`discussions/${discussion.id}/messages`, { params: { page: result.last_page, per_page: 100 } });
      }
      if (!handle.el.isConnected) return;
      const messages = result.data || [];
      const canReply = allowed('comment') && !discussion.is_locked;
      handle.el.querySelector('.modal-body').innerHTML = `
        <div class="project-chat-list mb-3" id="projectChatMessages" aria-live="polite" aria-relevant="additions" aria-label="Messages de la discussion">
          ${messages.length ? messages.map(projectChatMessage).join('') : '<div class="project-chat-empty text-center text-muted py-3">Aucun message dans cette discussion.</div>'}
        </div>
        <div class="small text-muted mb-2" id="projectChatSyncStatus" role="status">Messages synchronisés automatiquement.</div>
        ${canReply ? `<form id="messageForm" class="border-top pt-3">
          <label class="form-label" for="messageBody">Votre message</label>
          <textarea class="form-control mb-2" id="messageBody" name="body" rows="3" maxlength="10000" required></textarea>
          <div class="d-flex justify-content-between align-items-center gap-2">
            <input class="form-control form-control-sm" type="file" name="attachments[]" multiple aria-label="Ajouter des pièces jointes" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.md,.png,.jpg,.jpeg,.gif,.webp,.svg,.zip">
            <button type="submit" class="btn btn-primary btn-sm">Envoyer</button>
          </div>
        </form>` : discussion.is_locked ? '<div class="alert alert-secondary mb-0">Cette discussion est verrouillée.</div>' : ''}`;
      const messageContainer = $('#projectChatMessages', handle.el);
      messageContainer.scrollTop = messageContainer.scrollHeight;
      let latestMessageId = messages.reduce((latest, message) => Math.max(latest, Number(message.id) || 0), 0);
      let polling = false;
      const pollMessages = async () => {
        if (polling || document.hidden || !messageContainer.isConnected) return;
        polling = true;
        try {
          const updates = await api(`discussions/${discussion.id}/messages`, {
            params: { after_id: latestMessageId, per_page: 100 },
          });
          const newMessages = updates.data || [];
          if (newMessages.length) {
            appendProjectChatMessages(messageContainer, newMessages);
            latestMessageId = newMessages.reduce((latest, message) => Math.max(latest, Number(message.id) || 0), latestMessageId);
            $('#projectChatSyncStatus', handle.el).textContent = 'Nouveaux messages reçus.';
          }
        } catch (error) {
          const status = $('#projectChatSyncStatus', handle.el);
          if (status) status.textContent = 'Connexion interrompue ; nouvelle tentative automatique…';
          if (error.status === 401 || error.status === 403 || error.status === 404) {
            clearInterval(pollTimer);
            fail(error);
          }
        } finally {
          polling = false;
        }
      };
      const pollTimer = setInterval(pollMessages, 2500);
      handle.el.addEventListener('hidden.bs.modal', () => clearInterval(pollTimer), { once: true });

      const messageForm = $('#messageForm', handle.el);
      messageForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!messageForm.reportValidity()) return;
        const submit = $('button[type="submit"]', messageForm);
        submit.disabled = true;
        try {
          const body = new FormData(messageForm);
          if (!(body.get('attachments[]') instanceof File) || !body.get('attachments[]').size) body.delete('attachments[]');
          const message = await api(`discussions/${discussion.id}/messages`, { method: 'POST', form: body });
          appendProjectChatMessages(messageContainer, [message], true);
          latestMessageId = Math.max(latestMessageId, Number(message.id) || 0);
          messageForm.reset();
          $('#projectChatSyncStatus', handle.el).textContent = 'Message envoyé.';
          await loadDiscussions(discussionPage);
        } catch (error) {
          fail(error);
        } finally {
          submit.disabled = false;
        }
      });
    } catch (error) {
      handle.close();
      fail(error);
    }
  }

  async function toggleDiscussion(discussion) {
    if (!discussion) return;
    try {
      await api(`discussions/${discussion.id}`, { method: 'PUT', body: { is_locked: !discussion.is_locked } });
      await loadDiscussions(discussionPage);
      toast(discussion.is_locked ? 'Discussion déverrouillée.' : 'Discussion verrouillée.');
    } catch (error) {
      fail(error);
    }
  }

  async function toggleDiscussionPin(discussion) {
    if (!discussion) return;
    try {
      await api(`discussions/${discussion.id}`, { method: 'PUT', body: { is_pinned: !discussion.is_pinned } });
      await loadDiscussions(discussionPage);
      toast(discussion.is_pinned ? 'Discussion désépinglée.' : 'Discussion épinglée.');
    } catch (error) {
      fail(error);
    }
  }

  async function deleteDiscussion(discussion) {
    if (!discussion || !await confirmBox(`Supprimer la discussion « ${discussion.title} » et ses messages ?`)) return;
    try {
      await api(`discussions/${discussion.id}`, { method: 'DELETE' });
      await loadDiscussions(discussionPage);
      toast('Discussion supprimée.');
    } catch (error) {
      fail(error);
    }
  }

  async function loadDecisions(nextPage = 1) {
    const result = await api(`projects/${projectId}/decisions`, { params: { page: nextPage, per_page: 20 } });
    const items = result.data || [];
    $('#projectDecisions').innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Décisions</h2><div class="small text-muted">${result.total || items.length} décision(s) enregistrée(s)</div></div>
        ${allowed('edit') ? '<button type="button" class="btn btn-primary btn-sm" id="newDecision"><i class="bi bi-plus-lg me-1"></i>Nouvelle décision</button>' : ''}
      </div>
      ${items.length ? `<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Sujet</th><th>Décision</th><th>Date</th><th>Statut</th><th></th></tr></thead><tbody>
        ${items.map((decision) => `<tr>
          <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-decision="${esc(decision.id)}">${esc(decision.title)}</button><div class="small text-muted">Par ${esc(userName(decision.creator))}</div></td>
          <td class="text-truncate-2" style="max-width:320px">${esc(decision.decision)}</td>
          <td>${esc(decision.decided_at ? fmtDate(decision.decided_at) : '—')}</td>
          <td><span class="badge-soft bs-${esc(decision.status)}">${esc({ proposed: 'Proposée', validated: 'Validée', rejected: 'Rejetée', superseded: 'Remplacée' }[decision.status] || decision.status)}</span></td>
          <td>${allowed('edit') ? `<div class="d-flex justify-content-end gap-1">
            <button type="button" class="btn btn-sm btn-light-soft" data-edit-decision="${esc(decision.id)}" aria-label="Modifier ${esc(decision.title)}"><i class="bi bi-pencil"></i></button>
            ${allowed('delete') ? `<button type="button" class="btn btn-sm btn-outline-danger" data-delete-decision="${esc(decision.id)}" aria-label="Supprimer ${esc(decision.title)}"><i class="bi bi-trash"></i></button>` : ''}
          </div>` : ''}</td>
        </tr>`).join('')}
      </tbody></table></div>` : '<div class="text-center text-muted py-4">Aucune décision n’a encore été enregistrée.</div>'}
      <div id="decisionPager" class="mt-3"></div>`;
    $('#newDecision')?.addEventListener('click', () => editDecision());
    $$('[data-show-decision]').forEach((button) => button.addEventListener('click', () => showDecision(items.find((item) => Number(item.id) === Number(button.dataset.showDecision)))));
    $$('[data-edit-decision]').forEach((button) => button.addEventListener('click', () => editDecision(items.find((item) => Number(item.id) === Number(button.dataset.editDecision)))));
    $$('[data-delete-decision]').forEach((button) => button.addEventListener('click', () => deleteDecision(items.find((item) => Number(item.id) === Number(button.dataset.deleteDecision)))));
    pager($('#decisionPager'), result, loadDecisions);
  }

  function decisionFields(decision, options) {
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Sujet de la décision', required: true, value: decision?.title || '' })}
      ${field({ name: 'decision', label: 'Décision prise', type: 'textarea', rows: 4, required: true, value: decision?.decision || '' })}
      ${field({ name: 'justification', label: 'Justification', type: 'textarea', rows: 3, value: decision?.justification || '' })}
      ${field({ name: 'status', label: 'Statut', type: 'select', value: decision?.status || 'proposed', options: DECISION_STATUSES, col: 'col-md-6' })}
      ${field({ name: 'decided_at', label: 'Date de décision', type: 'date', value: dateInput(decision?.decided_at), col: 'col-md-6' })}
      ${field({ name: 'participants', label: 'Participants', type: 'select', multiple: true, value: (decision?.participants || []).map((person) => person.id), options: options.members.map((member) => [member.user_id, userName(member.user)]) })}
      ${field({ name: 'discussion_id', label: 'Discussion liée', type: 'select', blank: 'Aucune', value: decision?.discussion_id || '', options: options.discussions.map((item) => [item.id, item.title]), col: 'col-md-6' })}
      ${field({ name: 'supersedes_id', label: 'Remplace la décision', type: 'select', blank: 'Aucune', value: decision?.supersedes_id || '', options: options.decisions.filter((item) => Number(item.id) !== Number(decision?.id)).map((item) => [item.id, item.title]), col: 'col-md-6' })}
    </div>`;
  }

  async function editDecision(decision) {
    try {
      const [members, discussionResult, decisionResult] = await Promise.all([
        team.length ? Promise.resolve(team) : api(`projects/${projectId}/members`),
        api(`projects/${projectId}/discussions`, { params: { per_page: 100 } }),
        api(`projects/${projectId}/decisions`, { params: { per_page: 100 } }),
      ]);
      team = members;
      formModal({
        title: decision ? 'Modifier la décision' : 'Nouvelle décision',
        html: decisionFields(decision, { members, discussions: discussionResult.data || [], decisions: decisionResult.data || [] }),
        onSubmit: async (data) => {
          await api(decision ? `decisions/${decision.id}` : `projects/${projectId}/decisions`, { method: decision ? 'PUT' : 'POST', body: data });
          await loadDecisions();
          toast(decision ? 'Décision mise à jour.' : 'Décision enregistrée.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function showDecision(decision) {
    if (!decision) return;
    try {
      const details = await api(`decisions/${decision.id}`);
      const body = `<dl class="info-grid">
        <div><dt>Statut</dt><dd>${esc({ proposed: 'Proposée', validated: 'Validée', rejected: 'Rejetée', superseded: 'Remplacée' }[details.status] || details.status)}</dd></div>
        <div><dt>Date</dt><dd>${esc(details.decided_at ? fmtDate(details.decided_at) : '—')}</dd></div>
        <div><dt>Créée par</dt><dd>${esc(userName(details.creator))}</dd></div>
        <div><dt>Remplace</dt><dd>${esc(details.supersedes?.title || '—')}</dd></div>
      </dl>
      <div class="mt-3"><h3 class="h6">Décision</h3><div class="text-break">${esc(details.decision).replace(/\n/g, '<br>')}</div></div>
      ${details.justification ? `<div class="mt-3"><h3 class="h6">Justification</h3><div class="text-break">${esc(details.justification).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${details.participants?.length ? `<div class="mt-3"><h3 class="h6">Participants</h3><div>${details.participants.map((person) => esc(userName(person))).join(', ')}</div></div>` : ''}`;
      modal({ title: esc(details.title), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  async function deleteDecision(decision) {
    if (!decision || !await confirmBox(`Supprimer la décision « ${decision.title} » ?`)) return;
    try {
      await api(`decisions/${decision.id}`, { method: 'DELETE' });
      await loadDecisions();
      toast('Décision supprimée.');
    } catch (error) {
      fail(error);
    }
  }

  async function loadMinutes(nextPage = 1) {
    const result = await api(`projects/${projectId}/meeting-minutes`, { params: { page: nextPage, per_page: 20 } });
    const items = result.data || [];
    $('#projectMinutes').innerHTML = `
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h2 class="h5 mb-0">Comptes rendus de réunion</h2><div class="small text-muted">${result.total || items.length} compte(s) rendu(s)</div></div>
        ${allowed('edit') ? '<button type="button" class="btn btn-primary btn-sm" id="newMinute"><i class="bi bi-plus-lg me-1"></i>Nouveau compte rendu</button>' : ''}
      </div>
      ${items.length ? `<div class="list-group list-group-flush">
        ${items.map((minute) => `<article class="list-group-item px-0">
          <div class="d-flex justify-content-between gap-2">
            <div><h3 class="h6 mb-1">${esc(minute.title)}</h3><div class="small text-muted">${esc(minute.meeting_date ? fmtDate(minute.meeting_date) : 'Date non définie')} · ${esc(userName(minute.creator))}</div>
              <button type="button" class="btn btn-link btn-sm p-0" data-show-minute="${esc(minute.id)}">Lire le compte rendu</button></div>
            ${allowed('edit') ? `<div class="d-flex gap-1 align-self-start">
              <button type="button" class="btn btn-sm btn-light-soft" data-edit-minute="${esc(minute.id)}" aria-label="Modifier ${esc(minute.title)}"><i class="bi bi-pencil"></i></button>
              ${allowed('delete') ? `<button type="button" class="btn btn-sm btn-outline-danger" data-delete-minute="${esc(minute.id)}" aria-label="Supprimer ${esc(minute.title)}"><i class="bi bi-trash"></i></button>` : ''}
            </div>` : ''}
          </div>
        </article>`).join('')}
      </div>` : '<div class="text-center text-muted py-4">Aucun compte rendu pour ce projet.</div>'}
      <div id="minutePager" class="mt-3"></div>`;
    $('#newMinute')?.addEventListener('click', () => editMinute());
    $$('[data-show-minute]').forEach((button) => button.addEventListener('click', () => showMinute(items.find((item) => Number(item.id) === Number(button.dataset.showMinute)))));
    $$('[data-edit-minute]').forEach((button) => button.addEventListener('click', () => editMinute(items.find((item) => Number(item.id) === Number(button.dataset.editMinute)))));
    $$('[data-delete-minute]').forEach((button) => button.addEventListener('click', () => deleteMinute(items.find((item) => Number(item.id) === Number(button.dataset.deleteMinute)))));
    pager($('#minutePager'), result, loadMinutes);
  }

  function minuteFields(minute, events) {
    const activeMembers = (team.length ? team : project.members || []).filter((member) => !member.left_at);
    if (minute?.event_id && !events.some((event) => Number(event.id) === Number(minute.event_id))) {
      events.push({ id: minute.event_id, title: `Événement associé (#${minute.event_id})`, starts_at: minute.meeting_date });
    }
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: minute?.title || '' })}
      ${field({ name: 'meeting_date', label: 'Date de réunion', type: 'date', value: dateInput(minute?.meeting_date), col: 'col-md-6' })}
      ${field({ name: 'event_id', label: 'Événement lié', type: 'select', blank: 'Aucun', value: minute?.event_id || '', options: events.map((event) => [event.id, event.title]), col: 'col-md-6' })}
      ${field({ name: 'attendees', label: 'Participants', type: 'select', multiple: true, size: 5, value: (minute?.attendees || []).map(Number), options: activeMembers.map((member) => [member.user_id, userName(member.user)]) })}
      ${field({ name: 'content', label: 'Compte rendu', type: 'textarea', rows: 8, required: true, value: minute?.content || '' })}
    </div>`;
  }

  async function editMinute(minute) {
    try {
      const [members, events] = await Promise.all([
        team.length ? Promise.resolve(team) : api(`projects/${projectId}/members`),
        api('events', { params: { project_id: projectId } }),
      ]);
      team = members;
      projectEvents = events;
      formModal({
        title: minute ? 'Modifier le compte rendu' : 'Nouveau compte rendu',
        html: minuteFields(minute, [...projectEvents]),
        size: 'modal-lg',
        onSubmit: async (data) => {
          await api(minute ? `meeting-minutes/${minute.id}` : `projects/${projectId}/meeting-minutes`, { method: minute ? 'PUT' : 'POST', body: data });
          await loadMinutes();
          toast(minute ? 'Compte rendu mis à jour.' : 'Compte rendu créé.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function showMinute(minute) {
    if (!minute) return;
    try {
      const details = await api(`meeting-minutes/${minute.id}`);
      const members = team.length ? team : project.members || [];
      const attendees = (details.attendees || []).map((id) => {
        const member = members.find((item) => Number(item.user_id) === Number(id));
        return member ? userName(member.user) : '';
      }).filter(Boolean);
      const body = `<dl class="info-grid">
        <div><dt>Date de réunion</dt><dd>${esc(details.meeting_date ? fmtDate(details.meeting_date) : '—')}</dd></div>
        <div><dt>Créé par</dt><dd>${esc(userName(details.creator))}</dd></div>
        <div><dt>Événement associé</dt><dd>${esc(projectEvents.find((event) => Number(event.id) === Number(details.event_id))?.title || (details.event_id ? `Événement #${details.event_id}` : '—'))}</dd></div>
        <div><dt>Participants</dt><dd>${esc(attendees.join(', ') || '—')}</dd></div>
      </dl><div class="mt-3 text-break">${esc(details.content).replace(/\n/g, '<br>')}</div>`;
      modal({ title: esc(details.title), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  async function deleteMinute(minute) {
    if (!minute || !await confirmBox(`Supprimer le compte rendu « ${minute.title} » ?`)) return;
    try {
      await api(`meeting-minutes/${minute.id}`, { method: 'DELETE' });
      await loadMinutes();
      toast('Compte rendu supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  async function loadEvaluation() {
    const data = await api(`projects/${projectId}/evaluations`);
    const rows = data.criteria || [];
    const hasScore = data.priority_score !== null && data.priority_score !== undefined;
    $('#projectEvaluation').innerHTML = `
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <div><h2 class="h5 mb-0">Évaluation du projet</h2><div class="small text-muted">Score de priorité calculé à partir des critères actifs.</div></div>
        ${hasScore ? `<span class="badge-soft bs-validated fs-6">Score : ${esc(data.priority_score)} / 100</span>` : '<span class="badge-soft bs-idea">Non évalué</span>'}
        ${rows.length ? '<button type="button" class="btn btn-primary btn-sm" id="scoreProject"><i class="bi bi-ui-checks me-1"></i>Évaluer</button>' : ''}
      </div>
      ${rows.length ? `<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Critère</th><th>Moyenne</th><th>Évaluations</th></tr></thead><tbody>
        ${rows.map((row) => `<tr>
          <td><div class="fw-semibold">${esc(row.criterion.name)}</div>${row.criterion.description ? `<div class="small text-muted">${esc(row.criterion.description)}</div>` : ''}</td>
          <td>${row.average === null ? '—' : `${esc(row.average)} / 10`}</td>
          <td>${row.evaluations?.length || 0}</td>
        </tr>`).join('')}
      </tbody></table></div>` : '<div class="alert alert-info mb-0">Aucun critère d’évaluation actif. Les critères sont configurés dans les paramètres.</div>'}`;
    $('#scoreProject')?.addEventListener('click', () => scoreProject(rows));
  }

  function scoreProject(rows) {
    const inputRows = rows.map((row) => {
      const own = (row.evaluations || []).find((item) => Number(item.evaluator_id) === Number(currentUser.id));
      return `<div class="border rounded p-3 mb-3"><div class="fw-semibold mb-2">${esc(row.criterion.name)}</div>
        ${field({ name: `score_${row.criterion.id}`, label: 'Note (1–10)', type: 'number', min: 1, max: 10, value: own?.score || '', col: 'col-md-3' })}
        ${field({ name: `justification_${row.criterion.id}`, label: 'Justification', type: 'textarea', rows: 2, value: own?.justification || '', col: 'col-md-9' })}
      </div>`;
    }).join('');
    formModal({
      title: 'Évaluer le projet',
      html: inputRows,
      size: 'modal-lg',
      onSubmit: async (data) => {
        const scores = rows.map((row) => ({
          criterion_id: row.criterion.id,
          score: data[`score_${row.criterion.id}`],
          justification: data[`justification_${row.criterion.id}`],
        })).filter((item) => item.score !== null && item.score !== '');
        if (!scores.length) {
          toast('Saisissez au moins une note.', 'warning');
          return false;
        }
        await api(`projects/${projectId}/evaluations`, { method: 'POST', body: { scores } });
        await loadEvaluation();
        toast('Évaluation enregistrée.');
      },
    });
  }

  async function loadActivity(nextPage = 1) {
    const result = await api(`projects/${projectId}/activity`, { params: { page: nextPage, per_page: 20 } });
    const items = result.data || [];
    $('#projectActivity').innerHTML = `
      <div class="mb-3"><h2 class="h5 mb-0">Historique du projet</h2><div class="small text-muted">${result.total || items.length} activité(s)</div></div>
      ${items.length ? `<div class="list-group list-group-flush">
        ${items.map((item) => `<div class="list-group-item px-0 d-flex gap-3">
          <span class="milestone-dot mt-2"></span><div><div class="fw-semibold">${esc(item.description || item.action)}</div>
            <div class="small text-muted">${esc(userName(item.user))} · ${esc(item.created_at ? fmtDateTime(item.created_at) : '—')}</div>
          </div>
        </div>`).join('')}
      </div>` : '<div class="text-center text-muted py-4">Aucune activité enregistrée pour ce projet.</div>'}
      <div id="activityPager" class="mt-3"></div>`;
    pager($('#activityPager'), result, loadActivity);
  }

  const tabLoaders = {
    overview: async () => renderOverview(),
    team: loadTeam,
    milestones: loadMilestones,
    tasks: loadTasks,
    files: loadFiles,
    discussions: () => loadDiscussions(discussionPage),
    decisions: () => loadDecisions(),
    minutes: () => loadMinutes(),
    evaluation: loadEvaluation,
    activity: () => loadActivity(),
  };

  async function selectTab(tab, force = false) {
    if (!tabLoaders[tab] || !project) return;
    activeTab = tab;
    $$('[data-project-tab]').forEach((button) => {
      const active = button.dataset.projectTab === tab;
      button.classList.toggle('active', active);
      button.setAttribute('aria-selected', String(active));
    });
    $$('[data-project-panel]').forEach((panel) => panel.classList.toggle('d-none', panel.dataset.projectPanel !== tab));
    if (!force && loadedTabs.has(tab)) return;
    const target = $(`[data-project-panel="${tab}"]`);
    if (target) target.setAttribute('aria-busy', 'true');
    try {
      await tabLoaders[tab]();
      loadedTabs.add(tab);
    } catch (error) {
      const id = {
        team: 'projectTeam', milestones: 'projectMilestones', tasks: 'projectTasks', files: 'projectFiles',
        discussions: 'projectDiscussions', decisions: 'projectDecisions', minutes: 'projectMinutes',
        evaluation: 'projectEvaluation', activity: 'projectActivity',
      }[tab];
      if (id) contentMessage(id, error.status === 403 ? 'Cette section est réservée aux personnes disposant des droits nécessaires.' : 'Impossible de charger cette section.', true);
      fail(error);
    } finally {
      target?.removeAttribute('aria-busy');
    }
  }

  App.ready(async (user) => {
    currentUser = user;
    if (!projectId || !/^\d+$/.test(projectId)) {
      contentMessage('projectOverview', 'Identifiant de projet invalide.', true);
      return;
    }
    try {
      project = await api(`projects/${projectId}`);
      team = project.members || [];
      milestones = project.milestones || [];
      renderHeader();
      renderOverview();
      loadedTabs.add('overview');
      $$('[data-project-tab]').forEach((button) => button.addEventListener('click', () => selectTab(button.dataset.projectTab)));

      const targetDiscussionId = new URLSearchParams(location.search).get('discussion_id');
      if (targetDiscussionId && /^\d+$/.test(targetDiscussionId)) {
        await selectTab('discussions');
        try {
          const discussion = await api(`discussions/${targetDiscussionId}`);
          if (Number(discussion.project_id) === Number(project.id)) openDiscussion(discussion);
        } catch (error) {
          fail(error);
        }
      }
    } catch (error) {
      $('#projectTitle').textContent = 'Projet indisponible';
      $('#projectSubtitle').textContent = '';
      $('#projectActions').replaceChildren();
      $('#projectError').classList.remove('d-none');
      $('#projectError').textContent = error.status === 404 || error.status === 403
        ? 'Ce projet est introuvable ou vous ne disposez pas des droits nécessaires pour le consulter.'
        : 'Impossible de charger ce projet. Réessayez.';
      fail(error);
    }
  });
})();
