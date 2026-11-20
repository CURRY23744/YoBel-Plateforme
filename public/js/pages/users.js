(function () {
  'use strict';

  const { $, $$, api, esc, fullName, L, field, formModal, pager, confirm: confirmBox, toast, fail, debounce, ready, modal } = App;
  const rows = $('#userRows');
  const ROLES = [['admin', 'Administrateur'], ['project_manager', 'Chef de projet'], ['member', 'Membre'], ['guest', 'Invité']];
  let currentUser;
  let page = 1;
  let users = [];

  function filters() {
    return {
      search: $('#userSearch').value.trim(),
      role: $('#userRoleFilter').value,
      is_active: $('#userActiveFilter').value,
      page,
      per_page: 20,
    };
  }

  function renderUsers(result) {
    users = result.data || [];
    rows.innerHTML = users.length ? users.map((person) => {
      const isSelf = Number(person.id) === Number(currentUser.id);
      return `<tr>
        <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-user="${esc(person.id)}">${esc(fullName(person))}${isSelf ? ' <span class="badge text-bg-light">Vous</span>' : ''}</button><div class="small text-muted">${esc(person.email)}</div></td>
        <td>${esc(person.job_title || person.department || '—')}</td>
        <td>${esc(L.role[person.role] || person.role)}</td>
        <td><span class="badge-soft ${person.is_active ? 'bs-completed' : 'bs-archived'}">${person.is_active ? 'Actif' : 'Désactivé'}</span>${person.must_change_password ? '<div class="small text-warning">Changement de mot de passe requis</div>' : ''}</td>
        <td>${person.must_change_password ? '<span class="small text-warning">Temporaire</span>' : '<span class="small text-muted">Défini</span>'}</td>
        <td><div class="d-flex justify-content-end gap-1">
          ${currentUser.role === 'admin' && !isSelf ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-user="${esc(person.id)}" aria-label="Modifier ${esc(fullName(person))}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-light-soft" data-reset-password="${esc(person.id)}" aria-label="Réinitialiser le mot de passe de ${esc(fullName(person))}"><i class="bi bi-key"></i></button>
            <button type="button" class="btn btn-sm btn-light-soft" data-toggle-user="${esc(person.id)}" aria-label="${person.is_active ? 'Désactiver' : 'Activer'} ${esc(fullName(person))}"><i class="bi bi-${person.is_active ? 'person-slash' : 'person-check'}"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-user="${esc(person.id)}" aria-label="Supprimer ${esc(fullName(person))}"><i class="bi bi-trash"></i></button>` : ''}
        </div></td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-people fs-3 d-block mb-2"></i>Aucun utilisateur trouvé.</td></tr>';

    $$('[data-edit-user]').forEach((button) => button.addEventListener('click', () => editUser(users.find((person) => Number(person.id) === Number(button.dataset.editUser)))));
    $$('[data-show-user]').forEach((button) => button.addEventListener('click', () => showUser(users.find((person) => Number(person.id) === Number(button.dataset.showUser)))));
    $$('[data-reset-password]').forEach((button) => button.addEventListener('click', () => resetPassword(users.find((person) => Number(person.id) === Number(button.dataset.resetPassword)))));
    $$('[data-toggle-user]').forEach((button) => button.addEventListener('click', () => toggleUser(users.find((person) => Number(person.id) === Number(button.dataset.toggleUser)))));
    $$('[data-delete-user]').forEach((button) => button.addEventListener('click', () => deleteUser(users.find((person) => Number(person.id) === Number(button.dataset.deleteUser)))));
    pager($('#userPager'), result, (nextPage) => { page = nextPage; loadUsers(); });
  }

  async function loadUsers() {
    rows.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des utilisateurs…</td></tr>';
    try {
      const result = await api('users', { params: filters() });
      renderUsers(result);
    } catch (error) {
      rows.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Impossible de charger les utilisateurs.</td></tr>';
      fail(error);
    }
  }

  async function showUser(person) {
    if (!person) return;
    try {
      const details = await api(`users/${person.id}`);
      const memberships = details.memberships || [];
      const body = `<dl class="info-grid">
        <div><dt>Adresse e-mail</dt><dd><a href="mailto:${esc(details.email)}">${esc(details.email)}</a></dd></div>
        <div><dt>Rôle</dt><dd>${esc(L.role[details.role] || details.role)}</dd></div>
        <div><dt>Fonction</dt><dd>${esc(details.job_title || '—')}</dd></div>
        <div><dt>Service</dt><dd>${esc(details.department || '—')}</dd></div>
        <div><dt>Téléphone</dt><dd>${esc(details.phone || '—')}</dd></div>
        <div><dt>État</dt><dd>${details.is_active ? 'Actif' : 'Désactivé'}</dd></div>
      </dl>
      ${details.bio ? `<div class="mt-3"><h3 class="h6">À propos</h3><div class="text-break">${esc(details.bio).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${memberships.length ? `<div class="mt-3"><h3 class="h6">Projets</h3><ul class="mb-0">${memberships.map((membership) => {
        const project = membership.project;
        if (!project) return '';
        return `<li><a href="/projects/${encodeURIComponent(project.id)}">${esc(project.reference)} · ${esc(project.name)}</a> · ${esc(L.status[project.status] || project.status)}${membership.left_at ? ' · Ancien membre' : ''}</li>`;
      }).join('')}</ul></div>` : '<div class="mt-3 text-muted">Aucun projet associé.</div>'}`;
      modal({ title: esc(fullName(details)), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function userFields(person, isEdit = false) {
    return `<div class="row g-3">
      ${field({ name: 'first_name', label: 'Prénom', required: true, value: person?.first_name || '', col: 'col-md-6' })}
      ${field({ name: 'last_name', label: 'Nom', required: true, value: person?.last_name || '', col: 'col-md-6' })}
      ${field({ name: 'email', label: 'Adresse e-mail', type: 'email', required: true, value: person?.email || '', col: 'col-md-7' })}
      ${field({ name: 'role', label: 'Rôle', type: 'select', required: true, value: person?.role || 'member', options: ROLES, col: 'col-md-5' })}
      ${field({ name: 'phone', label: 'Téléphone', type: 'tel', value: person?.phone || '', col: 'col-md-6' })}
      ${field({ name: 'job_title', label: 'Fonction', value: person?.job_title || '', col: 'col-md-6' })}
      ${field({ name: 'department', label: 'Service', value: person?.department || '', col: 'col-md-6' })}
      ${field({ name: 'employee_number', label: 'Matricule', value: person?.employee_number || '', col: 'col-md-6' })}
      ${isEdit ? '' : `<div class="col-12 d-none" id="guestAssignmentFields">
        <div class="row g-3">
          ${field({ name: 'project_ids', label: 'Projets autorisés', type: 'select', multiple: true, required: true, size: 6, options: [], help: 'Vous pouvez sélectionner plusieurs projets.' })}
          ${field({ name: 'task_ids', label: 'Tâches confiées', type: 'select', multiple: true, size: 6, options: [], help: 'Sélection facultative : l’invité pourra accéder aux projets sélectionnés.' })}
          <div class="col-12"><div class="small text-muted" id="guestAssignmentStatus">Choisissez un ou plusieurs projets de collaboration pour cet invité.</div></div>
        </div>
      </div>`}
      ${isEdit ? '' : '<div class="col-12"><div class="alert alert-info mb-0 small">Un mot de passe temporaire sera généré. L’utilisateur devra le changer à sa première connexion.</div></div>'}
    </div>`;
  }

  async function setupGuestAssignment(form) {
    const role = $('[name="role"]', form);
    const section = $('#guestAssignmentFields', form);
    if (!section) return;

    const projectSelect = $('[name="project_ids"]', form);
    const taskSelect = $('[name="task_ids"]', form);
    const status = $('#guestAssignmentStatus', form);
    const selectedProjectIds = () => Array.from(projectSelect.selectedOptions).map((option) => option.value);
    const updateVisibility = () => {
      const isGuest = role.value === 'guest';
      section.classList.toggle('d-none', !isGuest);
      projectSelect.disabled = !isGuest;
      projectSelect.required = isGuest;
      taskSelect.disabled = !isGuest || selectedProjectIds().length === 0;
    };

    role.addEventListener('change', () => {
      updateVisibility();
      if (role.value === 'guest' && selectedProjectIds().length) {
        projectSelect.dispatchEvent(new Event('change'));
      }
    });
    projectSelect.addEventListener('change', async () => {
      taskSelect.innerHTML = '';
      const projectIds = selectedProjectIds();
      taskSelect.disabled = !projectIds.length || role.value !== 'guest';
      if (!projectIds.length) {
        status.textContent = 'Choisissez un ou plusieurs projets de collaboration pour cet invité.';
        return;
      }

      status.textContent = 'Chargement des tâches des projets sélectionnés…';
      try {
        const tasks = [];
        for (const projectId of projectIds) {
          let result = await api('tasks', { params: { project_id: projectId, page: 1, per_page: 100 } });
          tasks.push(...(result.data || []));
          for (let pageNumber = 2; pageNumber <= result.last_page; pageNumber++) {
            result = await api('tasks', { params: { project_id: projectId, page: pageNumber, per_page: 100 } });
            tasks.push(...(result.data || []));
          }
        }

        taskSelect.innerHTML = tasks.map((task) => {
          const option = document.createElement('option');
          option.value = task.id;
          option.textContent = `${task.title} · ${L.task[task.status] || task.status}`;
          return option.outerHTML;
        }).join('');
        status.textContent = tasks.length
          ? 'Sélection facultative des tâches que l’invité pourra consulter et mettre à jour.'
          : 'Les projets sélectionnés ne contiennent pas encore de tâches.';
      } catch (error) {
        status.textContent = 'Impossible de charger les tâches des projets sélectionnés.';
        fail(error);
      }
    });

    updateVisibility();
    try {
      let result = await api('projects', { params: { per_page: 100, sort: 'name', direction: 'asc' } });
      const projects = [...(result.data || [])];
      for (let pageNumber = 2; pageNumber <= result.last_page; pageNumber++) {
        result = await api('projects', { params: { page: pageNumber, per_page: 100, sort: 'name', direction: 'asc' } });
        projects.push(...(result.data || []));
      }
      projects.forEach((project) => {
        projectSelect.add(new Option(`${project.reference} · ${project.name}`, project.id));
      });
      if (!projects.length) {
        status.textContent = 'Créez un projet avant d’ajouter un invité.';
      }
    } catch (error) {
      fail(error);
      status.textContent = 'Impossible de charger les projets disponibles.';
    }
  }

  function createUser() {
    formModal({
      title: 'Créer un utilisateur',
      size: 'modal-lg',
      html: userFields(null),
      onOpen: (form) => setupGuestAssignment(form),
      onSubmit: async (data, form, handle) => {
        const result = await api('users', { method: 'POST', body: data });
        await loadUsers();
        showTemporaryPassword(result.temporary_password, result.user, handle);
        return false;
      },
    });
  }

  function editUser(person) {
    if (!person) return;
    formModal({
      title: `Modifier ${fullName(person)}`,
      size: 'modal-lg',
      html: userFields(person, true),
      onSubmit: async (data) => {
        await api(`users/${person.id}`, { method: 'PUT', body: data });
        await loadUsers();
        toast('Utilisateur mis à jour.');
      },
    });
  }

  function showTemporaryPassword(password, person, existingHandle = null) {
    const body = `<div class="alert alert-success">Le compte de ${esc(fullName(person))} a été créé ou réinitialisé. Un changement de mot de passe est requis à la première connexion.</div>
        <label class="form-label" for="temporaryPassword">Mot de passe temporaire</label>
        <div class="input-group"><input class="form-control font-monospace" id="temporaryPassword" value="${esc(password)}" readonly><button type="button" class="btn btn-outline-primary" id="copyTemporaryPassword"><i class="bi bi-clipboard me-1"></i>Copier</button></div>
        <div class="form-text">Partagez ce mot de passe avec l’utilisateur par un canal sécurisé.</div>`;
    const handle = existingHandle || modal({ title: 'Mot de passe temporaire', body, size: 'modal-lg' });
    if (existingHandle) {
      $('.modal-title', handle.el).textContent = 'Compte créé';
      $('.modal-body', handle.el).innerHTML = body;
    }
    $('#copyTemporaryPassword', handle.el).addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(password);
        toast('Mot de passe temporaire copié.');
      } catch (error) {
        fail(new Error('La copie automatique est indisponible. Sélectionnez le mot de passe pour le copier.'));
      }
    });
  }

  async function resetPassword(person) {
    if (!person || !await confirmBox(`Réinitialiser le mot de passe de ${fullName(person)} ? Les autres sessions seront déconnectées.`, 'Réinitialiser')) return;
    try {
      const result = await api(`users/${person.id}/reset-password`, { method: 'POST' });
      await loadUsers();
      showTemporaryPassword(result.temporary_password, person);
    } catch (error) {
      fail(error);
    }
  }

  async function toggleUser(person) {
    if (!person || !await confirmBox(`${person.is_active ? 'Désactiver' : 'Activer'} le compte de ${fullName(person)} ?`, person.is_active ? 'Désactiver' : 'Activer')) return;
    try {
      await api(`users/${person.id}/toggle-active`, { method: 'POST' });
      await loadUsers();
      toast(person.is_active ? 'Compte désactivé.' : 'Compte activé.');
    } catch (error) {
      fail(error);
    }
  }

  async function deleteUser(person) {
    if (!person || !await confirmBox(`Supprimer le compte de ${fullName(person)} ? Ses sessions seront révoquées.`, 'Supprimer')) return;
    try {
      await api(`users/${person.id}`, { method: 'DELETE' });
      toast('Utilisateur supprimé.');
      await loadUsers();
    } catch (error) {
      fail(error);
    }
  }

  function init(user) {
    currentUser = user;
    if (user.role === 'admin') {
      $('#userActions').innerHTML = '<button type="button" class="btn btn-primary" id="createUser"><i class="bi bi-person-plus me-1"></i>Nouvel utilisateur</button>';
      $('#createUser').addEventListener('click', createUser);
    }
    $('#userSearch').addEventListener('input', debounce(() => { page = 1; loadUsers(); }, 250));
    ['userRoleFilter', 'userActiveFilter'].forEach((id) => $(`#${id}`).addEventListener('change', () => { page = 1; loadUsers(); }));
    loadUsers();
  }

  ready(init);
})();
