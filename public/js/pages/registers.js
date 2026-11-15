(function () {
  'use strict';

  const { $, $$, api, esc, L, fmtDate, fullName, field, formModal, modal, pager, confirm: confirmBox, toast, fail, debounce, ready } = App;
  const root = $('#registerPage');
  if (!root) return;

  const kind = root.dataset.registerType;
  const isDecision = kind === 'decisions';
  const endpoint = isDecision ? 'decisions' : 'meeting-minutes';
  const labels = isDecision ? {
    singular: 'décision',
    create: 'Nouvelle décision',
    empty: 'Aucune décision générale enregistrée.',
  } : {
    singular: 'compte rendu',
    create: 'Nouveau compte rendu',
    empty: 'Aucun compte rendu général enregistré.',
  };
  const DECISION_STATUSES = [['proposed', 'Proposée'], ['validated', 'Validée'], ['rejected', 'Rejetée'], ['superseded', 'Remplacée']];
  let currentUser;
  let page = 1;

  function canManage() {
    return ['admin', 'project_manager'].includes(currentUser.role);
  }

  function setActions() {
    $('#registerActions').innerHTML = canManage()
      ? `<button type="button" class="btn btn-primary text-nowrap" id="createRegister"><i class="bi bi-plus-lg me-1"></i>${esc(labels.create)}</button>`
      : '';
    $('#createRegister')?.addEventListener('click', () => editRecord());
  }

  function renderRecords(result) {
    const items = result.data || [];
    if (!items.length) {
      $('#registerList').innerHTML = `<div class="empty"><i class="bi ${isDecision ? 'bi-journal-check' : 'bi-journal-text'}"></i>${esc(labels.empty)}</div>`;
      pager($('#registerPager'), result, loadRecords);
      return;
    }

    $('#registerList').innerHTML = isDecision
      ? `<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Sujet</th><th>Décision</th><th>Date</th><th>Statut</th><th>Créée par</th><th></th></tr></thead><tbody>
        ${items.map((item) => `<tr>
          <td><button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-record="${esc(item.id)}">${esc(item.title)}</button></td>
          <td class="text-truncate-2" style="max-width:320px">${esc(item.decision)}</td>
          <td>${esc(item.decided_at ? fmtDate(item.decided_at) : '—')}</td>
          <td><span class="badge-soft bs-${esc(item.status)}">${esc(L.decision[item.status] || item.status)}</span></td>
          <td>${esc(item.creator ? fullName(item.creator) : '—')}</td>
          <td>${canManage() ? `<div class="d-flex justify-content-end gap-1">
            <button type="button" class="btn btn-sm btn-light-soft" data-edit-record="${esc(item.id)}" aria-label="Modifier ${esc(item.title)}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-record="${esc(item.id)}" aria-label="Supprimer ${esc(item.title)}"><i class="bi bi-trash"></i></button>
          </div>` : ''}</td>
        </tr>`).join('')}
      </tbody></table></div>`
      : `<div class="list-group list-group-flush">
        ${items.map((item) => `<article class="list-group-item px-0">
          <div class="d-flex justify-content-between gap-3">
            <div class="flex-grow-1 min-w-0">
              <button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-record="${esc(item.id)}">${esc(item.title)}</button>
              <div class="small text-muted">${esc(item.meeting_date ? fmtDate(item.meeting_date) : 'Date non définie')} · ${esc(item.creator ? fullName(item.creator) : '—')}</div>
              <div class="text-truncate-2 mt-2">${esc(item.content)}</div>
            </div>
            ${canManage() ? `<div class="d-flex gap-1 align-self-start">
              <button type="button" class="btn btn-sm btn-light-soft" data-edit-record="${esc(item.id)}" aria-label="Modifier ${esc(item.title)}"><i class="bi bi-pencil"></i></button>
              <button type="button" class="btn btn-sm btn-outline-danger" data-delete-record="${esc(item.id)}" aria-label="Supprimer ${esc(item.title)}"><i class="bi bi-trash"></i></button>
            </div>` : ''}
          </div>
        </article>`).join('')}
      </div>`;

    $$('[data-show-record]').forEach((button) => button.addEventListener('click', () => showRecord(items.find((item) => Number(item.id) === Number(button.dataset.showRecord)))));
    $$('[data-edit-record]').forEach((button) => button.addEventListener('click', () => editRecord(items.find((item) => Number(item.id) === Number(button.dataset.editRecord)))));
    $$('[data-delete-record]').forEach((button) => button.addEventListener('click', () => deleteRecord(items.find((item) => Number(item.id) === Number(button.dataset.deleteRecord)))));
    pager($('#registerPager'), result, loadRecords);
  }

  async function loadRecords(nextPage = 1) {
    page = nextPage;
    $('#registerList').innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement…</div>';
    $('#registerError').classList.add('d-none');
    try {
      const params = { q: $('#registerSearch').value.trim(), page, per_page: 20 };
      if (isDecision) params.status = $('#registerStatus').value;
      const result = await api(endpoint, { params });
      renderRecords(result);
    } catch (error) {
      $('#registerList').innerHTML = '<div class="text-center text-danger py-5">Impossible de charger le registre.</div>';
      $('#registerError').textContent = error.message || 'Une erreur est survenue.';
      $('#registerError').classList.remove('d-none');
      fail(error);
    }
  }

  function decisionFields(record, options) {
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Sujet de la décision', required: true, value: record?.title || '' })}
      ${field({ name: 'decision', label: 'Décision prise', type: 'textarea', rows: 4, required: true, value: record?.decision || '' })}
      ${field({ name: 'justification', label: 'Justification', type: 'textarea', rows: 3, value: record?.justification || '' })}
      ${field({ name: 'status', label: 'Statut', type: 'select', value: record?.status || 'proposed', options: DECISION_STATUSES, col: 'col-md-6' })}
      ${field({ name: 'decided_at', label: 'Date de décision', type: 'date', value: record?.decided_at || '', col: 'col-md-6' })}
      ${field({ name: 'participants', label: 'Participants', type: 'select', multiple: true, value: (record?.participants || []).map((person) => person.id), options: options.users.map((user) => [user.id, fullName(user)]) })}
      ${field({ name: 'discussion_id', label: 'Discussion générale liée', type: 'select', blank: 'Aucune', value: record?.discussion_id || '', options: options.discussions.map((discussion) => [discussion.id, discussion.title]), col: 'col-md-6' })}
      ${field({ name: 'supersedes_id', label: 'Remplace la décision', type: 'select', blank: 'Aucune', value: record?.supersedes_id || '', options: options.decisions.filter((item) => Number(item.id) !== Number(record?.id)).map((item) => [item.id, item.title]), col: 'col-md-6' })}
    </div>`;
  }

  function minuteFields(record, options) {
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: record?.title || '' })}
      ${field({ name: 'meeting_date', label: 'Date de réunion', type: 'date', value: record?.meeting_date || '', col: 'col-md-6' })}
      ${field({ name: 'event_id', label: 'Événement lié', type: 'select', blank: 'Aucun', value: record?.event_id || '', options: options.events.map((event) => [event.id, `${event.title} · ${fmtDate(event.starts_at)}`]), col: 'col-md-6' })}
      ${field({ name: 'attendees', label: 'Participants', type: 'select', multiple: true, size: 5, value: (record?.attendees || []).map(Number), options: options.users.map((user) => [user.id, fullName(user)]) })}
      ${field({ name: 'content', label: 'Compte rendu', type: 'textarea', rows: 8, required: true, value: record?.content || '' })}
    </div>`;
  }

  async function editRecord(record) {
    if (!canManage()) return;
    try {
      let options;
      if (isDecision) {
        const [users, discussions, decisions] = await Promise.all([
          api('users', { params: { is_active: 1, per_page: 100 } }),
          api('discussions', { params: { per_page: 100 } }),
          api('decisions', { params: { per_page: 100 } }),
        ]);
        options = { users: users.data || [], discussions: discussions.data || [], decisions: decisions.data || [] };
      } else {
        const now = new Date();
        const from = `${now.getFullYear() - 5}-01-01`;
        const to = `${now.getFullYear() + 5}-12-31`;
        const [users, events] = await Promise.all([
          api('users', { params: { is_active: 1, per_page: 100 } }),
          api('events', { params: { from, to } }),
        ]);
        options = {
          users: users.data || [],
          events: events.filter((event) => !event.project_id),
        };
        if (record?.event_id && !options.events.some((event) => Number(event.id) === Number(record.event_id))) {
          options.events.push({ id: record.event_id, title: `Événement associé (#${record.event_id})`, starts_at: record.meeting_date });
        }
      }

      formModal({
        title: record ? `Modifier ${labels.singular}` : labels.create,
        html: isDecision ? decisionFields(record, options) : minuteFields(record, options),
        size: 'modal-lg',
        onSubmit: async (data) => {
          await api(record ? `${endpoint}/${record.id}` : endpoint, { method: record ? 'PUT' : 'POST', body: data });
          await loadRecords(page);
          toast(record ? 'Mise à jour effectuée.' : 'Enregistrement créé.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function showRecord(record) {
    if (!record) return;
    try {
      const details = await api(`${endpoint}/${record.id}`);
      const body = isDecision
        ? `<dl class="info-grid">
            <div><dt>Statut</dt><dd>${esc(L.decision[details.status] || details.status)}</dd></div>
            <div><dt>Date</dt><dd>${esc(details.decided_at ? fmtDate(details.decided_at) : '—')}</dd></div>
            <div><dt>Créée par</dt><dd>${esc(details.creator ? fullName(details.creator) : '—')}</dd></div>
            <div><dt>Remplace</dt><dd>${esc(details.supersedes?.title || '—')}</dd></div>
          </dl>
          <div class="mt-3"><h3 class="h6">Décision</h3><div class="text-break">${esc(details.decision).replace(/\n/g, '<br>')}</div></div>
          ${details.justification ? `<div class="mt-3"><h3 class="h6">Justification</h3><div class="text-break">${esc(details.justification).replace(/\n/g, '<br>')}</div></div>` : ''}
          ${details.participants?.length ? `<div class="mt-3"><h3 class="h6">Participants</h3><div>${details.participants.map((person) => esc(fullName(person))).join(', ')}</div></div>` : ''}`
        : `<dl class="info-grid">
            <div><dt>Date de réunion</dt><dd>${esc(details.meeting_date ? fmtDate(details.meeting_date) : '—')}</dd></div>
            <div><dt>Créé par</dt><dd>${esc(details.creator ? fullName(details.creator) : '—')}</dd></div>
            <div><dt>Événement associé</dt><dd>${esc(details.event_id || '—')}</dd></div>
            <div><dt>Participants</dt><dd>${esc((details.attendees || []).length ? `${details.attendees.length} participant(s)` : '—')}</dd></div>
          </dl><div class="mt-3 text-break">${esc(details.content).replace(/\n/g, '<br>')}</div>`;
      modal({ title: esc(details.title), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  async function deleteRecord(record) {
    if (!record || !canManage() || !await confirmBox(`Supprimer ${labels.singular} « ${record.title} » ?`)) return;
    try {
      await api(`${endpoint}/${record.id}`, { method: 'DELETE' });
      await loadRecords(page);
      toast('Suppression effectuée.');
    } catch (error) {
      fail(error);
    }
  }

  ready((user) => {
    currentUser = user;
    setActions();
    $('#registerSearch').addEventListener('input', debounce(() => loadRecords(1), 300));
    $('#registerStatus')?.addEventListener('change', () => loadRecords(1));
    loadRecords();
  });
})();
