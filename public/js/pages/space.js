(function () {
  'use strict';

  const { $, $$, api, esc, ago, richText, field, formModal, pager, confirm: confirmBox, toast, fail, debounce, ready } = App;
  const announcements = $('#announcementList');
  const discussionList = $('#discussionList');
  let currentUser;
  let announcementPage = 1;
  let discussionPage = 1;
  let messagePage = 1;
  let selectedDiscussion = null;
  let currentMessages = [];

  function isStaff() {
    return ['admin', 'project_manager'].includes(currentUser.role);
  }

  function renderAnnouncements(result) {
    const items = result.data || [];
    announcements.innerHTML = items.length ? items.map((item) => `<article class="list-group-item py-3">
      <div class="d-flex justify-content-between gap-2">
        <button type="button" class="btn btn-link p-0 text-start text-decoration-none text-body fw-semibold" data-show-announcement="${esc(item.id)}">${item.is_pinned ? '<i class="bi bi-pin-angle-fill text-primary me-1"></i>' : ''}${esc(item.title)}</button>
        ${isStaff() ? `<span class="d-flex gap-1 flex-shrink-0">
          <button type="button" class="btn btn-sm btn-light-soft" data-edit-announcement="${esc(item.id)}" aria-label="Modifier l’annonce"><i class="bi bi-pencil"></i></button>
          <button type="button" class="btn btn-sm btn-outline-danger" data-delete-announcement="${esc(item.id)}" aria-label="Supprimer l’annonce"><i class="bi bi-trash"></i></button>
        </span>` : ''}
      </div>
      <div class="small text-muted mt-1">${esc(item.creator?.name || 'Direction')}${item.published_at ? ` · ${esc(ago(item.published_at))}` : ' · Sans date de publication'}</div>
      <div class="small text-body mt-2 text-truncate-2">${esc(item.body)}</div>
    </article>`).join('') : '<div class="empty"><i class="bi bi-megaphone"></i>Aucune annonce pour le moment.</div>';
    pager($('#announcementPager'), result, (nextPage) => { announcementPage = nextPage; loadAnnouncements(); });
    $$('[data-show-announcement]').forEach((button) => button.addEventListener('click', () => {
      showAnnouncement(items.find((item) => Number(item.id) === Number(button.dataset.showAnnouncement)));
    }));
    $$('[data-edit-announcement]').forEach((button) => button.addEventListener('click', (event) => {
      event.stopPropagation();
      editAnnouncement(items.find((item) => Number(item.id) === Number(button.dataset.editAnnouncement)));
    }));
    $$('[data-delete-announcement]').forEach((button) => button.addEventListener('click', (event) => {
      event.stopPropagation();
      deleteAnnouncement(items.find((item) => Number(item.id) === Number(button.dataset.deleteAnnouncement)));
    }));
  }

  async function loadAnnouncements() {
    announcements.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des annonces…</div>';
    try {
      const result = await api('announcements', { params: { all: isStaff() && $('#allAnnouncements').checked, page: announcementPage, per_page: 10 } });
      renderAnnouncements(result);
    } catch (error) {
      announcements.innerHTML = '<div class="text-center text-danger py-5">Impossible de charger les annonces.</div>';
      fail(error);
    }
  }

  async function showAnnouncement(item) {
    if (!item) return;
    try {
      const details = await api(`announcements/${item.id}`);
      const content = `<div class="small text-muted mb-3">${esc(details.creator?.name || 'Direction')}${details.published_at ? ` · ${esc(ago(details.published_at))}` : ''}</div>
        <div class="text-break">${esc(details.body).replace(/\n/g, '<br>')}</div>
        ${details.expires_at ? `<div class="small text-muted mt-4">Expire le ${esc(App.fmtDate(details.expires_at))}</div>` : ''}`;
      App.modal({ title: esc(details.title), body: content, size: 'modal-lg' });
    } catch (error) {
      fail(error);
    }
  }

  function announcementFields(item) {
    return `<div class="row g-3">
      ${field({ name: 'title', label: 'Titre', required: true, value: item?.title || '' })}
      ${field({ name: 'body', label: 'Contenu', type: 'textarea', rows: 8, required: true, value: item?.body || '' })}
      ${field({ name: 'is_pinned', label: 'Épingler cette annonce', type: 'checkbox', value: item?.is_pinned || false, col: 'col-md-6' })}
      ${field({ name: 'published_at', label: 'Date de publication (vide = immédiate)', type: 'datetime-local', value: App.toLocalInput(item?.published_at), col: 'col-md-6' })}
      ${field({ name: 'expires_at', label: 'Date d’expiration (facultative)', type: 'datetime-local', value: App.toLocalInput(item?.expires_at), col: 'col-md-6' })}
    </div>`;
  }

  function createAnnouncement() {
    formModal({ title: 'Nouvelle annonce', size: 'modal-lg', html: announcementFields(), onSubmit: async (data) => {
      await api('announcements', { method: 'POST', body: data });
      await loadAnnouncements();
      toast('Annonce publiée.');
    } });
  }

  function editAnnouncement(item) {
    formModal({ title: 'Modifier l’annonce', size: 'modal-lg', html: announcementFields(item), onSubmit: async (data) => {
      await api(`announcements/${item.id}`, { method: 'PUT', body: data });
      await loadAnnouncements();
      toast('Annonce mise à jour.');
    } });
  }

  async function deleteAnnouncement(item) {
    if (!item || !await confirmBox(`Supprimer l’annonce « ${item.title} » ?`, 'Supprimer')) return;
    try {
      await api(`announcements/${item.id}`, { method: 'DELETE' });
      await loadAnnouncements();
      toast('Annonce supprimée.');
    } catch (error) {
      fail(error);
    }
  }

  function renderDiscussions(result) {
    const items = result.data || [];
    discussionList.innerHTML = items.length ? items.map((discussion) => `<button type="button" class="list-group-item list-group-item-action text-start py-3 ${selectedDiscussion?.id === discussion.id ? 'active' : ''}" data-open-discussion="${esc(discussion.id)}">
      <div class="d-flex justify-content-between gap-2">
        <span class="fw-semibold text-truncate">${discussion.is_pinned ? '<i class="bi bi-pin-angle-fill me-1"></i>' : ''}${esc(discussion.title)}</span>
        <span class="small text-nowrap">${discussion.is_locked ? '<i class="bi bi-lock-fill me-1" title="Verrouillée"></i>' : ''}${esc(discussion.messages_count || 0)} <i class="bi bi-chat"></i></span>
      </div>
      <div class="small mt-1 ${selectedDiscussion?.id === discussion.id ? '' : 'text-muted'}">${esc(discussion.creator?.name || 'Compte supprimé')} · ${esc(discussion.last_message_at ? ago(discussion.last_message_at) : ago(discussion.created_at))}</div>
    </button>`).join('') : '<div class="empty"><i class="bi bi-chat-square-text"></i>Aucune discussion générale.</div>';
    pager($('#discussionPager'), result, (nextPage) => { discussionPage = nextPage; loadDiscussions(); });
    $$('[data-open-discussion]').forEach((button) => button.addEventListener('click', () => {
      selectedDiscussion = items.find((discussion) => Number(discussion.id) === Number(button.dataset.openDiscussion));
      messagePage = 1;
      loadDiscussions();
      openDiscussion(selectedDiscussion);
    }));
  }

  async function loadDiscussions() {
    discussionList.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des discussions…</div>';
    try {
      const result = await api('discussions', { params: { q: $('#discussionSearch').value.trim(), page: discussionPage, per_page: 15 } });
      renderDiscussions(result);
    } catch (error) {
      discussionList.innerHTML = '<div class="text-center text-danger py-5">Impossible de charger les discussions.</div>';
      fail(error);
    }
  }

  function renderDiscussionActions() {
    const canManage = isStaff() || Number(selectedDiscussion.created_by) === Number(currentUser.id);
    const actions = [];
    if (canManage) actions.push('<button type="button" class="btn btn-sm btn-light-soft" id="editDiscussion" aria-label="Modifier la discussion"><i class="bi bi-pencil"></i></button>');
    if (canManage) actions.push('<button type="button" class="btn btn-sm btn-outline-danger" id="deleteDiscussion" aria-label="Supprimer la discussion"><i class="bi bi-trash"></i></button>');
    $('#discussionActions').innerHTML = actions.join('');
    $('#editDiscussion')?.addEventListener('click', editDiscussion);
    $('#deleteDiscussion')?.addEventListener('click', deleteDiscussion);

    const canPost = !selectedDiscussion.is_locked || isStaff();
    $('#messageForm').classList.toggle('d-none', !canPost);
    $('#lockedMessage').classList.toggle('d-none', canPost);
    $('#discussionTitle').textContent = selectedDiscussion.title;
  }

  async function openDiscussion(discussion) {
    selectedDiscussion = discussion;
    $('#discussionDetail').classList.remove('d-none');
    renderDiscussionActions();
    await loadMessages();
    $('#discussionDetail').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  async function loadMessages(nextPage = messagePage) {
    if (!selectedDiscussion) return;
    messagePage = nextPage;
    $('#discussionDetail').classList.remove('d-none');
    renderDiscussionActions();
    $('#discussionMessages').innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des messages…</div>';
    try {
      const result = await api(`discussions/${selectedDiscussion.id}/messages`, { params: { page: messagePage, per_page: 30 } });
      currentMessages = result.data || [];
      $('#discussionMessages').innerHTML = currentMessages.length ? currentMessages.map((message) => {
        const own = Number(message.user_id) === Number(currentUser.id);
        const canEdit = own || currentUser.role === 'admin';
        const attachments = message.attachments || [];
        return `<article class="border rounded-3 p-3 mb-2">
          <div class="d-flex justify-content-between gap-2 mb-2">
            <div class="fw-semibold">${esc(message.user?.name || 'Compte supprimé')} <span class="small fw-normal text-muted">${esc(ago(message.created_at))}${message.edited_at ? ' · modifié' : ''}</span></div>
            <div class="d-flex gap-1">
              ${canEdit ? `<button type="button" class="btn btn-sm btn-light-soft" data-edit-message="${esc(message.id)}" aria-label="Modifier le message"><i class="bi bi-pencil"></i></button>` : ''}
              ${own || isStaff() ? `<button type="button" class="btn btn-sm btn-outline-danger" data-delete-message="${esc(message.id)}" aria-label="Supprimer le message"><i class="bi bi-trash"></i></button>` : ''}
            </div>
          </div>
          <div class="text-break">${richText(message.body)}</div>
          ${attachments.length ? `<div class="mt-2 small text-muted">${attachments.map((file) => `<div><i class="bi bi-paperclip me-1"></i>${esc(file.original_name)}</div>`).join('')}</div>` : ''}
        </article>`;
      }).join('') : '<div class="empty"><i class="bi bi-chat-dots"></i>Aucun message dans cette discussion.</div>';
      pager($('#messagePager'), result, loadMessages);
      $$('[data-edit-message]').forEach((button) => button.addEventListener('click', () => editMessage(currentMessages.find((message) => Number(message.id) === Number(button.dataset.editMessage)))));
      $$('[data-delete-message]').forEach((button) => button.addEventListener('click', () => deleteMessage(currentMessages.find((message) => Number(message.id) === Number(button.dataset.deleteMessage)))));
    } catch (error) {
      $('#discussionMessages').innerHTML = '<div class="text-center text-danger py-4">Impossible de charger les messages.</div>';
      fail(error);
    }
  }

  function createDiscussion() {
    formModal({
      title: 'Nouvelle discussion',
      size: 'modal-lg',
      html: `<div class="row g-3">${field({ name: 'title', label: 'Sujet', required: true })}${field({ name: 'body', label: 'Premier message (facultatif)', type: 'textarea', rows: 4 })}${isStaff() ? field({ name: 'is_pinned', label: 'Épingler cette discussion', type: 'checkbox', value: false }) : ''}</div>`,
      onSubmit: async (data) => {
        const discussion = await api('discussions', { method: 'POST', body: data });
        discussionPage = 1;
        await loadDiscussions();
        await openDiscussion(discussion);
        toast('Discussion créée.');
      },
    });
  }

  function editDiscussion() {
    const fields = [field({ name: 'title', label: 'Sujet', required: true, value: selectedDiscussion.title })];
    if (isStaff()) {
      fields.push(field({ name: 'is_pinned', label: 'Épingler la discussion', type: 'checkbox', value: selectedDiscussion.is_pinned }));
      fields.push(field({ name: 'is_locked', label: 'Verrouiller la discussion', type: 'checkbox', value: selectedDiscussion.is_locked }));
    }
    formModal({ title: 'Modifier la discussion', html: fields.join(''), onSubmit: async (data) => {
      selectedDiscussion = await api(`discussions/${selectedDiscussion.id}`, { method: 'PUT', body: data });
      await loadDiscussions();
      renderDiscussionActions();
      toast('Discussion mise à jour.');
    } });
  }

  async function deleteDiscussion() {
    if (!selectedDiscussion || !await confirmBox(`Supprimer la discussion « ${selectedDiscussion.title} » et ses messages ?`, 'Supprimer')) return;
    try {
      await api(`discussions/${selectedDiscussion.id}`, { method: 'DELETE' });
      selectedDiscussion = null;
      $('#discussionDetail').classList.add('d-none');
      await loadDiscussions();
      toast('Discussion supprimée.');
    } catch (error) {
      fail(error);
    }
  }

  async function sendMessage(event) {
    event.preventDefault();
    const form = event.currentTarget;
    if (!form.reportValidity() || !selectedDiscussion) return;
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    try {
      await api(`discussions/${selectedDiscussion.id}/messages`, { method: 'POST', body: { body: $('#messageBody').value } });
      form.reset();
      messagePage = 1;
      await loadMessages();
      await loadDiscussions();
    } catch (error) {
      fail(error);
    } finally {
      button.disabled = false;
    }
  }

  function editMessage(message) {
    if (!message) return;
    formModal({ title: 'Modifier le message', html: field({ name: 'body', label: 'Message', type: 'textarea', rows: 5, required: true, value: message.body }), onSubmit: async (data) => {
      await api(`messages/${message.id}`, { method: 'PUT', body: data });
      await loadMessages();
      toast('Message modifié.');
    } });
  }

  async function deleteMessage(message) {
    if (!message || !await confirmBox('Supprimer ce message ?', 'Supprimer')) return;
    try {
      await api(`messages/${message.id}`, { method: 'DELETE' });
      await loadMessages();
      await loadDiscussions();
      toast('Message supprimé.');
    } catch (error) {
      fail(error);
    }
  }

  function init(user) {
    currentUser = user;
    if (isStaff()) {
      $('#announcementAction').innerHTML = '<button type="button" class="btn btn-sm btn-primary text-nowrap" id="createAnnouncement"><i class="bi bi-plus-lg me-1"></i>Nouvelle</button>';
      $('#createAnnouncement').addEventListener('click', createAnnouncement);
      $('#allAnnouncementsWrap').classList.remove('d-none');
    } else {
      $('#allAnnouncementsWrap').classList.add('d-none');
    }
    $('#createDiscussion').classList.toggle('d-none', user.role === 'guest');
    $('#createDiscussion').addEventListener('click', createDiscussion);
    $('#allAnnouncements').addEventListener('change', () => { announcementPage = 1; loadAnnouncements(); });
    $('#discussionSearch').addEventListener('input', debounce(() => { discussionPage = 1; loadDiscussions(); }, 250));
    $('#backToDiscussions').addEventListener('click', () => $('#discussionDetail').classList.add('d-none'));
    $('#messageForm').addEventListener('submit', sendMessage);

    loadAnnouncements();
    loadDiscussions();
  }

  ready(init);
})();
