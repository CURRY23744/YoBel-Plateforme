(function () {
  'use strict';

  const { $, $$, api, esc, fmtDateTime, toLocalInput, fullName, field, formModal, modal, confirm: confirmBox, toast, fail, ready } = App;
  const EVENT_TYPES = [
    ['meeting', 'Réunion'],
    ['delivery', 'Livraison'],
    ['client_meeting', 'Rendez-vous client'],
    ['reminder', 'Rappel'],
    ['deadline', 'Échéance'],
    ['other', 'Autre'],
  ];
  const TYPE_LABELS = Object.fromEntries(EVENT_TYPES);
  const RESPONSE_LABELS = { pending: 'En attente', accepted: 'Acceptée', declined: 'Déclinée', tentative: 'Peut-être' };
  const RESPONSE_OPTIONS = [['accepted', 'Accepter'], ['tentative', 'Peut-être'], ['declined', 'Décliner']];
  let currentUser;
  let visibleMonth = new Date();
  visibleMonth.setDate(1);
  let selectedDate = dateKey(new Date());
  let events = [];

  function dateKey(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  function gridRange() {
    const first = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth(), 1);
    const start = new Date(first);
    start.setDate(first.getDate() - ((first.getDay() + 6) % 7));
    const end = new Date(start);
    end.setDate(start.getDate() + 41);
    return { start, end };
  }

  function eventStart(event) {
    return App.naive(event.starts_at);
  }

  function eventEnd(event) {
    return event.ends_at ? App.naive(event.ends_at) : eventStart(event);
  }

  function eventOccursOn(event, date) {
    const key = dateKey(date);
    return dateKey(eventStart(event)) <= key && dateKey(eventEnd(event)) >= key;
  }

  function eventParticipants(event) {
    return event.participants || [];
  }

  function currentInvitation(event) {
    return eventParticipants(event).find((participant) => Number(participant.id) === Number(currentUser.id));
  }

  function eventForDate(date) {
    return events.filter((event) => eventOccursOn(event, date));
  }

  function renderCalendar() {
    $('#monthTitle').textContent = new Intl.DateTimeFormat('fr-FR', { month: 'long', year: 'numeric' }).format(visibleMonth);
    const { start } = gridRange();
    const today = dateKey(new Date());
    const cells = [];

    for (let offset = 0; offset < 42; offset += 1) {
      const date = new Date(start);
      date.setDate(start.getDate() + offset);
      const key = dateKey(date);
      const dayEvents = eventForDate(date);
      cells.push(`<div class="calendar-day${date.getMonth() === visibleMonth.getMonth() ? '' : ' outside-month'}${key === selectedDate ? ' selected' : ''}${key === today ? ' today' : ''}">
        <button type="button" class="calendar-date" data-select-date="${key}" aria-label="Afficher le ${key}">${date.getDate()}</button>
        <div class="calendar-day-events">
          ${dayEvents.slice(0, 3).map((event) => `<button type="button" class="calendar-event" data-open-event="${esc(event.id)}" title="${esc(event.title)}">
            <span class="calendar-event-time">${event.all_day ? 'Journée' : esc(new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(eventStart(event)))}</span>
            <span class="calendar-event-title">${esc(event.title)}</span>
          </button>`).join('')}
          ${dayEvents.length > 3 ? `<div class="calendar-more">+${dayEvents.length - 3} autre(s)</div>` : ''}
        </div>
      </div>`);
    }

    $('#agendaCalendar').innerHTML = cells.join('');
    $$('[data-select-date]').forEach((button) => button.addEventListener('click', () => {
      selectedDate = button.dataset.selectDate;
      renderCalendar();
      renderSelectedDate();
    }));
    $$('[data-open-event]', $('#agendaCalendar')).forEach((button) => button.addEventListener('click', () => {
      showEvent(events.find((event) => Number(event.id) === Number(button.dataset.openEvent)));
    }));
  }

  function renderSelectedDate() {
    const selected = new Date(`${selectedDate}T00:00:00`);
    $('#selectedDateTitle').textContent = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(selected);
    const daily = eventForDate(selected);
    $('#selectedDateCount').textContent = `${daily.length} événement(s)`;
    $('#selectedDateEvents').innerHTML = daily.length ? daily.map((event) => {
      const response = currentInvitation(event)?.pivot?.response_status;
      const projectLink = event.project
        ? `<a class="small" href="/projects/${encodeURIComponent(event.project.id)}">${esc(event.project.reference || event.project.name)}</a>`
        : '<span class="small text-muted">Événement général</span>';
      return `<div class="list-line align-items-start">
        <div class="text-primary text-center" style="min-width:4.5rem">
          <div class="fw-semibold">${event.all_day ? 'Journée' : esc(new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(eventStart(event)))}</div>
          ${event.ends_at && !event.all_day ? `<div class="small text-muted">${esc(new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit' }).format(eventEnd(event)))}</div>` : ''}
        </div>
        <div class="flex-grow-1 min-w-0">
          <button type="button" class="btn btn-link p-0 text-start fw-semibold" data-open-event="${esc(event.id)}">${esc(event.title)}</button>
          <div class="small text-muted">${esc(TYPE_LABELS[event.type] || event.type)}${event.location ? ` · ${esc(event.location)}` : ''}</div>
          ${projectLink}
          ${response ? `<span class="badge-soft bs-${esc(response)} ms-2">${esc(RESPONSE_LABELS[response] || response)}</span>` : ''}
        </div>
        <div class="d-flex flex-wrap justify-content-end gap-1">
          ${response ? `<button type="button" class="btn btn-sm btn-outline-primary" data-rsvp-event="${esc(event.id)}" aria-label="Répondre à l’invitation"><i class="bi bi-reply"></i></button>` : ''}
          ${event.can_manage ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-event="${esc(event.id)}" aria-label="Modifier ${esc(event.title)}"><i class="bi bi-pencil"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-event="${esc(event.id)}" aria-label="Supprimer ${esc(event.title)}"><i class="bi bi-trash"></i></button>` : ''}
        </div>
      </div>`;
    }).join('') : '<div class="text-center text-muted py-4"><i class="bi bi-calendar2-x fs-3 d-block mb-2"></i>Aucun événement à cette date.</div>';

    $$('[data-open-event]', $('#selectedDateEvents')).forEach((button) => button.addEventListener('click', () => {
      showEvent(events.find((event) => Number(event.id) === Number(button.dataset.openEvent)));
    }));
    $$('[data-rsvp-event]').forEach((button) => button.addEventListener('click', () => {
      const event = events.find((item) => Number(item.id) === Number(button.dataset.rsvpEvent));
      respondToEvent(event);
    }));
    $$('[data-edit-event]').forEach((button) => button.addEventListener('click', () => {
      editEvent(events.find((event) => Number(event.id) === Number(button.dataset.editEvent)));
    }));
    $$('[data-delete-event]').forEach((button) => button.addEventListener('click', () => {
      deleteEvent(events.find((event) => Number(event.id) === Number(button.dataset.deleteEvent)));
    }));
  }

  async function loadEvents() {
    const { start, end } = gridRange();
    $('#agendaCalendar').innerHTML = '<div class="calendar-loading"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement de l’agenda…</div>';
    try {
      events = await api('events', {
        params: {
          from: dateKey(start),
          to: dateKey(end),
          type: $('#eventTypeFilter').value,
          mine: $('#myEventsOnly').checked,
        },
      });
      renderCalendar();
      renderSelectedDate();
    } catch (error) {
      $('#agendaCalendar').innerHTML = '<div class="calendar-loading text-danger">Impossible de charger l’agenda.</div>';
      $('#selectedDateEvents').innerHTML = '<div class="text-center text-danger py-4">Impossible de charger les événements.</div>';
      fail(error);
    }
  }

  async function participantOptions(event) {
    if (event?.project_id) {
      const members = await api(`projects/${event.project_id}/members`);
      return members.map((member) => [member.user_id, fullName(member.user)]);
    }
    if (['admin', 'project_manager'].includes(currentUser.role)) {
      const result = await api('users', { params: { is_active: 1, per_page: 100 } });
      return (result.data || []).map((person) => [person.id, fullName(person)]);
    }
    return null;
  }

  function eventFields(event, participants) {
    const visibility = [
      ['private', 'Privé'],
      ['organization', 'Organisation'],
      ...(event?.project_id ? [['project', 'Projet']] : []),
    ];
    const selectedParticipants = eventParticipants(event || {}).map((person) => person.id);
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: event?.title || '' })}
      ${field({ name: 'type', label: 'Type', type: 'select', value: event?.type || 'meeting', options: EVENT_TYPES, col: 'col-md-6' })}
      ${field({ name: 'visibility', label: 'Visibilité', type: 'select', value: event?.visibility || 'private', options: visibility, col: 'col-md-6' })}
      ${field({ name: 'starts_at', label: 'Début', type: 'datetime-local', required: true, value: event ? toLocalInput(event.starts_at) : `${selectedDate}T09:00`, col: 'col-md-6' })}
      ${field({ name: 'ends_at', label: 'Fin', type: 'datetime-local', value: event?.ends_at ? toLocalInput(event.ends_at) : '', col: 'col-md-6' })}
      ${field({ name: 'all_day', label: 'Toute la journée', type: 'checkbox', value: event?.all_day || false, col: 'col-md-6' })}
      ${field({ name: 'reminder_minutes', label: 'Rappel (minutes avant)', type: 'number', min: 0, max: 10080, value: event?.reminder_minutes ?? '', col: 'col-md-6' })}
      ${field({ name: 'location', label: 'Lieu', value: event?.location || '', col: 'col-md-6' })}
      ${field({ name: 'meeting_url', label: 'Lien de visioconférence', type: 'url', value: event?.meeting_url || '', col: 'col-md-6' })}
      ${participants ? field({ name: 'participants', label: 'Inviter des personnes', type: 'select', multiple: true, size: 5, value: selectedParticipants, options: participants, help: 'Les invités recevront une notification et pourront répondre.' }) : ''}
      ${field({ name: 'description', label: 'Description', type: 'textarea', rows: 3, value: event?.description || '' })}
    </div>`;
  }

  async function createEvent() {
    try {
      const participants = await participantOptions(null);
      formModal({
        title: 'Nouvel événement',
        size: 'modal-lg',
        html: eventFields(null, participants),
        onSubmit: async (data) => {
          data.all_day = Boolean(data.all_day);
          await api('events', { method: 'POST', body: data });
          await loadEvents();
          toast('Événement créé.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function editEvent(event) {
    if (!event?.can_manage) return;
    try {
      const participants = await participantOptions(event);
      formModal({
        title: 'Modifier l’événement',
        size: 'modal-lg',
        html: eventFields(event, participants),
        onSubmit: async (data) => {
          data.all_day = Boolean(data.all_day);
          await api(`events/${event.id}`, { method: 'PUT', body: data });
          await loadEvents();
          toast('Événement mis à jour.');
        },
      });
    } catch (error) {
      fail(error);
    }
  }

  async function deleteEvent(event) {
    if (!event?.can_manage || !await confirmBox(`Supprimer l’événement « ${event.title} » ?`, 'Supprimer')) return;
    try {
      await api(`events/${event.id}`, { method: 'DELETE' });
      toast('Événement supprimé.');
      await loadEvents();
    } catch (error) {
      fail(error);
    }
  }

  async function respondToEvent(event) {
    if (!event || !currentInvitation(event)) return;
    const invitation = currentInvitation(event);
    formModal({
      title: `Répondre à « ${esc(event.title)} »`,
      html: field({ name: 'response_status', label: 'Votre réponse', type: 'select', required: true, value: invitation.pivot.response_status === 'pending' ? '' : invitation.pivot.response_status, blank: 'Choisir une réponse', options: RESPONSE_OPTIONS }),
      onSubmit: async (data) => {
        await api(`events/${event.id}/respond`, { method: 'POST', body: data });
        await loadEvents();
        toast('Votre réponse a été enregistrée.');
      },
    });
  }

  async function showEvent(event) {
    if (!event) return;
    try {
      const details = await api(`events/${event.id}`);
      const response = currentInvitation(details)?.pivot?.response_status;
      const safeMeetingUrl = /^https?:\/\//i.test(String(details.meeting_url || '')) ? String(details.meeting_url) : '';
      const body = `<dl class="info-grid">
        <div><dt>Type</dt><dd>${esc(TYPE_LABELS[details.type] || details.type)}</dd></div>
        <div><dt>Début</dt><dd>${esc(details.all_day ? `${new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long' }).format(eventStart(details))} · Journée entière` : fmtDateTime(details.starts_at))}</dd></div>
        <div><dt>Fin</dt><dd>${esc(details.ends_at ? fmtDateTime(details.ends_at) : '—')}</dd></div>
        <div><dt>Visibilité</dt><dd>${esc(details.visibility === 'organization' ? 'Organisation' : details.visibility === 'project' ? 'Projet' : 'Privé')}</dd></div>
        <div><dt>Lieu</dt><dd>${esc(details.location || '—')}</dd></div>
        <div><dt>Organisateur</dt><dd>${esc(fullName(details.creator))}</dd></div>
        <div><dt>Projet</dt><dd>${details.project ? `<a href="/projects/${encodeURIComponent(details.project.id)}">${esc(details.project.reference || details.project.name)}</a>` : '—'}</dd></div>
        ${response ? `<div><dt>Votre réponse</dt><dd>${esc(RESPONSE_LABELS[response] || response)}</dd></div>` : ''}
      </dl>
      ${details.description ? `<div class="mt-3"><h3 class="h6">Description</h3><div class="text-break">${esc(details.description).replace(/\n/g, '<br>')}</div></div>` : ''}
      ${safeMeetingUrl ? `<a class="btn btn-outline-primary mt-3" href="${esc(safeMeetingUrl)}" target="_blank" rel="noopener noreferrer"><i class="bi bi-camera-video me-1"></i>Rejoindre la visioconférence</a>` : ''}
      ${eventParticipants(details).length ? `<div class="mt-4"><h3 class="h6">Invités</h3><div class="d-flex flex-wrap gap-2">${eventParticipants(details).map((person) => `<span class="badge-soft bs-${esc(person.pivot?.response_status || 'pending')}">${esc(fullName(person))} · ${esc(RESPONSE_LABELS[person.pivot?.response_status] || 'En attente')}</span>`).join('')}</div></div>` : ''}`;
      modal({ title: esc(details.title), body, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function init(user) {
    currentUser = user;
    if (user.role !== 'guest') {
      $('#agendaActions').innerHTML = '<button type="button" class="btn btn-primary" id="createEvent"><i class="bi bi-plus-lg me-1"></i>Nouvel événement</button>';
      $('#createEvent').addEventListener('click', createEvent);
    }
    $('#previousMonth').addEventListener('click', () => {
      visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() - 1, 1);
      selectedDate = dateKey(visibleMonth);
      loadEvents();
    });
    $('#nextMonth').addEventListener('click', () => {
      visibleMonth = new Date(visibleMonth.getFullYear(), visibleMonth.getMonth() + 1, 1);
      selectedDate = dateKey(visibleMonth);
      loadEvents();
    });
    $('#todayMonth').addEventListener('click', () => {
      const today = new Date();
      visibleMonth = new Date(today.getFullYear(), today.getMonth(), 1);
      selectedDate = dateKey(today);
      loadEvents();
    });
    $('#eventTypeFilter').addEventListener('change', loadEvents);
    $('#myEventsOnly').addEventListener('change', loadEvents);
    loadEvents();
  }

  ready(init);
})();
