/* =========================================================
   YOBEL Workspace – noyau de l'interface
   Client API (Sanctum), authentification, helpers UI.
   ========================================================= */
(function () {
  'use strict';

  const TK = 'yb_token';
  const UK = 'yb_user';

  // ------------------------------------------------------------------ libellés
  const L = {
    status: { idea: 'Idée', feasibility: 'Étude de faisabilité', validated: 'Validé', in_progress: 'En cours', on_hold: 'En pause', completed: 'Terminé', abandoned: 'Abandonné', archived: 'Archivé' },
    priority: { low: 'Faible', medium: 'Moyenne', high: 'Haute', critical: 'Critique' },
    task: { todo: 'À faire', in_progress: 'En cours', review: 'En revue', done: 'Terminé', cancelled: 'Annulé' },
    decision: { proposed: 'Proposée', validated: 'Validée', rejected: 'Rejetée', superseded: 'Remplacée' },
    role: { admin: 'Administrateur', project_manager: 'Chef de projet', member: 'Membre', guest: 'Invité' },
    memberRole: { manager: 'Responsable', developer: 'Développeur', designer: 'Designer', tester: 'Testeur', analyst: 'Analyste', client_contact: 'Contact client', other: 'Autre' },
    fileCategory: { specification: 'Cahier des charges', mockup: 'Maquette', contract: 'Contrat', deliverable: 'Livrable', report: 'Rapport', image: 'Image', other: 'Autre' },
    eventType: { meeting: 'Réunion', delivery: 'Livraison', client_meeting: 'Rendez-vous client', reminder: 'Rappel', deadline: 'Échéance', other: 'Autre' },
    milestone: { pending: 'À venir', in_progress: 'En cours', completed: 'Atteint', missed: 'Manqué' },
    response: { pending: 'En attente', accepted: 'Accepté', declined: 'Refusé', tentative: 'Peut-être' },
    visibility: { private: 'Privé', project: 'Membres du projet', organization: 'Toute l\'entreprise' },
    health: { on_track: 'Dans les temps', at_risk: 'À risque', late: 'En retard' },
    rights: { can_view: 'Voir', can_comment: 'Commenter', can_upload: 'Déposer', can_edit: 'Modifier', can_delete: 'Supprimer', can_manage: 'Gérer' },
    preset: { reader: 'Lecteur', contributor: 'Contributeur', editor: 'Éditeur', manager: 'Gestionnaire' },
  };
  const opts = (dict) => Object.entries(dict);

  // ------------------------------------------------------------------ utilitaires
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const qs = (name) => new URLSearchParams(location.search).get(name);
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const debounce = (fn, ms = 350) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  const uid = () => Math.random().toString(36).slice(2, 8);

  // Les horaires sont traités comme des heures « murales » (sans conversion de fuseau)
  const naive = (iso) => (iso ? new Date(String(iso).replace('Z', '').slice(0, 19)) : null);
  const fmtDate = (d) => {
    if (!d) return '—';
    const x = naive(d); return x ? x.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) : '—';
  };
  const fmtDateTime = (d) => {
    if (!d) return '—';
    const x = naive(d); return x ? x.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' }) + ' à ' + x.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }) : '—';
  };
  const toLocalInput = (iso) => (iso ? String(iso).replace('Z', '').slice(0, 16).replace(' ', 'T') : '');
  const ago = (iso) => {
    if (!iso) return '';
    const s = (Date.now() - new Date(iso).getTime()) / 1000;
    if (s < 60) return 'à l\'instant';
    if (s < 3600) return `il y a ${Math.floor(s / 60)} min`;
    if (s < 86400) return `il y a ${Math.floor(s / 3600)} h`;
    if (s < 604800) return `il y a ${Math.floor(s / 86400)} j`;
    return fmtDate(iso);
  };
  const money = (v, cur = 'EUR') => (v === null || v === undefined || v === '' ? '—' : new Intl.NumberFormat('fr-FR', { style: 'currency', currency: cur || 'EUR', maximumFractionDigits: 0 }).format(v));
  const fmtSize = (b) => (b > 1048576 ? (b / 1048576).toFixed(1) + ' Mo' : Math.max(1, Math.round(b / 1024)) + ' Ko');
  const isOverdue = (d, status) => d && !['completed', 'abandoned', 'archived', 'done', 'cancelled'].includes(status) && new Date(d) < new Date(new Date().toDateString());

  const fileIcon = (f) => {
    const ext = (f.extension || '').toLowerCase();
    if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) return 'bi-file-earmark-image';
    if (ext === 'pdf') return 'bi-file-earmark-pdf';
    if (['doc', 'docx', 'txt', 'md'].includes(ext)) return 'bi-file-earmark-text';
    if (['xls', 'xlsx', 'csv'].includes(ext)) return 'bi-file-earmark-spreadsheet';
    if (['ppt', 'pptx'].includes(ext)) return 'bi-file-earmark-slides';
    if (ext === 'zip') return 'bi-file-earmark-zip';
    return 'bi-file-earmark';
  };

  const richText = (t) => esc(t).replace(/@([\p{L}\p{N}_.-]+)/gu, '<span class="mention">@$1</span>').replace(/\n/g, '<br>');

  // ------------------------------------------------------------------ badges / avatar
  const badge = (prefix, dict, v) => (v ? `<span class="badge-soft ${prefix}-${esc(v)}">${esc(dict[v] || v)}</span>` : '');
  const statusBadge = (v) => badge('bs', L.status, v);
  const priorityBadge = (v) => badge('bp', L.priority, v);
  const taskBadge = (v) => badge('bs', L.task, v);
  const decisionBadge = (v) => badge('bs', L.decision, v);
  const rightsBadges = (m) => Object.entries(L.rights).map(([k, lbl]) => `<span class="badge-soft badge-right ${m[k] ? '' : 'off'} me-1 mb-1">${lbl}</span>`).join('');

  const fullName = (u) => (u ? (u.name || `${u.first_name || ''} ${u.last_name || ''}`.trim()) : '—');
  const initials = (u) => (((u?.first_name || '?')[0] || '') + ((u?.last_name || '')[0] || '')).toUpperCase();
  const avatar = (u, size = '') => {
    if (!u) return `<span class="avatar ${size}">?</span>`;
    if (u.avatar_path) return `<span class="avatar ${size}"><img src="/storage/${esc(u.avatar_path)}" alt=""></span>`;
    return `<span class="avatar ${size} tone-${(u.id % 6) + 1}" title="${esc(fullName(u))}">${esc(initials(u))}</span>`;
  };
  const empty = (msg = 'Aucun élément pour le moment.', icon = 'bi-inbox') => `<div class="empty"><i class="bi ${icon}"></i>${esc(msg)}</div>`;
  const spinner = () => '<div class="text-center py-5 text-muted"><div class="spinner-border spinner-border-sm me-2"></div>Chargement…</div>';

  // ------------------------------------------------------------------ session
  const token = () => localStorage.getItem(TK);
  const user = () => { try { return JSON.parse(localStorage.getItem(UK)); } catch { return null; } };
  const setSession = (t, u) => { if (t) localStorage.setItem(TK, t); if (u) localStorage.setItem(UK, JSON.stringify(u)); };
  const clearSession = () => { localStorage.removeItem(TK); localStorage.removeItem(UK); };
  const go = (url) => { location.href = url; };
  const expire = () => { clearSession(); if (!location.pathname.startsWith('/login')) go('/login'); };

  // ------------------------------------------------------------------ toasts
  function toast(msg, type = 'success') {
    const box = document.getElementById('toasts'); if (!box) return;
    const el = document.createElement('div');
    const color = { success: 'text-bg-success', danger: 'text-bg-danger', warning: 'text-bg-warning', info: 'text-bg-primary' }[type] || 'text-bg-primary';
    el.className = `toast align-items-center border-0 ${color}`;
    el.innerHTML = `<div class="d-flex"><div class="toast-body">${esc(msg)}</div><button class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
    box.appendChild(el);
    const t = new bootstrap.Toast(el, { delay: type === 'danger' ? 6000 : 3500 }); t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
  }
  const fail = (e) => {
    if (!e) return;
    let msg = e.message || 'Une erreur est survenue.';
    if (e.errors) { const first = Object.values(e.errors)[0]; if (first && first[0]) msg = first[0]; }
    toast(msg, 'danger');
  };

  // ------------------------------------------------------------------ client API
  async function api(path, o = {}) {
    const { method = 'GET', body = null, params = null, form = null, raw = false } = o;
    let url = '/api/' + path.replace(/^\//, '');
    if (params) {
      const q = new URLSearchParams();
      Object.entries(params).forEach(([k, v]) => { if (v !== null && v !== undefined && v !== '' && v !== false) q.append(k, v === true ? 1 : v); });
      const s = q.toString(); if (s) url += (url.includes('?') ? '&' : '?') + s;
    }
    const headers = { Accept: 'application/json' };
    if (token()) headers.Authorization = 'Bearer ' + token();
    let payload;
    if (form) payload = form;
    else if (body !== null) { headers['Content-Type'] = 'application/json'; payload = JSON.stringify(body); }

    let res;
    try { res = await fetch(url, { method, headers, body: payload }); }
    catch (e) { toast('Connexion au serveur impossible.', 'danger'); throw e; }
    if (raw) return res;

    const text = await res.text();
    let data = null;
    if (text) { try { data = JSON.parse(text); } catch { data = text; } }

    if (!res.ok) {
      if (res.status === 401 && !document.body.dataset.guest) { expire(); return new Promise(() => {}); }
      if (res.status === 403 && data && data.code === 'password_change_required' && !location.pathname.startsWith('/change-password')) {
        go('/change-password'); return new Promise(() => {});
      }
      const err = new Error((data && data.message) || `Erreur ${res.status}`);
      err.status = res.status; err.errors = data && data.errors ? data.errors : null; err.data = data;
      throw err;
    }
    return data;
  }

  async function download(path, filename) {
    const res = await api(path, { raw: true });
    if (!res.ok) { toast('Téléchargement impossible.', 'danger'); return; }
    const blob = await res.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = filename || 'fichier'; document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 2000);
  }

  async function preview(path, title) {
    const res = await api(path, { raw: true });
    if (!res.ok) { toast(res.status === 415 ? 'Aperçu indisponible pour ce type de fichier.' : 'Aperçu impossible.', 'warning'); return; }
    const blob = await res.blob(); const url = URL.createObjectURL(blob);
    const isImg = blob.type.startsWith('image/');
    const h = modal({ title: esc(title || 'Aperçu'), size: 'modal-xl',
      body: isImg ? `<img src="${url}" class="img-fluid d-block mx-auto" alt="">` : `<iframe src="${url}" style="width:100%;height:75vh;border:0"></iframe>` });
    h.el.addEventListener('hidden.bs.modal', () => URL.revokeObjectURL(url));
  }

  // ------------------------------------------------------------------ modales
  function modal({ title = '', body = '', footer = '', size = '' }) {
    const el = document.createElement('div');
    el.className = 'modal fade'; el.tabIndex = -1;
    el.innerHTML = `<div class="modal-dialog modal-dialog-scrollable ${size}"><div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">${title}</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button></div>
      <div class="modal-body">${body}</div>${footer ? `<div class="modal-footer">${footer}</div>` : ''}</div></div>`;
    document.body.appendChild(el);
    const m = new bootstrap.Modal(el);
    el.addEventListener('hidden.bs.modal', () => { m.dispose(); el.remove(); });
    m.show();
    return { el, m, close: () => m.hide() };
  }

  /** Modale contenant un formulaire. onSubmit(data, form, handle) ; retourner false pour garder la modale ouverte. */
  function formModal({ title, html, submit = 'Enregistrer', size = '', danger = false, onSubmit, onOpen }) {
    const h = modal({
      title, size,
      body: `<form novalidate>${html}<div class="d-flex justify-content-end gap-2 mt-4">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
        <button type="submit" class="btn ${danger ? 'btn-danger' : 'btn-primary'}">${esc(submit)}</button></div></form>`,
    });
    const form = $('form', h.el);
    onFormSubmit(form, async (data) => { const r = await onSubmit(data, form, h); if (r !== false) h.close(); });
    if (onOpen) onOpen(form, h);
    return h;
  }

  function confirmBox(msg, okLabel = 'Confirmer') {
    return new Promise((resolve) => {
      const el = document.getElementById('confirmModal'); const m = bootstrap.Modal.getOrCreateInstance(el);
      $('#confirmMsg', el).textContent = msg;
      const ok = $('#confirmOk', el); ok.textContent = okLabel;
      let answered = false;
      const done = (v) => { if (answered) return; answered = true; ok.removeEventListener('click', yes); el.removeEventListener('hidden.bs.modal', no); resolve(v); };
      const yes = () => { done(true); m.hide(); };
      const no = () => done(false);
      ok.addEventListener('click', yes); el.addEventListener('hidden.bs.modal', no); m.show();
    });
  }

  // ------------------------------------------------------------------ formulaires
  function field(f) {
    const id = `f_${f.name}_${uid()}`;
    const col = f.col || 'col-12';
    const req = f.required ? ' required' : '';
    const dis = f.disabled ? ' disabled' : '';
    const lab = f.label ? `<label class="form-label" for="${id}">${esc(f.label)}${f.required ? ' <span class="text-danger">*</span>' : ''}</label>` : '';
    const help = f.help ? `<div class="form-text">${f.help}</div>` : '';
    if (f.type === 'checkbox') {
      return `<div class="${col}"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="${id}" name="${f.name}" ${f.value ? 'checked' : ''}${dis}>
        <label class="form-check-label" for="${id}">${esc(f.label)}</label></div>${help}</div>`;
    }
    let input;
    if (f.type === 'textarea') {
      input = `<textarea class="form-control" id="${id}" name="${f.name}" rows="${f.rows || 3}" placeholder="${esc(f.placeholder || '')}"${req}${dis}>${esc(f.value ?? '')}</textarea>`;
    } else if (f.type === 'select') {
      const sel = (v) => (f.multiple ? (f.value || []).map(String).includes(String(v)) : String(f.value ?? '') === String(v));
      const options = (f.options || []).map((o) => { const [v, l] = Array.isArray(o) ? o : [o.value, o.label]; return `<option value="${esc(v)}"${sel(v) ? ' selected' : ''}>${esc(l)}</option>`; }).join('');
      input = `<select class="form-select" id="${id}" name="${f.name}"${f.multiple ? ` multiple size="${f.size || 4}"` : ''}${req}${dis}>${f.blank !== undefined ? `<option value="">${esc(f.blank)}</option>` : ''}${options}</select>`;
    } else if (f.type === 'file') {
      input = `<input class="form-control" type="file" id="${id}" name="${f.name}"${f.multiple ? ' multiple' : ''}${f.accept ? ` accept="${f.accept}"` : ''}${req}>`;
    } else {
      input = `<input class="form-control" type="${f.type || 'text'}" id="${id}" name="${f.name}" value="${esc(f.value ?? '')}" placeholder="${esc(f.placeholder || '')}"${f.min !== undefined ? ` min="${f.min}"` : ''}${f.max !== undefined ? ` max="${f.max}"` : ''}${f.step ? ` step="${f.step}"` : ''}${req}${dis}>`;
    }
    return `<div class="${col}">${lab}${input}${help}</div>`;
  }
  const fields = (list) => `<div class="row g-3">${list.filter(Boolean).map(field).join('')}</div>`;

  function formData(form) {
    const out = {};
    $$('[name]', form).forEach((el) => {
      if (el.disabled || el.type === 'file') return;
      const n = el.name;
      if (el.type === 'checkbox') out[n] = el.checked;
      else if (el.tagName === 'SELECT' && el.multiple) out[n] = Array.from(el.selectedOptions).map((o) => o.value);
      else if (el.type === 'radio') { if (el.checked) out[n] = el.value; }
      else out[n] = el.value === '' ? null : el.value;
    });
    return out;
  }
  function clearErrors(form) {
    $$('.is-invalid', form).forEach((e) => e.classList.remove('is-invalid'));
    $$('.invalid-feedback.js', form).forEach((e) => e.remove());
  }
  function showErrors(form, errors) {
    if (!errors) return;
    Object.entries(errors).forEach(([key, msgs]) => {
      const el = form.querySelector(`[name="${key}"]`) || form.querySelector(`[name="${key.split('.')[0]}"]`);
      if (!el) return;
      el.classList.add('is-invalid');
      const fb = document.createElement('div'); fb.className = 'invalid-feedback js d-block'; fb.textContent = msgs[0];
      el.insertAdjacentElement('afterend', fb);
    });
  }
  function onFormSubmit(form, handler) {
    form.addEventListener('submit', async (ev) => {
      ev.preventDefault();
      clearErrors(form);
      if (!form.checkValidity()) { form.reportValidity(); return; }
      const btn = form.querySelector('[type="submit"]'); const label = btn ? btn.innerHTML : '';
      if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>'; }
      try { await handler(formData(form), form); }
      catch (e) { showErrors(form, e.errors); fail(e); }
      finally { if (btn) { btn.disabled = false; btn.innerHTML = label; } }
    });
  }

  // ------------------------------------------------------------------ pagination
  function pager(el, meta, goTo) {
    if (!el) return;
    if (!meta || meta.last_page <= 1) { el.innerHTML = meta && meta.total ? `<div class="small text-muted">${meta.total} résultat(s)</div>` : ''; return; }
    const cur = meta.current_page; const last = meta.last_page;
    const pages = []; for (let p = Math.max(1, cur - 2); p <= Math.min(last, cur + 2); p++) pages.push(p);
    el.innerHTML = `<div class="d-flex justify-content-between align-items-center flex-wrap gap-2"><span class="small text-muted">${meta.total} résultat(s)</span>
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item ${cur === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-p="${cur - 1}">‹</a></li>
        ${pages.map((p) => `<li class="page-item ${p === cur ? 'active' : ''}"><a class="page-link" href="#" data-p="${p}">${p}</a></li>`).join('')}
        <li class="page-item ${cur === last ? 'disabled' : ''}"><a class="page-link" href="#" data-p="${cur + 1}">›</a></li></ul></div>`;
    $$('[data-p]', el).forEach((a) => a.addEventListener('click', (e) => { e.preventDefault(); const p = +a.dataset.p; if (p >= 1 && p <= last && p !== cur) goTo(p); }));
  }

  // ------------------------------------------------------------------ notifications
  const NOTIF_ICON = { message: 'bi-chat-dots', task: 'bi-check2-square', project: 'bi-kanban', event: 'bi-calendar-event', file: 'bi-file-earmark', announcement: 'bi-megaphone' };
  const notifIcon = (type) => NOTIF_ICON[(type || '').split('.')[0]] || 'bi-bell';
  const notifTarget = (d) => (d.project_id ? `/projects/${d.project_id}` : d.announcement_id ? '/space' : d.event_id ? '/agenda' : d.task_id ? '/tasks' : '/notifications');
  const notifHtml = (n) => {
    const d = n.data || {};
    return `<div class="notif-item ${n.read_at ? '' : 'unread'}" data-id="${n.id}" data-url="${notifTarget(d)}">
      ${n.read_at ? '<span style="width:.55rem"></span>' : '<span class="notif-dot"></span>'}
      <div class="flex-grow-1"><div class="fw-semibold small"><i class="bi ${notifIcon(d.type)} me-1 text-muted"></i>${esc(d.title || 'Notification')}</div>
      ${d.body ? `<div class="small text-muted text-truncate-2">${esc(d.body)}</div>` : ''}<div class="text-muted" style="font-size:.7rem">${ago(n.created_at)}</div></div></div>`;
  };
  async function loadNotifications() {
    try {
      const r = await api('notifications', { params: { per_page: 8 } });
      const badgeEl = document.getElementById('notifBadge');
      if (badgeEl) { badgeEl.textContent = r.unread_count > 99 ? '99+' : r.unread_count; badgeEl.classList.toggle('d-none', !r.unread_count); }
      const list = document.getElementById('notifList');
      if (list) {
        list.innerHTML = r.notifications.data.length ? r.notifications.data.map(notifHtml).join('') : empty('Aucune notification', 'bi-bell-slash');
        $$('.notif-item', list).forEach((el) => el.addEventListener('click', async () => {
          try { await api(`notifications/${el.dataset.id}/read`, { method: 'POST' }); } catch (e) { /* ignore */ }
          go(el.dataset.url);
        }));
      }
    } catch (e) { /* silencieux */ }
  }

  // ------------------------------------------------------------------ initialisation
  function applyRoles(u) {
    $$('[data-roles]').forEach((el) => { if (!el.dataset.roles.split(',').includes(u.role)) el.remove(); });
  }
  function canAccessCurrentPage(u) {
    const roles = document.body.dataset.pageRoles;
    if (!roles) return true;
    return roles.split(',').map((role) => role.trim()).includes(u.role);
  }
  function fillUser(u) {
    const av = document.getElementById('userAvatar');
    if (av) { av.outerHTML = avatar(u).replace('class="avatar', 'id="userAvatar" class="avatar'); }
    const n = document.getElementById('userName'); if (n) n.textContent = fullName(u);
    const r = document.getElementById('userRole'); if (r) r.textContent = L.role[u.role] || u.role;
  }

  let readyCbs = [];
  async function init() {
    if (document.body.dataset.guest) return;
    if (!token()) { go('/login'); return; }
    let u;
    try { u = await api('auth/me'); setSession(null, u); } catch (e) { expire(); return; }
    if (u.must_change_password && !location.pathname.startsWith('/change-password')) { go('/change-password'); return; }
    if (!canAccessCurrentPage(u)) { go('/dashboard?access=denied'); return; }

    applyRoles(u); fillUser(u);
    if (qs('access') === 'denied') {
      toast('Cette page n’est pas accessible avec votre rôle.', 'warning');
      history.replaceState(null, '', location.pathname);
    }
    const nav = document.body.dataset.nav;
    $$('.sidebar .nav-link').forEach((a) => a.classList.toggle('active', a.dataset.nav === nav));
    document.getElementById('logoutBtn')?.addEventListener('click', logout);
    document.getElementById('notifReadAll')?.addEventListener('click', async () => { await api('notifications/read-all', { method: 'POST' }); loadNotifications(); });
    loadNotifications(); setInterval(loadNotifications, 60000);

    const cbs = readyCbs; readyCbs = null;
    for (const cb of cbs) { try { await cb(u); } catch (e) { fail(e); } }
  }
  async function logout() {
    try { await api('auth/logout', { method: 'POST' }); } catch (e) { /* ignore */ }
    clearSession(); go('/login');
  }
  function ready(cb) { if (readyCbs === null) cb(user()); else readyCbs.push(cb); }

  // cache simple des listes de référence
  const cache = {};
  const cached = (key, loader) => (cache[key] ||= loader());

  const activitySubjects = {
    ActivityLog: 'Activité',
    Decision: 'Décision',
    Event: 'Événement',
    MeetingMinute: 'Compte rendu',
    Milestone: 'Jalon',
    Project: 'Projet',
    ProjectMember: 'Membre du projet',
    ProjectType: 'Type de projet',
    Task: 'Tâche',
    User: 'Utilisateur',
  };
  const activitySubject = (log) => {
    const type = log?.subject_type ? log.subject_type.split('\\').pop() : '';
    const label = activitySubjects[type] || type.replace(/([a-z])([A-Z])/g, '$1 $2');
    return label || 'Élément';
  };
  const activityAction = (action, past = false) => ({
    created: past ? 'a créé' : 'Création',
    updated: past ? 'a modifié' : 'Modification',
    deleted: past ? 'a supprimé' : 'Suppression',
  }[action] || action || 'Activité');

  window.App = {
    L, opts, esc, qs, $, $$, debounce, uid, fmtDate, fmtDateTime, toLocalInput, naive, ago, money, fmtSize, isOverdue, fileIcon, richText,
    activitySubject, activityAction,
    badge, statusBadge, priorityBadge, taskBadge, decisionBadge, rightsBadges, fullName, avatar, empty, spinner,
    token, user, setSession, clearSession, go, toast, fail, api, download, preview, modal, formModal, confirm: confirmBox,
    field, fields, formData, showErrors, clearErrors, onFormSubmit, pager, loadNotifications, notifHtml, notifTarget, ready, logout, cached,
    types: () => cached('types', () => api('project-types')),
  };

  document.addEventListener('DOMContentLoaded', init);
})();
