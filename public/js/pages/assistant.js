(function () {
  'use strict';

  const { $, api, esc, qs, ready, fail } = App;
  const messages = $('#assistantMessages');
  const projectSelect = $('#assistantProject');
  let projectId = '';

  function appendMessage(role, text, failed = false) {
    const item = document.createElement('article');
    item.className = `p-3 rounded ${role === 'assistant' ? 'bg-light' : 'border'}`;
    const label = role === 'assistant' ? 'Yobel' : 'Vous';
    item.innerHTML = `<div class="small fw-semibold mb-1">${label}</div><div class="text-break" style="white-space:pre-wrap">${esc(text)}</div>`;
    if (failed) item.classList.add('border-danger', 'text-danger');
    $('#assistantWelcome')?.remove();
    messages.appendChild(item);
    item.scrollIntoView({ block: 'nearest' });
  }

  function renderSuggestions(suggestions) {
    if (!suggestions?.length) return;
    const section = document.createElement('section');
    section.className = 'border rounded p-3';
    section.innerHTML = `<h2 class="h6">Propositions de Yobel</h2><p class="small text-muted">Une validation enregistre votre avis; elle ne déclenche aucune action dans le projet.</p>
      <div class="d-flex flex-column gap-2">${suggestions.map((suggestion) => {
        const status = { pending: 'À examiner', accepted: 'Validée par vous', rejected: 'Refusée par vous', expired: 'Expirée' }[suggestion.status] || suggestion.status;
        return `<article class="border rounded p-2" data-suggestion="${esc(suggestion.id)}">
          <div class="d-flex justify-content-between gap-2">
            <div class="fw-semibold">${esc(suggestion.title)}</div>
            <span class="badge text-bg-light" data-suggestion-status>${esc(status)}</span>
          </div>
          ${suggestion.content ? `<div class="small text-muted mt-1 text-break">${esc(suggestion.content)}</div>` : ''}
          ${suggestion.status === 'pending' ? `<div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-sm btn-outline-success" data-review-suggestion="accepted">Valider</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-review-suggestion="rejected">Refuser</button>
          </div>` : ''}
        </article>`;
      }).join('')}</div>`;
    messages.appendChild(section);

    section.querySelectorAll('[data-review-suggestion]').forEach((button) => {
      button.addEventListener('click', async () => {
        const card = button.closest('[data-suggestion]');
        const buttons = card.querySelectorAll('[data-review-suggestion]');
        buttons.forEach((item) => { item.disabled = true; });
        try {
          const result = await api(`ai/suggestions/${card.dataset.suggestion}/review`, {
            method: 'POST',
            body: { status: button.dataset.reviewSuggestion },
          });
          card.querySelector('[data-suggestion-status]').textContent = result.status === 'accepted' ? 'Validée par vous' : 'Refusée par vous';
          buttons.forEach((item) => item.remove());
        } catch (error) {
          buttons.forEach((item) => { item.disabled = false; });
          fail(error);
        }
      });
    });
  }

  async function loadHistory() {
    messages.innerHTML = '';
    try {
      const params = { per_page: 50 };
      if (projectId) params.project_id = projectId;
      const result = await api('ai/interactions', { params });
      const history = (result.data || []).reverse();
      if (!history.length) {
        messages.innerHTML = '<div class="text-muted text-center py-5" id="assistantWelcome">Bonjour, je suis Yobel. Posez-moi une question, partagez une idée ou demandez un conseil. Vous pouvez choisir un projet si vous souhaitez que je tienne compte de ses données.</div>';
        return;
      }
      history.forEach((item) => {
        appendMessage('user', item.prompt);
        const oldProvider = { ollama: 'Ollama', anthropic: 'Claude' }[item.provider];
        const response = item.status !== 'completed' && oldProvider
          ? `Cet échange date de l’ancienne intégration ${oldProvider}.`
          : item.response || item.error || 'Cette réponse n’a pas pu être générée.';
        appendMessage('assistant', response, item.status !== 'completed');
        renderSuggestions(item.suggestions);
      });
    } catch (error) {
      $('#assistantError').textContent = error.message || 'Impossible de charger votre historique.';
      $('#assistantError').classList.remove('d-none');
      fail(error);
    }
  }

  async function loadProjects() {
    const result = await api('projects', { params: { per_page: 100, sort: 'name', direction: 'asc' } });
    (result.data || []).forEach((project) => {
      const option = document.createElement('option');
      option.value = project.id;
      option.textContent = `${project.reference} · ${project.name}`;
      projectSelect.appendChild(option);
    });

    const requestedProject = qs('project_id');
    if (requestedProject && (result.data || []).some((project) => String(project.id) === requestedProject)) {
      projectSelect.value = requestedProject;
      projectId = requestedProject;
    }
  }

  async function sendPrompt(event) {
    event.preventDefault();
    const prompt = $('#assistantPrompt').value.trim();
    if (prompt.length < 2) return;

    const button = $('#assistantSend');
    const error = $('#assistantError');
    button.disabled = true;
    $('#assistantPrompt').disabled = true;
    error.classList.add('d-none');
    appendMessage('user', prompt);
    $('#assistantPrompt').value = '';

    const waiting = document.createElement('div');
    waiting.className = 'text-muted small';
    waiting.textContent = 'Yobel réfléchit…';
    waiting.setAttribute('role', 'status');
    messages.appendChild(waiting);

    try {
      const result = await api('ai/ask', {
        method: 'POST',
        body: { prompt, project_id: projectId || null },
      });
      waiting.remove();
      appendMessage('assistant', result.response);
      renderSuggestions(result.suggestions);
    } catch (requestError) {
      waiting.remove();
      const message = requestError.message || 'Impossible de traiter votre demande. Réessayez.';
      appendMessage('assistant', message, true);
      error.textContent = message;
      error.classList.remove('d-none');
    } finally {
      button.disabled = false;
      $('#assistantPrompt').disabled = false;
      $('#assistantPrompt').focus();
    }
  }

  ready(async () => {
    try {
      await loadProjects();
      projectSelect.addEventListener('change', () => {
        projectId = projectSelect.value;
        loadHistory();
      });
      $('#assistantForm').addEventListener('submit', sendPrompt);
      await loadHistory();
    } catch (error) {
      $('#assistantError').textContent = error.message || 'Impossible d’initialiser Yobel.';
      $('#assistantError').classList.remove('d-none');
      fail(error);
    }
  });
})();
