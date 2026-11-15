(function () {
  'use strict';

  const { $, $$, api, esc, L, field, formModal, confirm: confirmBox, toast, fail, ready } = App;
  const rankingRows = $('#rankingRows');
  const criteriaRows = $('#criteriaRows');
  let currentUser;
  let criteria = [];

  function renderRanking(items) {
    rankingRows.innerHTML = items.length ? items.map((project) => `<tr>
      <td><span class="badge rounded-pill text-bg-light">${esc(project.rank)}</span></td>
      <td><a class="fw-semibold" href="/projects/${encodeURIComponent(project.id)}">${esc(project.reference)} · ${esc(project.name)}</a></td>
      <td>${esc(project.type?.name || '—')}</td>
      <td><span class="badge-soft bs-${esc(project.status)}">${esc(L.status[project.status] || project.status)}</span></td>
      <td><span class="badge-soft bp-${esc(project.priority)}">${esc(L.priority[project.priority] || project.priority)}</span></td>
      <td class="fw-semibold">${project.priority_score === null ? '—' : `${esc(project.priority_score)} / 100`}</td>
    </tr>`).join('') : '<tr><td colspan="6" class="text-center text-muted py-5">Aucun projet évalué pour ce filtre.</td></tr>';
  }

  async function loadRanking() {
    rankingRows.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement du classement…</td></tr>';
    try {
      const items = await api('evaluations/ranking', { params: { status: $('#rankingStatus').value, limit: 200 } });
      renderRanking(items);
    } catch (error) {
      rankingRows.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Impossible de charger le classement.</td></tr>';
      fail(error);
    }
  }

  function renderCriteria() {
    criteriaRows.innerHTML = criteria.length ? criteria.map((criterion) => `<tr>
      <td class="fw-semibold">${esc(criterion.name)}</td>
      <td>${esc(criterion.description || '—')}</td>
      <td>${esc(criterion.weight ?? 1)}</td>
      <td><span class="badge-soft ${criterion.is_active ? 'bs-completed' : 'bs-archived'}">${criterion.is_active ? 'Actif' : 'Inactif'}</span></td>
      <td><div class="d-flex justify-content-end gap-1">
        ${currentUser.role === 'admin' ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-criterion="${esc(criterion.id)}" aria-label="Modifier ${esc(criterion.name)}"><i class="bi bi-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-light-soft" data-toggle-criterion="${esc(criterion.id)}" aria-label="${criterion.is_active ? 'Désactiver' : 'Activer'} ${esc(criterion.name)}"><i class="bi bi-${criterion.is_active ? 'pause' : 'play'}-circle"></i></button>
          <button type="button" class="btn btn-sm btn-outline-danger" data-delete-criterion="${esc(criterion.id)}" aria-label="Supprimer ${esc(criterion.name)}"><i class="bi bi-trash"></i></button>` : ''}
      </div></td>
    </tr>`).join('') : '<tr><td colspan="5" class="text-center text-muted py-5">Aucun critère d’évaluation configuré.</td></tr>';
    $$('[data-edit-criterion]').forEach((button) => button.addEventListener('click', () => editCriterion(criteria.find((item) => Number(item.id) === Number(button.dataset.editCriterion)))));
    $$('[data-toggle-criterion]').forEach((button) => button.addEventListener('click', () => toggleCriterion(criteria.find((item) => Number(item.id) === Number(button.dataset.toggleCriterion)))));
    $$('[data-delete-criterion]').forEach((button) => button.addEventListener('click', () => deleteCriterion(criteria.find((item) => Number(item.id) === Number(button.dataset.deleteCriterion)))));
  }

  async function loadCriteria() {
    criteriaRows.innerHTML = '<tr><td colspan="5" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des critères…</td></tr>';
    try {
      criteria = await api('evaluation-criteria', { params: { include_inactive: currentUser.role === 'admin' && $('#includeInactiveCriteria').checked } });
      renderCriteria();
    } catch (error) {
      criteriaRows.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-5">Impossible de charger les critères.</td></tr>';
      fail(error);
    }
  }

  function criterionFields(criterion) {
    return `<div class="row g-3">
      ${field({ name: 'name', label: 'Nom du critère', required: true, value: criterion?.name || '' })}
      ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: criterion?.description || '' })}
      ${field({ name: 'weight', label: 'Pondération', type: 'number', min: 0, max: 100, step: '0.01', value: criterion?.weight ?? 1, col: 'col-md-4' })}
      ${field({ name: 'sort_order', label: 'Ordre', type: 'number', min: 0, value: criterion?.sort_order ?? 0, col: 'col-md-4' })}
      ${field({ name: 'is_active', label: 'Critère actif', type: 'checkbox', value: criterion?.is_active ?? true })}
    </div>`;
  }

  function createCriterion() {
    formModal({ title: 'Nouveau critère', html: criterionFields(), onSubmit: async (data) => {
      await api('evaluation-criteria', { method: 'POST', body: data });
      await loadCriteria();
      toast('Critère créé.');
    } });
  }

  function editCriterion(criterion) {
    formModal({ title: 'Modifier le critère', html: criterionFields(criterion), onSubmit: async (data) => {
      await api(`evaluation-criteria/${criterion.id}`, { method: 'PUT', body: data });
      await loadCriteria();
      toast('Critère mis à jour.');
    } });
  }

  async function toggleCriterion(criterion) {
    try {
      await api(`evaluation-criteria/${criterion.id}`, { method: 'PUT', body: { is_active: !criterion.is_active } });
      await loadCriteria();
      toast(criterion.is_active ? 'Critère désactivé.' : 'Critère activé.');
    } catch (error) {
      fail(error);
    }
  }

  async function deleteCriterion(criterion) {
    if (!criterion || !await confirmBox(`Supprimer le critère « ${criterion.name} » ?`, 'Supprimer')) return;
    try {
      await api(`evaluation-criteria/${criterion.id}`, { method: 'DELETE' });
      await loadCriteria();
      toast('Critère supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  function init(user) {
    currentUser = user;
    if (user.role === 'admin') {
      $('#criterionActions').innerHTML = '<button type="button" class="btn btn-primary" id="createCriterion"><i class="bi bi-plus-lg me-1"></i>Nouveau critère</button>';
      $('#createCriterion').addEventListener('click', createCriterion);
    }
    $('#inactiveCriteriaWrap').classList.toggle('d-none', user.role !== 'admin');
    $('#rankingStatus').addEventListener('change', loadRanking);
    $('#includeInactiveCriteria').addEventListener('change', loadCriteria);
    loadRanking();
    loadCriteria();
  }

  ready(init);
})();
