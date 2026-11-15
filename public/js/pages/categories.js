(function () {
  'use strict';

  const { $, $$, api, esc, field, formModal, modal, pager, confirm: confirmBox, toast, fail, debounce, ready } = App;
  const root = $('#categoryContent');
  let activeTab = 'types';
  let currentUser;
  let page = 1;
  let types = [];
  let tags = [];

  function canManage() {
    return ['admin', 'project_manager'].includes(currentUser.role);
  }

  function setActions() {
    const enabled = activeTab === 'types' ? currentUser.role === 'admin' : canManage();
    const label = { types: 'Nouveau type', clients: 'Nouveau client', tags: 'Nouvelle étiquette' }[activeTab];
    $('#categoryActions').innerHTML = enabled ? `<button type="button" class="btn btn-primary" id="createCategory"><i class="bi bi-plus-lg me-1"></i>${label}</button>` : '';
    $('#createCategory')?.addEventListener('click', createItem);
    $('#categorySearchWrap').classList.toggle('d-none', activeTab === 'tags');
    $('#inactiveTypesWrap').classList.toggle('d-none', activeTab !== 'types' || currentUser.role !== 'admin');
  }

  function setActiveTab(tab) {
    activeTab = tab;
    page = 1;
    $$('[data-category-tab]').forEach((button) => button.classList.toggle('active', button.dataset.categoryTab === tab));
    setActions();
    loadItems();
  }

  function renderTypes() {
    const query = $('#categorySearch').value.trim().toLocaleLowerCase('fr');
    const visible = types.filter((type) => !query || `${type.name} ${type.description || ''}`.toLocaleLowerCase('fr').includes(query));
    $('#categoryContent').innerHTML = visible.length ? `<div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Type</th><th>Description</th><th>Projets</th><th>État</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>${visible.map((type) => {
        const color = /^#[\da-f]{6}$/i.test(type.color || '') ? type.color : '#6c757d';
        return `<tr>
          <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-type="${esc(type.id)}"><span class="d-inline-flex align-items-center gap-2"><span class="rounded-circle" style="width:.75rem;height:.75rem;background-color:${color}"></span>${esc(type.name)}</span></button></td>
          <td>${esc(type.description || '—')}</td><td>${esc(type.projects_count || 0)}</td>
          <td><span class="badge-soft ${type.is_active ? 'bs-completed' : 'bs-archived'}">${type.is_active ? 'Actif' : 'Inactif'}</span></td>
          <td><div class="d-flex justify-content-end gap-1">
            ${currentUser.role === 'admin' ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-type="${esc(type.id)}" aria-label="Modifier ${esc(type.name)}"><i class="bi bi-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-light-soft" data-toggle-type="${esc(type.id)}" aria-label="${type.is_active ? 'Désactiver' : 'Activer'} ${esc(type.name)}"><i class="bi bi-${type.is_active ? 'pause' : 'play'}-circle"></i></button>
              ${Number(type.projects_count) === 0 ? `<button type="button" class="btn btn-sm btn-outline-danger" data-delete-type="${esc(type.id)}" aria-label="Supprimer ${esc(type.name)}"><i class="bi bi-trash"></i></button>` : ''}` : ''}
          </div></td>
        </tr>`;
      }).join('')}</tbody></table></div>` : '<div class="empty"><i class="bi bi-grid"></i>Aucun type de projet ne correspond à la recherche.</div>';

    $$('[data-edit-type]').forEach((button) => button.addEventListener('click', () => editType(types.find((type) => Number(type.id) === Number(button.dataset.editType)))));
    $$('[data-show-type]').forEach((button) => button.addEventListener('click', () => showType(types.find((type) => Number(type.id) === Number(button.dataset.showType)))));
    $$('[data-toggle-type]').forEach((button) => button.addEventListener('click', () => toggleType(types.find((type) => Number(type.id) === Number(button.dataset.toggleType)))));
    $$('[data-delete-type]').forEach((button) => button.addEventListener('click', () => deleteType(types.find((type) => Number(type.id) === Number(button.dataset.deleteType)))));
  }

  async function loadTypes() {
    types = await api('project-types', { params: { include_inactive: $('#includeInactiveTypes').checked } });
    $('#categoryPager').innerHTML = '';
    renderTypes();
  }

  async function showType(type) {
    if (!type) return;
    try {
      const details = await api(`project-types/${type.id}`);
      const body = `<dl class="info-grid">
        <div><dt>Nom</dt><dd>${esc(details.name)}</dd></div>
        <div><dt>État</dt><dd>${details.is_active ? 'Actif' : 'Inactif'}</dd></div>
        <div><dt>Ordre d’affichage</dt><dd>${esc(details.sort_order ?? '—')}</dd></div>
        <div><dt>Projets visibles</dt><dd>${esc(details.projects_count || 0)}</dd></div>
        <div><dt>Identifiant</dt><dd>${esc(details.slug || '—')}</dd></div>
      </dl>
      ${details.description ? `<div class="mt-3"><h3 class="h6">Description</h3><div class="text-break">${esc(details.description).replace(/\n/g, '<br>')}</div></div>` : ''}`;
      modal({ title: esc(details.name), body });
    } catch (error) {
      fail(error);
    }
  }

  function renderClients(result) {
    const clients = result.data || [];
    $('#categoryContent').innerHTML = clients.length ? `<div class="table-responsive"><table class="table table-hover mb-0">
      <thead><tr><th>Client</th><th>Contact</th><th>Coordonnées</th><th>Projets</th><th>État</th><th><span class="visually-hidden">Actions</span></th></tr></thead>
      <tbody>${clients.map((client) => `<tr>
        <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-client="${esc(client.id)}">${esc(client.name)}</button><div class="small text-muted">${esc(client.company || '')}</div></td>
        <td>${esc(client.contact_name || '—')}</td>
        <td>${client.email ? `<a href="mailto:${esc(client.email)}">${esc(client.email)}</a>` : '—'}${client.phone ? `<div class="small text-muted">${esc(client.phone)}</div>` : ''}</td>
        <td>${esc(client.projects_count || 0)}</td>
        <td><span class="badge-soft ${client.is_active ? 'bs-completed' : 'bs-archived'}">${client.is_active ? 'Actif' : 'Inactif'}</span></td>
        <td><div class="d-flex justify-content-end gap-1">
          ${canManage() ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-client="${esc(client.id)}" aria-label="Modifier ${esc(client.name)}"><i class="bi bi-pencil"></i></button>
            ${Number(client.projects_count) === 0 ? `<button type="button" class="btn btn-sm btn-outline-danger" data-delete-client="${esc(client.id)}" aria-label="Supprimer ${esc(client.name)}"><i class="bi bi-trash"></i></button>` : ''}` : ''}
        </div></td>
      </tr>`).join('')}</tbody></table></div>` : '<div class="empty"><i class="bi bi-building"></i>Aucun client trouvé.</div>';

    $$('[data-edit-client]').forEach((button) => button.addEventListener('click', () => editClient(clients.find((client) => Number(client.id) === Number(button.dataset.editClient)))));
    $$('[data-show-client]').forEach((button) => button.addEventListener('click', () => showClient(clients.find((client) => Number(client.id) === Number(button.dataset.showClient)))));
    $$('[data-delete-client]').forEach((button) => button.addEventListener('click', () => deleteClient(clients.find((client) => Number(client.id) === Number(button.dataset.deleteClient)))));
    pager($('#categoryPager'), result, (nextPage) => { page = nextPage; loadItems(); });
  }

  async function showClient(client) {
    if (!client) return;
    try {
      const details = await api(`clients/${client.id}`);
      const website = String(details.website || '');
      const safeWebsite = /^https?:\/\//i.test(website) ? website : `https://${website}`;
      const body = `<dl class="info-grid">
        <div><dt>Entreprise</dt><dd>${esc(details.company || '—')}</dd></div>
        <div><dt>Contact</dt><dd>${esc(details.contact_name || '—')}</dd></div>
        <div><dt>E-mail</dt><dd>${details.email ? `<a href="mailto:${esc(details.email)}">${esc(details.email)}</a>` : '—'}</dd></div>
        <div><dt>Téléphone</dt><dd>${esc(details.phone || '—')}</dd></div>
        <div><dt>Site web</dt><dd>${website ? `<a href="${esc(safeWebsite)}" target="_blank" rel="noopener noreferrer">${esc(website)}</a>` : '—'}</dd></div>
        <div><dt>Pays</dt><dd>${esc(details.country || '—')}</dd></div>
        <div><dt>Projets associés</dt><dd>${esc(details.projects_count || 0)}</dd></div>
        <div><dt>État</dt><dd>${details.is_active ? 'Actif' : 'Inactif'}</dd></div>
      </dl>${details.address ? `<div class="mt-3"><h3 class="h6">Adresse</h3><div>${esc(details.address).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${details.notes ? `<div class="mt-3"><h3 class="h6">Notes</h3><div class="text-break">${esc(details.notes).replace(/\n/g, '<br>')}</div></div>` : ''}`;
      modal({ title: esc(details.name), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function renderTags() {
    const query = $('#categorySearch').value.trim().toLocaleLowerCase('fr');
    const visible = tags.filter((tag) => !query || tag.name.toLocaleLowerCase('fr').includes(query));
    $('#categoryContent').innerHTML = visible.length ? `<div class="p-3 d-flex flex-wrap gap-2">
      ${visible.map((tag) => {
        const color = /^#[\da-f]{6}$/i.test(tag.color || '') ? tag.color : '#667085';
        return `<span class="badge rounded-pill d-inline-flex align-items-center gap-2 px-3 py-2" style="background-color:${color}">
          ${esc(tag.name)}${canManage() ? `<button type="button" class="btn-close btn-close-white" data-delete-tag="${esc(tag.id)}" aria-label="Supprimer ${esc(tag.name)}"></button>` : ''}
        </span>`;
      }).join('')}
    </div>` : '<div class="empty"><i class="bi bi-tags"></i>Aucune étiquette trouvée.</div>';
    $('#categoryPager').innerHTML = '';
    $$('[data-delete-tag]').forEach((button) => button.addEventListener('click', () => deleteTag(tags.find((tag) => Number(tag.id) === Number(button.dataset.deleteTag)))));
  }

  async function loadItems() {
    root.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement…</div>';
    try {
      if (activeTab === 'types') await loadTypes();
      else if (activeTab === 'tags') {
        tags = await api('tags');
        renderTags();
      } else {
        const result = await api('clients', { params: { search: $('#categorySearch').value.trim(), page, per_page: 20 } });
        renderClients(result);
      }
    } catch (error) {
      root.innerHTML = '<div class="text-center text-danger py-5">Impossible de charger ces données.</div>';
      fail(error);
    }
  }

  function typeFields(type) {
    return `<div class="row g-3">
      ${field({ name: 'name', label: 'Nom', required: true, value: type?.name || '' })}
      ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: type?.description || '' })}
      ${field({ name: 'color', label: 'Couleur', type: 'color', value: type?.color || '#6c757d', col: 'col-md-4' })}
      ${field({ name: 'icon', label: 'Icône Bootstrap (facultatif)', value: type?.icon || '', placeholder: 'bi-kanban', col: 'col-md-4' })}
      ${field({ name: 'sort_order', label: 'Ordre d’affichage', type: 'number', min: 0, value: type?.sort_order ?? 0, col: 'col-md-4' })}
      ${field({ name: 'is_active', label: 'Type actif', type: 'checkbox', value: type?.is_active ?? true })}
    </div>`;
  }

  function clientFields(client) {
    return `<div class="row g-3">
      ${field({ name: 'name', label: 'Nom du client', required: true, value: client?.name || '', col: 'col-md-6' })}
      ${field({ name: 'company', label: 'Entreprise', value: client?.company || '', col: 'col-md-6' })}
      ${field({ name: 'contact_name', label: 'Nom du contact', value: client?.contact_name || '', col: 'col-md-6' })}
      ${field({ name: 'email', label: 'Adresse e-mail', type: 'email', value: client?.email || '', col: 'col-md-6' })}
      ${field({ name: 'phone', label: 'Téléphone', type: 'tel', value: client?.phone || '', col: 'col-md-6' })}
      ${field({ name: 'website', label: 'Site web', type: 'url', value: client?.website || '', col: 'col-md-6' })}
      ${field({ name: 'country', label: 'Pays (code ISO à 2 lettres)', value: client?.country || '', maxlength: 2, col: 'col-md-6' })}
      ${field({ name: 'tax_number', label: 'Numéro fiscal', value: client?.tax_number || '', col: 'col-md-6' })}
      ${field({ name: 'address', label: 'Adresse', type: 'textarea', rows: 2, value: client?.address || '' })}
      ${field({ name: 'notes', label: 'Notes', type: 'textarea', rows: 3, value: client?.notes || '' })}
      ${field({ name: 'is_active', label: 'Client actif', type: 'checkbox', value: client?.is_active ?? true })}
    </div>`;
  }

  function createItem() {
    if (activeTab === 'types') {
      formModal({ title: 'Nouveau type de projet', html: typeFields(), onSubmit: async (data) => {
        await api('project-types', { method: 'POST', body: data });
        await loadItems();
        toast('Type de projet créé.');
      } });
    } else if (activeTab === 'clients') {
      formModal({ title: 'Nouveau client', size: 'modal-lg', html: clientFields(), onSubmit: async (data) => {
        await api('clients', { method: 'POST', body: data });
        await loadItems();
        toast('Client créé.');
      } });
    } else {
      formModal({
        title: 'Nouvelle étiquette',
        html: `<div class="row g-3">${field({ name: 'name', label: 'Nom', required: true })}${field({ name: 'color', label: 'Couleur', type: 'color', value: '#667085', col: 'col-md-4' })}</div>`,
        onSubmit: async (data) => {
          await api('tags', { method: 'POST', body: data });
          await loadItems();
          toast('Étiquette enregistrée.');
        },
      });
    }
  }

  function editType(type) {
    formModal({ title: 'Modifier le type de projet', html: typeFields(type), onSubmit: async (data) => {
      await api(`project-types/${type.id}`, { method: 'PUT', body: data });
      await loadItems();
      toast('Type de projet mis à jour.');
    } });
  }

  async function toggleType(type) {
    try {
      await api(`project-types/${type.id}`, { method: 'PUT', body: { is_active: !type.is_active } });
      await loadItems();
      toast(type.is_active ? 'Type désactivé.' : 'Type activé.');
    } catch (error) {
      fail(error);
    }
  }

  async function deleteType(type) {
    if (!type || !await confirmBox(`Supprimer le type « ${type.name} » ?`, 'Supprimer')) return;
    try {
      await api(`project-types/${type.id}`, { method: 'DELETE' });
      await loadItems();
      toast('Type de projet supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  function editClient(client) {
    formModal({ title: 'Modifier le client', size: 'modal-lg', html: clientFields(client), onSubmit: async (data) => {
      await api(`clients/${client.id}`, { method: 'PUT', body: data });
      await loadItems();
      toast('Client mis à jour.');
    } });
  }

  async function deleteClient(client) {
    if (!client || !await confirmBox(`Supprimer le client « ${client.name} » ?`, 'Supprimer')) return;
    try {
      await api(`clients/${client.id}`, { method: 'DELETE' });
      await loadItems();
      toast('Client supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  async function deleteTag(tag) {
    if (!tag || !await confirmBox(`Supprimer l’étiquette « ${tag.name} » ?`, 'Supprimer')) return;
    try {
      await api(`tags/${tag.id}`, { method: 'DELETE' });
      await loadItems();
      toast('Étiquette supprimée.');
    } catch (error) {
      fail(error);
    }
  }

  function init(user) {
    currentUser = user;
    $$('[data-category-tab]').forEach((button) => button.addEventListener('click', () => setActiveTab(button.dataset.categoryTab)));
    $('#categorySearch').addEventListener('input', debounce(() => {
      if (activeTab === 'clients') page = 1;
      if (activeTab !== 'types') loadItems();
      else renderTypes();
    }, 250));
    $('#includeInactiveTypes').addEventListener('change', loadItems);
    setActions();
    loadItems();
  }

  ready(init);
})();
