<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Form — FormBuilder</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<?php
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/');
$formUuid = htmlspecialchars($_GET['id'] ?? '', ENT_QUOTES, 'UTF-8');
?>

<div class="public-form-page">
  <header class="public-form-header">
    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" width="20"><rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><path d="M17 13v8M13 17h8"/></svg>
    <span style="font-family:'Syne',sans-serif;font-weight:800;color:#fff;font-size:1.1rem">Form<span style="color:var(--accent)">Builder</span></span>
  </header>

  <div class="public-form-container">
    <div class="public-form-card" id="form-wrapper">

      <!-- Loading -->
      <div id="form-loading" style="padding:60px;text-align:center">
        <span class="spinner dark"></span>
        <p class="text-muted text-sm mt">Loading form…</p>
      </div>

      <!-- Error -->
      <div id="form-error" class="hidden" style="padding:60px;text-align:center">
        <div class="empty-state-icon" style="margin:0 auto 20px;background:#fee2e2">
          <svg viewBox="0 0 24 24" fill="none" stroke="#991b1b" stroke-width="2" width="28"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        </div>
        <h3 id="form-error-msg">Form not found</h3>
        <p class="text-muted text-sm mt-sm">This form may be inactive or the URL is incorrect.</p>
      </div>

      <!-- Form -->
      <div id="form-container" class="hidden"></div>

      <!-- Success -->
      <div id="form-success" class="public-form-success hidden">
        <div class="success-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M20 6L9 17l-5-5"/></svg>
        </div>
        <h2 style="font-family:'Syne',sans-serif;font-weight:800;margin-bottom:10px">Submitted!</h2>
        <p class="text-muted">Your response has been recorded. Thank you!</p>
        <button class="btn btn-accent mt" onclick="resetForm()">Submit Another Response</button>
      </div>

    </div>
  </div>
</div>

<script>
window.APP_BASE = '<?= $basePath ?>';
const FORM_UUID = '<?= $formUuid ?>';

let formData = null;

async function loadForm() {
  if (!FORM_UUID) { showError('No form ID provided'); return; }
  try {
    const r = await fetch(`${window.APP_BASE}/api/public/${FORM_UUID}`);
    const data = await r.json();
    if (!r.ok) { showError(data.error || 'Form not found'); return; }
    formData = data;
    document.title = data.name + ' — FormBuilder';
    renderForm(data);
  } catch(e) {
    showError('Failed to load form');
  }
}

function renderForm(form) {
  document.getElementById('form-loading').classList.add('hidden');
  const cont = document.getElementById('form-container');
  cont.classList.remove('hidden');

  let fieldsHtml = form.fields.map(field => renderField(field)).join('');

  cont.innerHTML = `
    <div class="public-form-card-header">
      <h1 class="public-form-title">${esc(form.name)}</h1>
      ${form.description ? `<p class="public-form-desc">${esc(form.description)}</p>` : ''}
    </div>
    <form id="public-form" novalidate>
      <div class="public-form-body">
        <div id="form-submission-error" class="hidden"></div>
        ${fieldsHtml}
      </div>
      <div class="public-form-footer">
        <button type="submit" class="btn btn-accent btn-lg" id="submit-btn">
          Submit Form
        </button>
      </div>
    </form>`;

  document.getElementById('public-form').addEventListener('submit', handleSubmit);
}

function renderField(field) {
  const req = field.is_required ? '<span style="color:var(--accent)">*</span>' : '';
  const opts = Array.isArray(field.options) ? field.options : [];
  const rules = field.validation_rules || {};
  const inputName = `field_${field.id}`;

  let inputHtml = '';
  switch(field.field_type) {
    case 'text':
      inputHtml = `<input type="text" class="form-control" name="${inputName}" id="${inputName}" placeholder="${esc(field.placeholder)}" ${field.is_required ? 'required' : ''}>`;
      break;
    case 'email':
      inputHtml = `<input type="email" class="form-control" name="${inputName}" id="${inputName}" placeholder="${esc(field.placeholder)}" ${field.is_required ? 'required' : ''}>`;
      break;
    case 'number':
      inputHtml = `<input type="number" class="form-control" name="${inputName}" id="${inputName}" placeholder="${esc(field.placeholder)}"
        ${rules.min !== undefined ? `min="${rules.min}"` : ''} ${rules.max !== undefined ? `max="${rules.max}"` : ''}
        ${field.is_required ? 'required' : ''}>`;
      break;
    case 'textarea':
      inputHtml = `<textarea class="form-control" name="${inputName}" id="${inputName}" placeholder="${esc(field.placeholder)}" rows="4" ${field.is_required ? 'required' : ''}></textarea>`;
      break;
    case 'dropdown':
      inputHtml = `<select class="form-control" name="${inputName}" id="${inputName}" ${field.is_required ? 'required' : ''}>
        <option value="">— Select an option —</option>
        ${opts.map(o => `<option value="${esc(o)}">${esc(o)}</option>`).join('')}
      </select>`;
      break;
    case 'radio':
      inputHtml = `<div class="radio-group">${opts.map((o,i) => `
        <label class="radio-item">
          <input type="radio" name="${inputName}" value="${esc(o)}" ${i===0 && field.is_required ? 'required' : ''}>
          <span>${esc(o)}</span>
        </label>`).join('')}</div>`;
      break;
    case 'checkbox':
      inputHtml = `<div class="checkbox-group">${opts.map(o => `
        <label class="check-item">
          <input type="checkbox" name="${inputName}[]" value="${esc(o)}">
          <span>${esc(o)}</span>
        </label>`).join('')}</div>`;
      break;
    case 'file':
      inputHtml = `<input type="file" class="form-control" name="${inputName}" id="${inputName}" ${field.is_required ? 'required' : ''} style="padding:8px">`;
      break;
    default:
      inputHtml = `<input type="text" class="form-control" name="${inputName}" id="${inputName}">`;
  }

  return `
    <div class="form-group" id="group_${inputName}">
      <label class="form-label" for="${inputName}">${esc(field.label)} ${req}</label>
      ${inputHtml}
      <div class="form-error hidden" id="err_${inputName}"></div>
    </div>`;
}

async function handleSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('submit-btn');

  // Client-side validation
  let hasErrors = false;
  formData.fields.forEach(field => {
    const name = `field_${field.id}`;
    clearError(name);
    if (field.field_type === 'checkbox') {
      const checked = form.querySelectorAll(`[name="${name}[]"]:checked`);
      if (field.is_required && !checked.length) {
        setError(name, 'Please select at least one option');
        hasErrors = true;
      }
    } else if (field.field_type === 'radio') {
      const checked = form.querySelector(`[name="${name}"]:checked`);
      if (field.is_required && !checked) {
        setError(name, 'Please select an option');
        hasErrors = true;
      }
    } else {
      const el = form.querySelector(`[name="${name}"]`);
      if (!el) return;
      if (field.is_required && !el.value.trim()) {
        setError(name, 'This field is required');
        hasErrors = true;
      } else if (field.field_type === 'email' && el.value && !isValidEmail(el.value)) {
        setError(name, 'Enter a valid email address');
        hasErrors = true;
      } else if (field.field_type === 'number' && el.value) {
        const rules = field.validation_rules || {};
        const v = parseFloat(el.value);
        if (rules.min !== undefined && v < rules.min) { setError(name, `Minimum value is ${rules.min}`); hasErrors = true; }
        if (rules.max !== undefined && v > rules.max) { setError(name, `Maximum value is ${rules.max}`); hasErrors = true; }
      }
    }
  });
  if (hasErrors) return;

  btn.disabled = true;
  btn.innerHTML = '<span class="spinner"></span> Submitting…';

  // Build FormData (supports file uploads)
  const hasFile = formData.fields.some(f => f.field_type === 'file');
  let body, headers = {};

  if (hasFile) {
    const fd = new FormData(form);
    body = fd;
  } else {
    const data = {};
    formData.fields.forEach(field => {
      const name = `field_${field.id}`;
      if (field.field_type === 'checkbox') {
        const checked = [...form.querySelectorAll(`[name="${name}[]"]:checked`)].map(i => i.value);
        data[name] = checked;
      } else {
        const el = form.querySelector(`[name="${name}"]`);
        data[name] = el ? el.value : '';
      }
    });
    body = JSON.stringify(data);
    headers = { 'Content-Type': 'application/json' };
  }

  try {
    const r = await fetch(`${window.APP_BASE}/api/submit/${FORM_UUID}`, { method: 'POST', headers, body });
    const result = await r.json();

    if (r.ok) {
      document.getElementById('form-container').classList.add('hidden');
      document.getElementById('form-success').classList.remove('hidden');
    } else {
      btn.disabled = false;
      btn.innerHTML = 'Submit Form';
      const errDiv = document.getElementById('form-submission-error');
      if (result.errors) {
        const msgs = Object.entries(result.errors).map(([k,v]) => `<strong>${k}:</strong> ${Array.isArray(v)?v.join(', '):v}`).join('<br>');
        errDiv.className = 'alert alert-error mb';
        errDiv.innerHTML = msgs;
        // Also set inline errors
        Object.entries(result.errors).forEach(([label, errs]) => {
          const field = formData.fields.find(f => f.label === label);
          if (field) setError(`field_${field.id}`, Array.isArray(errs) ? errs.join(', ') : errs);
        });
      } else {
        errDiv.className = 'alert alert-error mb';
        errDiv.textContent = result.error || 'Submission failed';
      }
    }
  } catch(e) {
    btn.disabled = false;
    btn.innerHTML = 'Submit Form';
    showGlobalError('Network error. Please try again.');
  }
}

function resetForm() {
  document.getElementById('form-success').classList.add('hidden');
  document.getElementById('form-container').classList.remove('hidden');
  document.getElementById('public-form').reset();
}

function setError(name, msg) {
  const el = document.getElementById('err_' + name);
  const input = document.getElementById(name) || document.querySelector(`[name="${name}"]`) || document.querySelector(`[name="${name}[]"]`);
  if (el) { el.textContent = msg; el.classList.remove('hidden'); }
  if (input) input.classList.add('is-invalid');
}

function clearError(name) {
  const el = document.getElementById('err_' + name);
  const input = document.getElementById(name);
  if (el) { el.textContent = ''; el.classList.add('hidden'); }
  if (input) input.classList.remove('is-invalid');
}

function showError(msg) {
  document.getElementById('form-loading').classList.add('hidden');
  document.getElementById('form-error-msg').textContent = msg;
  document.getElementById('form-error').classList.remove('hidden');
}

function showGlobalError(msg) {
  const errDiv = document.getElementById('form-submission-error');
  errDiv.className = 'alert alert-error mb';
  errDiv.textContent = msg;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function esc(str) {
  if (!str) return '';
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

loadForm();
</script>
</body>
</html>