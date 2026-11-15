(function () {
  'use strict';

  const { $, $$, api, esc, ago, notifTarget, pager, confirm: confirmBox, toast, fail, ready, go } = App;
  const list = $('#notificationItems');
  let page = 1;

  function notificationIcon(type) {
    const icons = {
      message: 'bi-chat-dots',
      task: 'bi-check2-square',
      project: 'bi-kanban',
      event: 'bi-calendar-event',
      file: 'bi-file-earmark',
      announcement: 'bi-megaphone',
    };
    return icons[(type || '').split('.')[0]] || 'bi-bell';
  }

  function renderNotifications(result) {
    const items = result.notifications?.data || [];
    $('#notificationSummary').textContent = `${result.unread_count || 0} notification(s) non lue(s)`;
    $('#markAllRead').disabled = !result.unread_count;
    list.innerHTML = items.length ? items.map((notification) => {
      const data = notification.data || {};
      return `<div class="list-group-item py-3 ${notification.read_at ? '' : 'bg-primary-subtle'}" data-notification-id="${esc(notification.id)}">
        <div class="d-flex align-items-start gap-3">
          <div class="avatar sm bg-white text-primary"><i class="bi ${notificationIcon(data.type || notification.type)}"></i></div>
          <button type="button" class="btn btn-link p-0 text-start flex-grow-1 text-decoration-none text-body" data-open-notification="${esc(notification.id)}">
            <span class="d-block fw-semibold">${esc(data.title || 'Notification')}</span>
            ${data.body ? `<span class="d-block small text-muted text-truncate-2">${esc(data.body)}</span>` : ''}
            <span class="d-block small text-muted mt-1">${esc(ago(notification.created_at))}${notification.read_at ? '' : ' · Non lue'}</span>
          </button>
          <div class="d-flex gap-1">
            ${notification.read_at ? '' : `<button type="button" class="btn btn-sm btn-light-soft" data-mark-notification="${esc(notification.id)}" aria-label="Marquer comme lu"><i class="bi bi-check2"></i></button>`}
            <button type="button" class="btn btn-sm btn-outline-danger" data-delete-notification="${esc(notification.id)}" aria-label="Supprimer la notification"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>`;
    }).join('') : `<div class="empty"><i class="bi bi-bell-slash"></i>${$('#unreadOnly').checked ? 'Aucune notification non lue.' : 'Vous n’avez pas encore de notification.'}</div>`;

    $$('[data-open-notification]').forEach((button) => button.addEventListener('click', () => openNotification(items.find((item) => item.id === button.dataset.openNotification))));
    $$('[data-mark-notification]').forEach((button) => button.addEventListener('click', () => markRead(button.dataset.markNotification)));
    $$('[data-delete-notification]').forEach((button) => button.addEventListener('click', () => deleteNotification(button.dataset.deleteNotification)));
    pager($('#notificationPager'), result.notifications, (nextPage) => { page = nextPage; loadNotifications(); });
  }

  async function loadNotifications() {
    list.innerHTML = '<div class="text-center text-muted py-5"><span class="spinner-border spinner-border-sm me-2" role="status"></span>Chargement des notifications…</div>';
    try {
      const result = await api('notifications', { params: { unread: $('#unreadOnly').checked, page, per_page: 20 } });
      renderNotifications(result);
    } catch (error) {
      list.innerHTML = '<div class="text-center text-danger py-5">Impossible de charger les notifications.</div>';
      fail(error);
    }
  }

  async function markRead(id) {
    try {
      await api(`notifications/${encodeURIComponent(id)}/read`, { method: 'POST' });
      await loadNotifications();
    } catch (error) {
      fail(error);
    }
  }

  async function openNotification(notification) {
    if (!notification) return;
    try {
      if (!notification.read_at) await api(`notifications/${encodeURIComponent(notification.id)}/read`, { method: 'POST' });
      go(notifTarget(notification.data || {}));
    } catch (error) {
      fail(error);
    }
  }

  async function deleteNotification(id) {
    if (!await confirmBox('Supprimer cette notification ?', 'Supprimer')) return;
    try {
      await api(`notifications/${encodeURIComponent(id)}`, { method: 'DELETE' });
      toast('Notification supprimée.');
      await loadNotifications();
    } catch (error) {
      fail(error);
    }
  }

  function init() {
    $('#unreadOnly').addEventListener('change', () => { page = 1; loadNotifications(); });
    $('#markAllRead').addEventListener('click', async () => {
      try {
        await api('notifications/read-all', { method: 'POST' });
        toast('Toutes les notifications sont marquées comme lues.');
        page = 1;
        await loadNotifications();
      } catch (error) {
        fail(error);
      }
    });
    loadNotifications();
  }

  ready(init);
})();
