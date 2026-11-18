(function () {
  'use strict';

  const { $, api, esc, fmtDate, ready, toast, fail } = App;
  const WEEKDAY_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
  let weekStart;
  let loading = false;

  function localDate(value) {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
  }

  function dateKey(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
  }

  function mondayOf(date) {
    const monday = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
    return monday;
  }

  function moveWeek(amount) {
    weekStart.setDate(weekStart.getDate() + amount * 7);
    loadPlanning();
  }

  function renderWeeklyRows(weekly) {
    const byWeekday = new Map(weekly.map((day) => [Number(day.weekday), day]));
    $('#planningWeeklyRows').innerHTML = WEEKDAY_LABELS.map((label, index) => {
      const day = byWeekday.get(index + 1) || {};
      return `<tr data-weekly-day="${index + 1}">
        <th scope="row">${label}</th>
        <td><div class="form-check form-switch mb-0"><input class="form-check-input" type="checkbox" data-weekly-available ${day.is_available ? 'checked' : ''} aria-label="Disponible le ${label.toLowerCase()}"></div></td>
        <td><input class="form-control form-control-sm" type="time" data-weekly-start value="${esc(day.start_time || '09:00')}" ${day.is_available ? '' : 'disabled'} aria-label="Début ${label.toLowerCase()}"></td>
        <td><input class="form-control form-control-sm" type="time" data-weekly-end value="${esc(day.end_time || '17:00')}" ${day.is_available ? '' : 'disabled'} aria-label="Fin ${label.toLowerCase()}"></td>
      </tr>`;
    }).join('');
  }

  function renderExceptionRows(days) {
    $('#planningExceptionRows').innerHTML = days.map((day) => `
      <tr data-exception-day="${esc(day.date)}">
        <th scope="row"><span class="d-block">${esc(day.label)}</span><span class="small text-muted">${esc(fmtDate(day.date))}</span></th>
        <td><select class="form-select form-select-sm" data-exception-mode aria-label="Exception ${esc(day.label.toLowerCase())}">
          <option value="default" ${!day.exception_mode ? 'selected' : ''}>Habituelle</option>
          <option value="available" ${day.exception_mode === 'available' ? 'selected' : ''}>Disponible exceptionnellement</option>
          <option value="unavailable" ${day.exception_mode === 'unavailable' ? 'selected' : ''}>Indisponible / absence</option>
        </select></td>
        <td><input class="form-control form-control-sm" type="time" data-exception-start value="${esc(day.exception_mode === 'available' ? day.start_time || '09:00' : '09:00')}" ${day.exception_mode === 'available' ? '' : 'disabled'}></td>
        <td><input class="form-control form-control-sm" type="time" data-exception-end value="${esc(day.exception_mode === 'available' ? day.end_time || '17:00' : '17:00')}" ${day.exception_mode === 'available' ? '' : 'disabled'}></td>
        <td><input class="form-control form-control-sm" type="text" maxlength="255" data-exception-reason value="${esc(day.reason || '')}" placeholder="Ex. congé, rendez-vous"></td>
      </tr>`).join('');
  }

  function renderTeam(team) {
    if (!Array.isArray(team)) return;
    $('#planningTeamSection').classList.remove('d-none');
    $('#planningTeam').innerHTML = team.map((member) => `
      <div class="col-12 col-xl-6">
        <article class="card h-100">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
              <h3 class="h6 mb-0">${esc(member.user.name)}</h3>
              <span class="badge text-bg-light">${esc({ admin: 'Administrateur', project_manager: 'Chef de projet', member: 'Membre', guest: 'Invité' }[member.user.role] || member.user.role)}</span>
            </div>
            <div class="planning-team-days">${member.days.map((day) => `
              <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-top">
                <span>${esc(day.label)} <span class="small text-muted">${esc(fmtDate(day.date))}</span></span>
                ${!day.is_configured
                  ? '<span class="badge-soft bs-idea">Non renseigné</span>'
                  : day.is_available
                  ? `<span class="badge-soft bs-completed">${esc(day.start_time || '—')}–${esc(day.end_time || '—')}</span>`
                  : `<span class="badge-soft bs-on_hold">${day.exception_mode === 'unavailable' ? 'Absent' : 'Indisponible'}</span>`}
              </div>
              ${day.reason ? `<div class="small text-muted text-end pb-1">${esc(day.reason)}</div>` : ''}`).join('')}
            </div>
          </div>
        </article>
      </div>`).join('') || '<div class="col-12"><div class="text-muted">Aucun membre actif.</div></div>';
  }

  function wireInputs() {
    $('[data-weekly-available]') && document.querySelectorAll('[data-weekly-available]').forEach((input) => {
      input.addEventListener('change', () => {
        const row = input.closest('[data-weekly-day]');
        row.querySelectorAll('[data-weekly-start], [data-weekly-end]').forEach((time) => { time.disabled = !input.checked; });
      });
    });

    document.querySelectorAll('[data-exception-mode]').forEach((input) => {
      input.addEventListener('change', () => {
        const row = input.closest('[data-exception-day]');
        const available = input.value === 'available';
        row.querySelectorAll('[data-exception-start], [data-exception-end]').forEach((time) => { time.disabled = !available; });
      });
    });
  }

  async function loadPlanning() {
    if (loading) return;
    loading = true;
    const start = dateKey(weekStart);
    try {
      const data = await api('planning', { params: { week_start: start } });
      $('#planningWeekTitle').textContent = `${fmtDate(data.week_start)} – ${fmtDate(data.week_end)}`;
      renderWeeklyRows(data.weekly || []);
      renderExceptionRows(data.days || []);
      renderTeam(data.team);
      wireInputs();
      const url = new URL(location.href);
      url.searchParams.set('week_start', start);
      history.replaceState(null, '', url);
    } catch (error) {
      $('#planningWeeklyRows').innerHTML = '<tr><td colspan="4" class="text-danger">Impossible de charger le planning.</td></tr>';
      fail(error);
    } finally {
      loading = false;
    }
  }

  async function savePlanning(event) {
    event.preventDefault();
    const weekly = Array.from(document.querySelectorAll('[data-weekly-day]')).map((row) => ({
      weekday: Number(row.dataset.weeklyDay),
      is_available: $('[data-weekly-available]', row).checked,
      start_time: $('[data-weekly-start]', row).value || null,
      end_time: $('[data-weekly-end]', row).value || null,
    }));
    const exceptions = Array.from(document.querySelectorAll('[data-exception-day]'))
      .filter((row) => $('[data-exception-mode]', row).value !== 'default')
      .map((row) => ({
        date: row.dataset.exceptionDay,
        mode: $('[data-exception-mode]', row).value,
        start_time: $('[data-exception-start]', row).value || null,
        end_time: $('[data-exception-end]', row).value || null,
        reason: $('[data-exception-reason]', row).value.trim() || null,
      }));

    try {
      await api('planning', { method: 'PUT', body: { week_start: dateKey(weekStart), weekly, exceptions } });
      toast('Votre planning a été enregistré.');
      await loadPlanning();
    } catch (error) {
      fail(error);
    }
  }

  ready(() => {
    const params = new URLSearchParams(location.search);
    const requestedWeek = params.get('week_start');
    const initialDate = requestedWeek && /^\d{4}-\d{2}-\d{2}$/.test(requestedWeek)
      ? localDate(requestedWeek)
      : new Date();
    weekStart = mondayOf(initialDate);
    $('#planningPrevious').addEventListener('click', () => moveWeek(-1));
    $('#planningNext').addEventListener('click', () => moveWeek(1));
    $('#planningToday').addEventListener('click', () => { weekStart = mondayOf(new Date()); loadPlanning(); });
    $('#planningForm').addEventListener('submit', savePlanning);
    loadPlanning();
  });
})();
