(function () {
  'use strict';

  const { $, $$, api, esc, L, fmtDate, fullName, field, formModal, modal, pager, confirm: confirmBox, toast, fail, debounce, ready } = App;
  const rows = $('#taskRows');
  const STATUSES = [['todo', 'À faire'], ['in_progress', 'En cours'], ['review', 'En revue'], ['done', 'Terminée'], ['cancelled', 'Annulée']];
  const PRIORITIES = [['low', 'Faible'], ['medium', 'Moyenne'], ['high', 'Haute'], ['critical', 'Critique']];
  let currentUser;
  let page = 1;

  function filters() {
    return {
      q: $('#taskSearch').value.trim(),
      status: $('#taskStatus').value,
      priority: $('#taskPriority').value,
      project_id: $('#taskProject').value,
      overdue: $('#taskOverdue').checked,
      assignee_id: $('#taskShowAll').checked ? '' : 'me',
      page,
      per_page: 20,
    };
  }

  function renderTasks(tasks) {
    if (!tasks.length) {
      rows.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-check2-square fs-3 d-block mb-2"></i>Aucune tâche ne correspond à ces filtres.</td></tr>';
      return;
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);
    rows.innerHTML = tasks.map((task) => {
      const due = task.due_date ? new Date(`${String(task.due_date).slice(0, 10)}T00:00:00`) : null;
      const overdue = due && due < today && !['done', 'cancelled'].includes(task.status);
      const status = L.task[task.status] || task.status;
      const priority = L.priority[task.priority] || task.priority;
      const statusCell = task.can_change_status
        ? `<select class="form-select form-select-sm" data-task-status="${esc(task.id)}" aria-label="Statut de ${esc(task.title)}">${STATUSES.map(([value, label]) => `<option value="${value}"${task.status === value ? ' selected' : ''}>${label}</option>`).join('')}</select>`
        : `<span class="badge-soft bs-${esc(task.status)}">${esc(status)}</span>`;
      return `<tr>
        <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none fw-semibold" data-task-detail="${esc(task.id)}">${esc(task.title)}</button>${task.description ? `<div class="small text-muted text-truncate-2">${esc(task.description)}</div>` : ''}</td>
        <td>${task.project ? `<a href="/projects/${encodeURIComponent(task.project.id)}">${esc(task.project.reference || task.project.name)}</a>` : '<span class="text-muted">Personnel</span>'}</td>
        <td>${esc(task.assignee?.name || 'Non assignée')}</td>
        <td class="${overdue ? 'text-danger fw-semibold' : ''}">${esc(task.due_date ? fmtDate(task.due_date) : '—')}${overdue ? '<div class="small">En retard</div>' : ''}</td>
        <td><span class="badge-soft bp-${esc(task.priority)}">${esc(priority)}</span></td>
        <td>${statusCell}</td>
        <td><div class="d-flex justify-content-end gap-1">
          ${task.can_edit ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-task="${esc(task.id)}" aria-label="Modifier ${esc(task.title)}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-task="${esc(task.id)}" aria-label="Supprimer ${esc(task.title)}"><i class="bi bi-trash"></i></button>` : ''}
        </div></td>
      </tr>`;
    }).join('');

    $$('[data-task-status]').forEach((input) => input.addEventListener('change', async () => {
      try {
        await api(`tasks/${input.dataset.taskStatus}`, { method: 'PUT', body: { status: input.value } });
        toast('Statut de la tâche mis à jour.');
        await loadTasks(page);
      } catch (error) {
        fail(error);
        await loadTasks(page);
      }
    }));
    $$('[data-edit-task]').forEach((button) => button.addEventListener('click', () => {
      editTask(tasks.find((task) => Number(task.id) === Number(button.dataset.editTask)));
    }));
    $$('[data-task-detail]').forEach((button) => button.addEventListener('click', () => {
      showTask(tasks.find((task) => Number(task.id) === Number(button.dataset.taskDetail)));
    }));
    $$('[data-delete-task]').forEach((button) => button.addEventListener('click', () => {
      deleteTask(tasks.find((task) => Number(task.id) === Number(button.dataset.deleteTask)));
    }));
  }

  async function loadTasks(nextPage = 1) {
    page = nextPage;
    rows.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des tâches…</td></tr>';
    try {
      const result = await api('tasks', { params: filters() });
      renderTasks(result.data || []);
      pager($('#taskPager'), result, loadTasks);
    } catch (error) {
      rows.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-5">Impossible de charger les tâches. Réessayez.</td></tr>';
      fail(error);
    }
  }

  async function showTask(task) {
    if (!task) return;
    try {
      const details = await api(`tasks/${task.id}`);
      const subtasks = details.subtasks || [];
      const body = `<dl class="info-grid">
        <div><dt>Statut</dt><dd>${esc(L.task[details.status] || details.status)}</dd></div>
        <div><dt>Priorité</dt><dd>${esc(L.priority[details.priority] || details.priority)}</dd></div>
        <div><dt>Assignée à</dt><dd>${esc(details.assignee ? fullName(details.assignee) : 'Non assignée')}</dd></div>
        <div><dt>Créée par</dt><dd>${esc(details.creator ? fullName(details.creator) : '—')}</dd></div>
        <div><dt>Début</dt><dd>${esc(details.start_date ? fmtDate(details.start_date) : '—')}</dd></div>
        <div><dt>Échéance</dt><dd>${esc(details.due_date ? fmtDate(details.due_date) : '—')}</dd></div>
        <div><dt>Durée estimée</dt><dd>${details.estimated_minutes ? `${esc(details.estimated_minutes)} min` : '—'}</dd></div>
        ${details.project ? `<div><dt>Projet</dt><dd><a href="/projects/${encodeURIComponent(details.project.id)}">${esc(details.project.reference)} · ${esc(details.project.name)}</a></dd></div>` : ''}
      </dl>
      ${details.description ? `<div class="mt-3"><h3 class="h6">Description</h3><div class="text-break">${esc(details.description).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${subtasks.length ? `<div class="mt-3"><h3 class="h6">Sous-tâches</h3><ul class="mb-0">${subtasks.map((subtask) => `<li>${esc(subtask.title)} · ${esc(L.task[subtask.status] || subtask.status)}</li>`).join('')}</ul></div>` : ''}`;
      modal({ title: esc(details.title), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function taskFields(task) {
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: task?.title || '' })}
      ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: task?.description || '' })}
      ${field({ name: 'priority', label: 'Priorité', type: 'select', value: task?.priority || 'medium', options: PRIORITIES, col: 'col-md-6' })}
      ${field({ name: 'start_date', label: 'Date de début', type: 'date', value: task?.start_date || '', col: 'col-md-6' })}
      ${field({ name: 'due_date', label: 'Échéance', type: 'date', value: task?.due_date || '', col: 'col-md-6' })}
      ${field({ name: 'estimated_minutes', label: 'Durée estimée (minutes)', type: 'number', min: 0, value: task?.estimated_minutes ?? '', col: 'col-md-6' })}
    </div>`;
  }

  function editTask(task) {
    if (!task) return;
    formModal({
      title: 'Modifier la tâche',
      html: taskFields(task),
      onSubmit: async (data) => {
        await api(`tasks/${task.id}`, { method: 'PUT', body: data });
        await loadTasks(page);
        toast('Tâche mise à jour.');
      },
    });
  }

  function createTask() {
    formModal({
      title: 'Nouvelle tâche personnelle',
      html: `${taskFields()}<input type="hidden" name="assignee_id" value="${esc(currentUser.id)}">`,
      onSubmit: async (data) => {
        data.status = 'todo';
        await api('tasks', { method: 'POST', body: data });
        $('#taskShowAll').checked = false;
        await loadTasks(1);
        toast('Tâche créée et ajoutée à vos tâches.');
      },
    });
  }

  async function deleteTask(task) {
    if (!task || !await confirmBox(`Supprimer la tâche « ${task.title} » ?`, 'Supprimer')) return;
    try {
      await api(`tasks/${task.id}`, { method: 'DELETE' });
      toast('Tâche supprimée.');
      await loadTasks(page);
    } catch (error) {
      fail(error);
    }
  }

  async function init(user) {
    currentUser = user;
    if (user.role === 'guest') $('#taskShowAll').closest('.col-12').classList.add('d-none');
    if (user.role !== 'guest') {
      $('#taskActions').innerHTML = '<button type="button" class="btn btn-primary" id="createTask"><i class="bi bi-plus-lg me-1"></i>Nouvelle tâche</button>';
      $('#createTask').addEventListener('click', createTask);
    }

    try {
      const result = await api('projects', { params: { per_page: 100 } });
      $('#taskProject').innerHTML += (result.data || []).map((project) => `<option value="${esc(project.id)}">${esc(project.reference)} · ${esc(project.name)}</option>`).join('');
    } catch (error) {
      fail(error);
    }

    const reload = () => loadTasks(1);
    $('#taskSearch').addEventListener('input', debounce(reload, 250));
    ['taskStatus', 'taskPriority', 'taskProject', 'taskOverdue', 'taskShowAll'].forEach((id) => $(`#${id}`).addEventListener('change', reload));
    await loadTasks();
  }

  ready(init);
})();
