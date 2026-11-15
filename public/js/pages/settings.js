(function () {
  'use strict';

  const { $, api, esc, toast, fail, ready, setSession } = App;
  const TYPES = [
    ['announcement', 'Annonces', 'Nouvelles communications publiées par la direction.'],
    ['event.invited', 'Invitations aux événements', 'Invitations et ajouts à un rendez-vous.'],
    ['file.uploaded', 'Fichiers', 'Dépôts de fichiers dans vos projets.'],
    ['message.mention', 'Mentions dans les messages', 'Messages qui vous mentionnent.'],
    ['message.reply', 'Réponses aux discussions', 'Nouveaux messages dans vos discussions.'],
    ['project.assigned', 'Projets qui vous sont attribués', 'Changement de responsabilité d’un projet.'],
    ['project.member_added', 'Ajouts à un projet', 'Ajout à l’équipe d’un projet.'],
    ['project.status_changed', 'Changements de statut des projets', 'Évolutions de statut de vos projets.'],
    ['task.assigned', 'Tâches', 'Tâches qui vous sont assignées.'],
  ];
  let unknownMuted = [];
  let currentUser;

  function renderPreferences(user) {
    const muted = user.notification_preferences?.muted || [];
    unknownMuted = muted.filter((type) => !TYPES.some(([known]) => known === type));
    $('#notificationPreferences').innerHTML = TYPES.map(([type, label, description]) => `
      <div class="d-flex justify-content-between align-items-center gap-3 border-bottom py-3">
        <div><div class="fw-semibold">${esc(label)}</div><div class="small text-muted">${esc(description)}</div></div>
        <div class="form-check form-switch flex-shrink-0">
          <input class="form-check-input" type="checkbox" role="switch" id="mute_${esc(type)}" data-mute-type="${esc(type)}"${muted.includes(type) ? ' checked' : ''}>
          <label class="form-check-label" for="mute_${esc(type)}"><span class="visually-hidden">Mettre en sourdine : ${esc(label)}</span></label>
        </div>
      </div>`).join('');
  }

  function init(user) {
    currentUser = user;
    renderPreferences(user);
    $('#notificationSettingsForm').addEventListener('submit', async (event) => {
      event.preventDefault();
      const button = event.currentTarget.querySelector('[type="submit"]');
      button.disabled = true;
      try {
        const muted = [...unknownMuted, ...Array.from(document.querySelectorAll('[data-mute-type]:checked')).map((input) => input.dataset.muteType)];
        const updated = await api('auth/profile', { method: 'PUT', body: { notification_preferences: { muted } } });
        setSession(null, updated);
        currentUser = updated;
        renderPreferences(updated);
        toast('Préférences enregistrées.');
      } catch (error) {
        fail(error);
      } finally {
        button.disabled = false;
      }
    });
  }

  ready(init);
})();
