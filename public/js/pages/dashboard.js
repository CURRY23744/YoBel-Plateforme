(function () {
  'use strict';

  const { $, api, esc, L, fmtDate, fmtDateTime, ago, fullName, fail, ready, activitySubject, activityAction } = App;
  let charts = [];

  function renderKpis(data, stats) {
    const cards = [
      ['bi-kanban', 'Projets visibles', stats.total || 0, ''],
      ['bi-lightning-charge', 'Projets en cours', data.projects_by_status?.in_progress || 0, 'teal'],
      ['bi-exclamation-triangle', 'Projets en retard', stats.overdue || 0, 'red'],
      ['bi-check2-square', 'Tâches prioritaires', (data.my_tasks || []).length, 'amber'],
    ];
    $('#kpis').innerHTML = cards.map(([icon, label, value, tone]) => `<div class="col-6 col-xl-3"><div class="card h-100"><div class="card-body">
      <div class="kpi"><div class="ico ${tone}"><i class="bi ${icon}"></i></div><div><div class="num">${esc(value)}</div><div class="lbl">${esc(label)}</div></div></div>
    </div></div></div>`).join('');
  }

  function renderChart(canvasId, labels, values, colors, type = 'doughnut') {
    const canvas = $(`#${canvasId}`);
    if (!canvas) return;
    if (!labels.length || typeof Chart === 'undefined') {
      canvas.parentElement.innerHTML = '<div class="empty py-5"><i class="bi bi-bar-chart"></i>Aucune donnée à afficher.</div>';
      return;
    }
    charts.push(new Chart(canvas, {
      type,
      data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 0, hoverOffset: 4 }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        ...(type === 'bar' ? { scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } : {}),
      },
    }));
  }

  function renderCharts(data, stats, types) {
    charts.forEach((chart) => chart.destroy());
    charts = [];
    const statuses = data.projects_by_status || {};
    const statusEntries = Object.entries(statuses);
    renderChart('chartStatus', statusEntries.map(([status]) => L.status[status] || status), statusEntries.map(([, count]) => count),
      ['#234579', '#2f8f9d', '#5a6fa8', '#e3a32f', '#3f7cac', '#2e8b6b', '#a23d2e', '#667085']);

    const byType = stats.by_type || {};
    const typeEntries = Object.entries(byType);
    const typeMap = new Map(types.map((type) => [String(type.id), type]));
    renderChart('chartType', typeEntries.map(([id]) => typeMap.get(id)?.name || 'Autre'), typeEntries.map(([, count]) => count),
      typeEntries.map(([id]) => /^#[\da-f]{6}$/i.test(typeMap.get(id)?.color || '') ? typeMap.get(id).color : '#667085'));
  }

  function renderList(id, items, render, emptyText, icon) {
    const target = $(`#${id}`);
    target.innerHTML = items.length ? items.map(render).join('') : App.empty(emptyText, icon);
  }

  function renderDashboard(data, stats, types, user) {
    $('#hello').textContent = `Bonjour ${user.first_name || ''}`.trim();
    $('#today').textContent = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());
    if (['admin', 'project_manager'].includes(user.role)) {
      $('#headActions').innerHTML = '<a class="btn btn-primary" href="/projects"><i class="bi bi-kanban me-1"></i>Voir les projets</a>';
    }
    renderKpis(data, stats);
    renderCharts(data, stats, types);

    renderList('myTasks', data.my_tasks || [], (task) => `<a class="list-line text-decoration-none" href="${task.project_id ? `/projects/${encodeURIComponent(task.project_id)}` : '/tasks'}">
      <i class="bi bi-check2-square text-primary"></i><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${esc(task.title)}</div><div class="small text-muted">${esc(L.task[task.status] || task.status)}</div></div>
      <span class="badge-soft bp-${esc(task.priority)}">${esc(L.priority[task.priority] || task.priority)}</span>${task.due_date ? `<span class="small text-muted">${esc(fmtDate(task.due_date))}</span>` : ''}
    </a>`, 'Aucune tâche à suivre.', 'bi-check2-square');

    renderList('events', data.upcoming_events || [], (event) => `<a class="list-line text-decoration-none" href="/agenda">
      <i class="bi bi-calendar-event text-primary"></i><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${esc(event.title)}</div><div class="small text-muted">${esc(App.L.eventType[event.type] || event.type)}</div></div>
      <span class="small text-nowrap">${esc(fmtDateTime(event.starts_at))}</span>
    </a>`, 'Aucun événement à venir.', 'bi-calendar2');

    renderList('milestones', data.upcoming_milestones || [], (milestone) => `<a class="list-line text-decoration-none" href="/projects/${encodeURIComponent(milestone.project_id)}">
      <span class="milestone-dot"></span><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${esc(milestone.title)}</div><div class="small text-muted">Jalon du projet</div></div><span class="small text-nowrap">${esc(fmtDate(milestone.due_date))}</span>
    </a>`, 'Aucun jalon prévu dans les 30 prochains jours.', 'bi-flag');

    renderList('overdue', data.overdue_projects || [], (project) => `<a class="list-line text-decoration-none" href="/projects/${encodeURIComponent(project.id)}">
      <i class="bi bi-exclamation-circle text-danger"></i><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${esc(project.reference)} · ${esc(project.name)}</div><div class="small text-muted">${esc(L.status[project.status] || project.status)}</div></div>
      <span class="small text-danger">${esc(fmtDate(project.planned_end_date))}</span>
    </a>`, 'Aucun projet en retard.', 'bi-check-circle');

    if (user.role === 'admin') {
      $('#adminRow').classList.remove('d-none');
      renderList('activity', data.recent_activity || [], (entry) => `<div class="list-line">
        <i class="bi bi-clock-history text-muted"></i><div class="flex-grow-1 min-w-0"><div class="fw-semibold text-truncate">${esc(entry.user?.name || 'Système')} · ${esc(activityAction(entry.action, true))}</div><div class="small text-muted text-truncate">${esc(activitySubject(entry))}</div></div><span class="small text-muted">${esc(ago(entry.created_at))}</span>
      </div>`, 'Aucune activité récente.', 'bi-clock-history');
      renderList('latestUsers', data.latest_users || [], (person) => `<div class="list-line">
        ${App.avatar(person, 'sm')}<div class="flex-grow-1"><div class="fw-semibold">${esc(fullName(person))}</div><div class="small text-muted">${esc(L.role[person.role] || person.role)}</div></div>
      </div>`, 'Aucun compte récent.', 'bi-people');
    }
  }

  async function init(user) {
    try {
      const [data, stats, types] = await Promise.all([
        api('dashboard'),
        api('projects/stats'),
        api('project-types'),
      ]);
      renderDashboard(data, stats, types, user);
    } catch (error) {
      $('#kpis').innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0">Impossible de charger le tableau de bord.</div></div>';
      fail(error);
    }
  }

  ready(init);
})();
