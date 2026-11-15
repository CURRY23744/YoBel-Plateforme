(function () {
  'use strict';

  const { $, fullName, setSession, toast, fail, ready } = App;
  const form = $('#profileForm');
  let previewUrl = '';
  let currentUser;

  function showUser(user) {
    $('#profileName').textContent = fullName(user);
    $('#profileJobTitle').textContent = user.job_title || '';
    $('#profileOrganization').textContent = user.organization?.name || '';
    $('#profileEmail').textContent = user.email || '';
    $('#firstName').value = user.first_name || '';
    $('#lastName').value = user.last_name || '';
    $('#phone').value = user.phone || '';
    $('#jobTitle').value = user.job_title || '';
    $('#locale').value = user.locale || 'fr';
    $('#timezone').value = user.timezone || user.organization?.timezone || 'Europe/Paris';
    $('#bio').value = user.bio || '';
    renderAvatar(user.avatar_path, user.first_name, user.last_name);
  }

  function renderAvatar(path, firstName, lastName) {
    const target = $('#profileAvatar');
    target.replaceChildren();
    if (path) {
      const image = document.createElement('img');
      image.src = `/storage/${path}`;
      image.alt = 'Photo de profil';
      target.append(image);
      return;
    }
    const initials = `${firstName?.[0] || ''}${lastName?.[0] || ''}`.toLocaleUpperCase('fr') || '··';
    target.textContent = initials;
  }

  function init(user) {
    currentUser = user;
    showUser(user);
    $('#avatar').addEventListener('change', () => {
      const file = $('#avatar').files[0];
      if (!file) return;
      if (previewUrl) URL.revokeObjectURL(previewUrl);
      previewUrl = URL.createObjectURL(file);
      const image = document.createElement('img');
      image.src = previewUrl;
      image.alt = 'Aperçu de la photo de profil';
      $('#profileAvatar').replaceChildren(image);
    });

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const button = form.querySelector('[type="submit"]');
      const label = button.textContent;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Enregistrement…';
      const data = new FormData(form);
      if (!$('#avatar').files.length) data.delete('avatar');
      try {
        const updated = await App.api('auth/profile', { method: 'POST', form: data });
        updated.organization = currentUser.organization;
        currentUser = updated;
        setSession(null, updated);
        showUser(updated);
        $('#userName').textContent = fullName(updated);
        const avatar = App.avatar(updated).replace('class="avatar', 'id="userAvatar" class="avatar');
        $('#userAvatar').outerHTML = avatar;
        if (previewUrl) {
          URL.revokeObjectURL(previewUrl);
          previewUrl = '';
        }
        toast('Profil mis à jour.');
      } catch (error) {
        App.showErrors(form, error.errors);
        fail(error);
      } finally {
        button.disabled = false;
        button.textContent = label;
      }
    });
  }

  ready(init);
})();
