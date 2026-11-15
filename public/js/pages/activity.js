(function () {
  'use strict';

  const { $, api, esc, fullName, fmtDateTime, pager, download, fail, debounce, ready } = App;
  const rows = $('#activityRows');
  let page = 1;

  function filters() {
    return {
      from: $('#activityFrom').value,
      to: $('#activityTo').value,
      user_id: $('#activityUser').value,
      project_id: $('#activityProject').value,
      action: $('#activityAction').value.trim(),
      page,
      per_page: 30,
    };
  }

  function renderLogs(result) {
    const logs = result.data || [];
    rows.innerHTML = logs.length ? logs.map((log) => {
      const subject = log.subject_type ? log.subject_type.split('\\').pop() : '';
      return `<tr>
        <td class="text-nowrap">${esc(fmtDateTime(log.created_at))}</td>
        <td>${esc(fullName(log.user))}</td>
        <td>${log.project ? `<a href="/projects/${encodeURIComponent(log.project.id)}">${esc(log.project.reference || log.project.name)}</a>` : '—'}</td>
        <td><span class="badge-soft bs-idea">${esc(log.action || '—')}</span></td>
        <td>${esc(subject ? `${subject}${log.subject_id ? ` #${log.subject_id}` : ''}` : '—')}</td>
        <td>${esc(log.description || '—')}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-clock-history fs-3 d-block mb-2"></i>Aucune activité pour ces filtres.</td></tr>';
    pager($('#activityPager'), result, (nextPage) => { page = nextPage; loadActivity(); });
  }

  async function loadActivity() {
    rows.innerHTML = '<tr><td colspan="6" class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement du journal…</td></tr>';
    try {
      const result = await api('activity-logs', { params: filters() });
      renderLogs(result);
    } catch (error) {
      rows.innerHTML = '<tr><td colspan="6" class="text-center text-danger py-5">Impossible de charger le journal.</td></tr>';
      fail(error);
    }
  }

  async function loadFilters() {
    try {
      const [users, projects] = await Promise.all([
        api('users', { params: { per_page: 100 } }),
        api('projects', { params: { per_page: 100 } }),
      ]);
      $('#activityUser').innerHTML += (users.data || []).map((user) => `<option value="${esc(user.id)}">${esc(fullName(user))}</option>`).join('');
      $('#activityProject').innerHTML += (projects.data || []).map((project) => `<option value="${esc(project.id)}">${esc(project.reference)} · ${esc(project.name)}</option>`).join('');
    } catch (error) {
      fail(error);
    }
  }

  function exportCsv() {
    const params = new URLSearchParams({ export: 'csv' });
    Object.entries(filters()).forEach(([key, value]) => {
      if (value !== '' && key !== 'page' && key !== 'per_page') params.set(key, String(value));
    });
    download(`activity-logs?${params.toString()}`, 'journal-activite.csv');
  }

  function init() {
    ['activityFrom', 'activityTo', 'activityUser', 'activityProject'].forEach((id) => $(`#${id}`).addEventListener('change', () => { page = 1; loadActivity(); }));
    $('#activityAction').addEventListener('input', debounce(() => { page = 1; loadActivity(); }, 300));
    $('#exportActivity').addEventListener('click', exportCsv);
    loadFilters();
    loadActivity();
  }

  ready(init);
})();
