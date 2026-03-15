/* === FORM BUILDER — ADMIN APP JS === */
'use strict';

// ── API Client ──────────────────────────────────────────────
const API = {
  base: window.APP_BASE + '/api',

  getToken() { return localStorage.getItem('fb_token'); },
  setToken(t) { localStorage.setItem('fb_token', t); },
  clearToken() { localStorage.removeItem('fb_token'); },

  headers(extra = {}) {
    const h = { 'Content-Type': 'application/json', ...extra };
    const t = this.getToken();
    if (t) h['Authorization'] = 'Bearer ' + t;
    return h;
  },

  async req(method, path, body = null) {
    const opts = { method, headers: this.headers() };
    if (body) opts.body = JSON.stringify(body);
    try {
      const r = await fetch(this.base + path, opts);
      if (r.status === 401) { this.clearToken(); showLogin(); return null; }
      const data = await r.json().catch(() => ({}));
      return { ok: r.ok, status: r.status, data };
    } catch (e) {
      console.error('API error', e);
      return { ok: false, status: 0, data: { error: 'Network error' } };
    }
  },

  get: (path) => API.req('GET', path),
  post: (path, body) => API.req('POST', path, body),
  put: (path, body) => API.req('PUT', path, body),
  delete: (path) => API.req('DELETE', path),

  async download(path, filename) {
    const r = await fetch(this.base + path, { headers: { Authorization: 'Bearer ' + this.getToken() } });
    if (!r.ok) { showToast('Export failed', 'error'); return; }
    const blob = await r.blob();
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a'); a.href = url; a.download = filename;
    document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
  }
};

// ── State ────────────────────────────────────────────────────
const State = {
  admin: null,
  forms: [],
  currentForm: null,
  currentFields: [],
  submissions: [],
  view: 'forms', // forms | builder | submissions | settings
};

// ── Toast ────────────────────────────────────────────────────
function showToast(msg, type = 'info', duration = 3500) {
  let container = document.getElementById('toast-container');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = 'position:fixed;bottom:24px;right:24px;z-index:9999;display:flex;flex-direction:column;gap:10px;';
    document.body.appendChild(container);
  }
  const icons = {
    success: '<svg viewBox="0 0 20 20" fill="currentColor" width="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/></svg>',
    error:   '<svg viewBox="0 0 20 20" fill="currentColor" width="16"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/></svg>',
    info:    '<svg viewBox="0 0 20 20" fill="currentColor" width="16"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"/></svg>',
  };
  const colors = { success: '#dcfce7|#166534', error: '#fee2e2|#991b1b', info: '#dbeafe|#1e40af' };
  const [bg, color] = (colors[type] || colors.info).split('|');
  const toast = document.createElement('div');
  toast.style.cssText = `background:${bg};color:${color};padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:10px;box-shadow:0 4px 12px rgba(0,0,0,.15);min-width:240px;max-width:380px;animation:slideInRight .2s ease;`;
  toast.innerHTML = (icons[type] || '') + `<span style="flex:1">${msg}</span>`;
  container.appendChild(toast);
  setTimeout(() => {
    toast.style.opacity = '0'; toast.style.transition = 'opacity .3s';
    setTimeout(() => toast.remove(), 300);
  }, duration);
}

// ── Modal helpers ────────────────────────────────────────────
function openModal(id) { document.getElementById(id)?.classList.add('open'); }
function closeModal(id) { document.getElementById(id)?.classList.remove('open'); }
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.remove('open');
  if (e.target.dataset.closeModal) closeModal(e.target.dataset.closeModal);
});

// ── Auth ─────────────────────────────────────────────────────
function showLogin() {
  document.getElementById('app-root').innerHTML = '';
  document.getElementById('login-page').classList.remove('hidden');
}
function hideLogin() {
  document.getElementById('login-page').classList.add('hidden');
}

async function doLogin() {
  const username = document.getElementById('login-username').value.trim();
  const password = document.getElementById('login-password').value;
  const btn = document.getElementById('login-btn');
  if (!username || !password) { showToast('Enter credentials', 'error'); return; }
  btn.disabled = true; btn.innerHTML = '<span class="spinner"></span> Signing in…';
  const res = await API.post('/auth/login', { username, password });
  btn.disabled = false; btn.innerHTML = 'Sign In';
  if (res?.ok) {
    API.setToken(res.data.token);
    // localStorage.setItem("token", res.data.token);
    State.admin = res.data.admin;
    hideLogin();
    renderApp();
    loadForms();
  } else {
    showToast(res?.data?.error || 'Login failed', 'error');
    document.getElementById('login-password').value = '';
  }
}

// ── App Shell ────────────────────────────────────────────────
function renderApp() {
  const root = document.getElementById('app-root');
  const a = State.admin;
  const initials = a ? a.username.substring(0,2).toUpperCase() : 'AD';
  root.innerHTML = `
  <nav class="topbar">
    <div class="topbar-brand">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><path d="M17 13v8M13 17h8"/></svg>
      Form<span class="accent">Builder</span>
    </div>
    <div class="topbar-nav">
      <a href="#" onclick="navigateTo('forms');return false" class="active" id="nav-forms">Forms</a>
    </div>
    <div class="topbar-user">
      <span>${a?.username || 'Admin'}</span>
      <div class="avatar">${initials}</div>
      <button class="btn btn-ghost btn-sm" onclick="logout()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
      </button>
    </div>
  </nav>
  <div class="app-layout">
    <aside class="sidebar">
      <div class="sidebar-section">
        <div class="sidebar-label">Workspace</div>
        <button class="sidebar-link active" id="sb-forms" onclick="navigateTo('forms')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
          All Forms
        </button>
      </div>
      <div class="sidebar-section" id="sb-form-section" style="display:none">
        <div class="sidebar-label" id="sb-form-name">Form</div>
        <button class="sidebar-link" id="sb-builder" onclick="navigateTo('builder')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          Form Builder
        </button>
        <button class="sidebar-link" id="sb-submissions" onclick="navigateTo('submissions')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
          Submissions
        </button>
        <button class="sidebar-link" onclick="copyPublicUrl()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
          Copy Public URL
        </button>
      </div>
    </aside>
    <main class="main-content" id="main-view"></main>
  </div>
  ${modalsHTML()}`;
}

function modalsHTML() {
  return `
  <!-- Create Form Modal -->
  <div class="modal-overlay" id="modal-create-form">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title">Create New Form</h3>
        <button class="btn btn-ghost btn-icon" data-close-modal="modal-create-form">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Form Name <span class="required">*</span></label>
          <input type="text" class="form-control" id="new-form-name" placeholder="e.g. Contact Us Form" autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea class="form-control" id="new-form-desc" placeholder="Optional description..." rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" data-close-modal="modal-create-form">Cancel</button>
        <button class="btn btn-accent" onclick="createForm()">Create Form</button>
      </div>
    </div>
  </div>

  <!-- Add/Edit Field Modal -->
  <div class="modal-overlay" id="modal-field">
    <div class="modal modal-lg">
      <div class="modal-header">
        <h3 class="modal-title" id="modal-field-title">Add Field</h3>
        <button class="btn btn-ghost btn-icon" data-close-modal="modal-field">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="field-edit-id">
        <div class="grid-2">
          <div class="form-group">
            <label class="form-label">Field Type <span class="required">*</span></label>
            <select class="form-control" id="field-type" onchange="onFieldTypeChange()">
              <option value="text">Text</option>
              <option value="email">Email</option>
              <option value="number">Number</option>
              <option value="textarea">Textarea</option>
              <option value="dropdown">Dropdown</option>
              <option value="radio">Radio</option>
              <option value="checkbox">Checkbox</option>
              <option value="file">File Upload</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Label <span class="required">*</span></label>
            <input type="text" class="form-control" id="field-label" placeholder="e.g. Full Name">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Placeholder</label>
          <input type="text" class="form-control" id="field-placeholder" placeholder="e.g. Enter your full name">
        </div>
        <div class="form-group">
          <label class="form-label flex gap-sm" style="align-items:center">
            <input type="checkbox" id="field-required" style="accent-color:var(--accent);width:16px;height:16px">
            Required field
          </label>
        </div>
        <div id="field-options-section" style="display:none">
          <label class="form-label">Options <span class="required">*</span></label>
          <div id="field-options-list"></div>
          <button class="btn btn-outline btn-sm mt-sm" type="button" onclick="addOption()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M12 5v14M5 12h14"/></svg>
            Add Option
          </button>
        </div>
        <div id="field-number-section" style="display:none" class="grid-2 mt">
          <div class="form-group">
            <label class="form-label">Min Value</label>
            <input type="number" class="form-control" id="field-min" placeholder="e.g. 0">
          </div>
          <div class="form-group">
            <label class="form-label">Max Value</label>
            <input type="number" class="form-control" id="field-max" placeholder="e.g. 100">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" data-close-modal="modal-field">Cancel</button>
        <button class="btn btn-accent" onclick="saveField()">Save Field</button>
      </div>
    </div>
  </div>

  <!-- Delete confirm modal -->
  <div class="modal-overlay" id="modal-delete">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title">Confirm Delete</h3>
        <button class="btn btn-ghost btn-icon" data-close-modal="modal-delete">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body">
        <p id="modal-delete-msg">Are you sure you want to delete this?</p>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline" data-close-modal="modal-delete">Cancel</button>
        <button class="btn btn-danger" id="modal-delete-confirm">Delete</button>
      </div>
    </div>
  </div>`;
}

// ── Navigation ───────────────────────────────────────────────
function navigateTo(view) {
  State.view = view;
  ['sb-forms','sb-builder','sb-submissions'].forEach(id => {
    document.getElementById(id)?.classList.remove('active');
  });
  if (view === 'forms') document.getElementById('sb-forms')?.classList.add('active');
  else if (view === 'builder') document.getElementById('sb-builder')?.classList.add('active');
  else if (view === 'submissions') document.getElementById('sb-submissions')?.classList.add('active');

  if (view === 'forms') renderFormsView();
  else if (view === 'builder') renderBuilderView();
  else if (view === 'submissions') renderSubmissionsView();
}

function logout() {
  API.clearToken();
  State.admin = null;
  State.forms = [];
  State.currentForm = null;
  showLogin();
}

// ── Forms List View ───────────────────────────────────────────
async function loadForms() {
  const res = await API.get('/forms');
  if (res?.ok) {
    State.forms = res.data;
    if (State.view === 'forms') renderFormsView();
  }
}

function renderFormsView() {
  const view = document.getElementById('main-view');
  const total = State.forms.length;
  const active = State.forms.filter(f => f.is_active).length;
  const submissions = State.forms.reduce((s, f) => s + parseInt(f.submission_count || 0), 0);

  view.innerHTML = `
  <div class="page-header">
    <div class="page-header-inner">
      <div>
        <h1 class="page-title">Forms</h1>
        <p class="page-subtitle">Create and manage your forms</p>
      </div>
      <button class="btn btn-accent" onclick="openModal('modal-create-form')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15"><path d="M12 5v14M5 12h14"/></svg>
        New Form
      </button>
    </div>
  </div>
  <div class="stats-row">
    <div class="stat-card"><div class="stat-label">Total Forms</div><div class="stat-value">${total}</div></div>
    <div class="stat-card"><div class="stat-label">Active</div><div class="stat-value">${active}</div></div>
    <div class="stat-card"><div class="stat-label">Total Submissions</div><div class="stat-value">${submissions}</div></div>
  </div>
  ${total === 0 ? `
  <div class="empty-state">
    <div class="empty-state-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <h3>No forms yet</h3>
    <p>Create your first form to get started collecting responses.</p>
    <button class="btn btn-accent mt" onclick="openModal('modal-create-form')">Create Form</button>
  </div>` : `
  <div class="forms-grid">
    ${State.forms.map(f => `
    <div class="form-card" onclick="selectForm('${f.uuid}')">
      <div class="form-card-accent"></div>
      <div class="form-card-body">
        <div class="form-card-name truncate">${esc(f.name)}</div>
        <div class="form-card-desc">${f.description ? esc(f.description) : '<em style="color:var(--ink-muted)">No description</em>'}</div>
        <div class="form-card-meta">
          <span class="meta-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            ${f.field_count} fields
          </span>
          <span class="meta-chip">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg>
            ${f.submission_count} submissions
          </span>
        </div>
      </div>
      <div class="form-card-footer">
        <span class="status-badge ${f.is_active ? 'active' : 'inactive'}">
          <span class="status-dot"></span>${f.is_active ? 'Active' : 'Inactive'}
        </span>
        <div class="flex gap-sm" onclick="event.stopPropagation()">
          <button class="btn btn-ghost btn-icon btn-sm" data-tooltip="Edit" onclick="selectForm('${f.uuid}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
          </button>
          <button class="btn btn-ghost btn-icon btn-sm" data-tooltip="Delete" onclick="confirmDeleteForm('${f.id}','${f.uuid}')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
          </button>
        </div>
      </div>
    </div>`).join('')}
  </div>`}`;
}

async function createForm() {
  const name = document.getElementById('new-form-name').value.trim();
  const description = document.getElementById('new-form-desc').value.trim();
  if (!name) { showToast('Form name is required', 'error'); return; }
  const res = await API.post('/forms', { name, description });
  if (res?.ok) {
    closeModal('modal-create-form');
    document.getElementById('new-form-name').value = '';
    document.getElementById('new-form-desc').value = '';
    State.currentForm = res.data;
    State.currentFields = res.data.fields || [];
    await loadForms();
    activateFormContext();
    navigateTo('builder');
    showToast('Form created!', 'success');
  } else {
    showToast(res?.data?.error || 'Failed to create form', 'error');
  }
}

async function selectForm(uuid) {
  const res = await API.get('/forms/' + uuid);
  if (res?.ok) {
    State.currentForm = res.data;
    State.currentFields = res.data.fields || [];
    activateFormContext();
    navigateTo('builder');
  }
}

function activateFormContext() {
  const f = State.currentForm;
  if (!f) return;
  document.getElementById('sb-form-section').style.display = '';
  document.getElementById('sb-form-name').textContent = f.name.length > 18 ? f.name.substring(0,18)+'…' : f.name;
}

function confirmDeleteForm(id, uuid) {
  document.getElementById('modal-delete-msg').textContent = 'Are you sure you want to delete this form and all its submissions?';
  document.getElementById('modal-delete-confirm').onclick = async () => {
    const res = await API.delete('/forms/' + uuid);
    if (res?.ok) {
      closeModal('modal-delete');
      if (State.currentForm?.uuid === uuid) {
        State.currentForm = null;
        State.currentFields = [];
        document.getElementById('sb-form-section').style.display = 'none';
      }
      await loadForms();
      navigateTo('forms');
      showToast('Form deleted', 'success');
    }
  };
  openModal('modal-delete');
}

// ── Builder View ──────────────────────────────────────────────
function renderBuilderView() {
  if (!State.currentForm) { navigateTo('forms'); return; }
  const f = State.currentForm;
  const view = document.getElementById('main-view');
  const publicUrl = `${window.APP_BASE}/public/form.php?id=${f.uuid}`;

  view.innerHTML = `
  <div class="page-header">
    <div class="breadcrumb">
      <a href="#" onclick="navigateTo('forms');return false">Forms</a>
      <span class="breadcrumb-sep">›</span>
      <span>${esc(f.name)}</span>
    </div>
    <div class="page-header-inner">
      <div>
        <h1 class="page-title">${esc(f.name)}</h1>
        ${f.description ? `<p class="page-subtitle">${esc(f.description)}</p>` : ''}
      </div>
      <div class="flex gap-sm">
        <button class="btn btn-outline" onclick="toggleFormStatus()">
          ${f.is_active ? '⏸ Deactivate' : '▶ Activate'}
        </button>
        <a href="${publicUrl}" target="_blank" class="btn btn-outline">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
          Preview
        </a>
        <button class="btn btn-accent" onclick="openFieldModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M12 5v14M5 12h14"/></svg>
          Add Field
        </button>
      </div>
    </div>
    <div class="flex gap-sm mt-sm">
      <div class="flex gap-sm" style="background:var(--paper);border:1px solid var(--paper-border);border-radius:6px;padding:8px 12px;flex:1;max-width:500px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" style="color:var(--ink-muted);flex-shrink:0;margin-top:1px"><path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/></svg>
        <span class="text-sm truncate" style="flex:1;color:var(--ink-muted)">${publicUrl}</span>
        <button class="copy-btn text-sm font-bold" onclick="copyPublicUrl()" style="flex-shrink:0">Copy</button>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header flex-between">
      <h3 class="card-title">Form Fields <span class="text-muted text-sm">(${State.currentFields.length})</span></h3>
      <span class="text-xs text-muted">Drag to reorder</span>
    </div>
    ${State.currentFields.length === 0 ? `
    <div class="empty-state" style="padding:40px">
      <div class="empty-state-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      </div>
      <h3>No fields yet</h3>
      <p>Add fields to build your form</p>
      <button class="btn btn-accent mt" onclick="openFieldModal()">Add First Field</button>
    </div>` : `
    <div id="fields-list">
      ${State.currentFields.map((f, i) => renderFieldRow(f, i)).join('')}
    </div>`}
  </div>`;

  initDragSort();
}

function renderFieldRow(f, i) {
  const opts = Array.isArray(f.options) && f.options.length ? f.options.slice(0,3).map(o => esc(o)).join(', ') + (f.options.length > 3 ? '…' : '') : '';
  return `
  <div class="field-row" draggable="true" data-field-id="${f.id}" data-index="${i}">
    <div class="drag-handle">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><path d="M9 6h.01M9 12h.01M9 18h.01M15 6h.01M15 12h.01M15 18h.01"/></svg>
    </div>
    <span class="field-type-badge">${f.field_type}</span>
    <div style="flex:1;min-width:0">
      <div class="field-label-text">${esc(f.label)} ${f.is_required ? '<span class="field-required-mark">*</span>' : ''}</div>
      ${f.placeholder ? `<div class="text-xs text-muted">${esc(f.placeholder)}</div>` : ''}
      ${opts ? `<div class="text-xs text-muted">Options: ${opts}</div>` : ''}
    </div>
    <div class="td-actions">
      <button class="btn btn-ghost btn-icon btn-sm" onclick="openFieldModal(${f.id})" data-tooltip="Edit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      </button>
      <button class="btn btn-ghost btn-icon btn-sm" onclick="confirmDeleteField(${f.id})" data-tooltip="Delete">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
      </button>
    </div>
  </div>`;
}

async function toggleFormStatus() {
  const f = State.currentForm;
  const res = await API.put('/forms/' + f.uuid, { is_active: !f.is_active });
  if (res?.ok) {
    State.currentForm = res.data;
    State.currentFields = res.data.fields;
    renderBuilderView();
    showToast(`Form ${res.data.is_active ? 'activated' : 'deactivated'}`, 'success');
  }
}

function copyPublicUrl() {
  if (!State.currentForm) return;
  const url = `${window.location.origin}${window.APP_BASE}/public/form.php?id=${State.currentForm.uuid}`;
  navigator.clipboard.writeText(url).then(() => showToast('URL copied!', 'success')).catch(() => showToast('Failed to copy URL', 'error'));
}

// ── Field Modal ───────────────────────────────────────────────
function onFieldTypeChange() {
  const type = document.getElementById('field-type').value;
  const hasOpts = ['dropdown','radio','checkbox'].includes(type);
  document.getElementById('field-options-section').style.display = hasOpts ? '' : 'none';
  document.getElementById('field-number-section').style.display = type === 'number' ? '' : 'none';
}

function addOption(value = '') {
  const list = document.getElementById('field-options-list');
  const idx = list.children.length;
  const div = document.createElement('div');
  div.className = 'flex gap-sm mb-sm';
  div.innerHTML = `
    <input type="text" class="form-control" placeholder="Option ${idx + 1}" value="${esc(value)}" style="flex:1">
    <button class="btn btn-ghost btn-icon" type="button" onclick="this.parentElement.remove()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </button>`;
  list.appendChild(div);
}

function openFieldModal(fieldId = null) {
  document.getElementById('modal-field-title').textContent = fieldId ? 'Edit Field' : 'Add Field';
  document.getElementById('field-edit-id').value = fieldId || '';
  document.getElementById('field-options-list').innerHTML = '';

  if (fieldId) {
    const f = State.currentFields.find(x => x.id == fieldId);
    if (!f) return;
    document.getElementById('field-type').value = f.field_type;
    document.getElementById('field-label').value = f.label;
    document.getElementById('field-placeholder').value = f.placeholder || '';
    document.getElementById('field-required').checked = !!f.is_required;
    document.getElementById('field-min').value = f.validation_rules?.min ?? '';
    document.getElementById('field-max').value = f.validation_rules?.max ?? '';
    if (Array.isArray(f.options)) f.options.forEach(o => addOption(o));
  } else {
    document.getElementById('field-type').value = 'text';
    document.getElementById('field-label').value = '';
    document.getElementById('field-placeholder').value = '';
    document.getElementById('field-required').checked = false;
    document.getElementById('field-min').value = '';
    document.getElementById('field-max').value = '';
  }
  onFieldTypeChange();
  openModal('modal-field');
  setTimeout(() => document.getElementById('field-label').focus(), 100);
}

async function saveField() {
  const type = document.getElementById('field-type').value;
  const label = document.getElementById('field-label').value.trim();
  if (!label) { showToast('Label is required', 'error'); return; }

  const options = [...document.querySelectorAll('#field-options-list input')].map(i => i.value.trim()).filter(Boolean);
  const hasOpts = ['dropdown','radio','checkbox'].includes(type);
  if (hasOpts && options.length < 2) { showToast('Add at least 2 options', 'error'); return; }

  const validationRules = {};
  const min = document.getElementById('field-min').value;
  const max = document.getElementById('field-max').value;
  if (min !== '') validationRules.min = parseFloat(min);
  if (max !== '') validationRules.max = parseFloat(max);

  const payload = {
    field_type: type,
    label,
    placeholder: document.getElementById('field-placeholder').value.trim(),
    is_required: document.getElementById('field-required').checked,
    options: hasOpts ? options : [],
    validation_rules: validationRules,
  };

  const editId = document.getElementById('field-edit-id').value;
  const form = State.currentForm;
  let res;
  if (editId) {
    res = await API.put(`/forms/${form.uuid}/fields/${editId}`, payload);
  } else {
    res = await API.post(`/forms/${form.uuid}/fields`, payload);
  }

  if (res?.ok) {
    closeModal('modal-field');
    // Refresh fields
    const formRes = await API.get('/forms/' + form.uuid);
    if (formRes?.ok) {
      State.currentForm = formRes.data;
      State.currentFields = formRes.data.fields;
    }
    renderBuilderView();
    showToast(editId ? 'Field updated' : 'Field added', 'success');
  } else {
    const errs = res?.data?.errors;
    showToast(Array.isArray(errs) ? errs.join(', ') : (res?.data?.error || 'Failed'), 'error');
  }
}

function confirmDeleteField(fieldId) {
  document.getElementById('modal-delete-msg').textContent = 'Delete this field? All submission data for it will be lost.';
  document.getElementById('modal-delete-confirm').onclick = async () => {
    const res = await API.delete(`/forms/${State.currentForm.uuid}/fields/${fieldId}`);
    if (res?.ok) {
      closeModal('modal-delete');
      State.currentFields = State.currentFields.filter(f => f.id != fieldId);
      renderBuilderView();
      showToast('Field deleted', 'success');
    }
  };
  openModal('modal-delete');
}

// ── Drag & Drop Reorder ───────────────────────────────────────
function initDragSort() {
  const list = document.getElementById('fields-list');
  if (!list) return;
  let dragSrc = null;

  list.querySelectorAll('.field-row').forEach(row => {
    row.addEventListener('dragstart', e => {
      dragSrc = row;
      row.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
    });
    row.addEventListener('dragend', () => {
      row.classList.remove('dragging');
      list.querySelectorAll('.field-row').forEach(r => r.classList.remove('drag-over'));
      saveFieldOrder();
    });
    row.addEventListener('dragover', e => {
      e.preventDefault(); e.dataTransfer.dropEffect = 'move';
      if (row !== dragSrc) row.classList.add('drag-over');
    });
    row.addEventListener('dragleave', () => row.classList.remove('drag-over'));
    row.addEventListener('drop', e => {
      e.preventDefault();
      if (dragSrc && dragSrc !== row) {
        const rows = [...list.querySelectorAll('.field-row')];
        const srcIdx = rows.indexOf(dragSrc);
        const tgtIdx = rows.indexOf(row);
        if (srcIdx < tgtIdx) row.after(dragSrc); else row.before(dragSrc);
      }
    });
  });
}

async function saveFieldOrder() {
  const list = document.getElementById('fields-list');
  if (!list) return;
  const order = [...list.querySelectorAll('.field-row')].map(r => parseInt(r.dataset.fieldId));
  await API.put(`/forms/${State.currentForm.uuid}/fields/reorder`, { order });
  // Update local state order
  const fieldMap = Object.fromEntries(State.currentFields.map(f => [f.id, f]));
  State.currentFields = order.map(id => fieldMap[id]).filter(Boolean);
}

// ── Submissions View ──────────────────────────────────────────
async function renderSubmissionsView() {
  if (!State.currentForm) { navigateTo('forms'); return; }
  const view = document.getElementById('main-view');
  view.innerHTML = `
  <div class="page-header">
    <div class="breadcrumb">
      <a href="#" onclick="navigateTo('forms');return false">Forms</a>
      <span class="breadcrumb-sep">›</span>
      <a href="#" onclick="navigateTo('builder');return false">${esc(State.currentForm.name)}</a>
      <span class="breadcrumb-sep">›</span>
      <span>Submissions</span>
    </div>
    <div class="page-header-inner">
      <div>
        <h1 class="page-title">Submissions</h1>
        <p class="page-subtitle">${esc(State.currentForm.name)}</p>
      </div>
      <button class="btn btn-outline" onclick="exportCSV()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Export CSV
      </button>
    </div>
  </div>
  <div class="card" style="position:relative">
    <div id="submissions-content"><div style="text-align:center;padding:40px"><span class="spinner dark"></span></div></div>
  </div>`;

  const res = await API.get(`/forms/${State.currentForm.uuid}/submissions`);
  const cont = document.getElementById('submissions-content');
  if (!res?.ok) { cont.innerHTML = '<div class="alert alert-error">Failed to load submissions</div>'; return; }

  const subs = res.data;
  State.submissions = subs;

  if (!subs.length) {
    cont.innerHTML = `<div class="empty-state" style="padding:40px">
      <div class="empty-state-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg></div>
      <h3>No submissions yet</h3><p>Submissions will appear here once people fill out your form.</p>
    </div>`;
    return;
  }

  const keys = subs.length ? Object.keys(subs[0].values) : [];
  cont.innerHTML = `
  <div class="table-wrapper">
    <table>
      <thead><tr>
        <th>#</th>
        <th>Submitted At</th>
        ${keys.map(k => `<th>${esc(k)}</th>`).join('')}
      </tr></thead>
      <tbody>
        ${subs.map((s,i) => `<tr>
          <td class="text-muted">${s.id}</td>
          <td class="text-sm">${new Date(s.submitted_at).toLocaleString()}</td>
          ${keys.map(k => `<td>${esc(s.values[k] || '—')}</td>`).join('')}
        </tr>`).join('')}
      </tbody>
    </table>
  </div>`;
}

function exportCSV() {
  if (!State.currentForm) return;
  API.download(`/forms/${State.currentForm.uuid}/export`, `submissions_${State.currentForm.uuid}.csv`);
}

// ── Escape HTML ───────────────────────────────────────────────
function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Keyboard shortcuts ────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => m.classList.remove('open'));
  }
  if (e.key === 'Enter' && document.getElementById('login-page') && !document.getElementById('login-page').classList.contains('hidden')) {
    doLogin();
  }
});

// Add slide-in animation
const style = document.createElement('style');
style.textContent = `@keyframes slideInRight{from{transform:translateX(100%);opacity:0}to{transform:translateX(0);opacity:1}}`;
document.head.appendChild(style);

// ── Bootstrap ─────────────────────────────────────────────────
window.addEventListener('DOMContentLoaded', async () => {
  const token = API.getToken();
  if (!token) { showLogin(); return; }
  const res = await API.get('/auth/me');
  if (res?.ok) {
    State.admin = res.data;
    hideLogin();
    renderApp();
    loadForms();
  } else {
    showLogin();
  }
});