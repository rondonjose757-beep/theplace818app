'use strict';

/* ===================================================
   Gestión de Usuarios — The Place 818
=================================================== */

let usrList = [];

function esc2(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function showUsrErr(elId, msg) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent = msg;
  el.classList.remove('hidden');
}
function clearUsrErr(elId) {
  const el = document.getElementById(elId);
  if (el) { el.textContent = ''; el.classList.add('hidden'); }
}

// ── Init ────────────────────────────────────────────
async function initUsuarios() {
  await cargarUsuarios();
  setupModalUsuario();
  setupModalPassword();
}

async function cargarUsuarios() {
  const tbody = document.getElementById('usr-tbody');
  if (!tbody) return;

  try {
    const res  = await fetch('/api/usuarios/listar.php');
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    usrList = data.usuarios ?? [];
    renderTabla(usrList);
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="5" class="usr-empty">Error: ${esc2(e.message)}</td></tr>`;
  }
}

function renderTabla(lista) {
  const tbody = document.getElementById('usr-tbody');
  if (!tbody) return;

  if (!lista.length) {
    tbody.innerHTML = '<tr><td colspan="5" class="usr-empty">Sin usuarios registrados</td></tr>';
    return;
  }

  tbody.innerHTML = lista.map(u => {
    const estadoBadge = u.activo
      ? '<span class="badge-estado activo">Activo</span>'
      : '<span class="badge-estado inactivo">Inactivo</span>';

    const acciones = `
      <div class="usr-acciones">
        <button class="btn btn-outline btn-sm" onclick="abrirEditarUsuario(${u.id})">Editar</button>
        <button class="btn btn-outline btn-sm" onclick="abrirCambiarPassword(${u.id}, '${esc2(u.nombre)}')">Contraseña</button>
        <button class="btn btn-outline btn-sm" onclick="toggleEstado(${u.id})">${u.activo ? 'Desactivar' : 'Activar'}</button>
      </div>`;

    return `
      <tr>
        <td class="usr-nombre-cell">${esc2(u.nombre)}</td>
        <td class="usr-email-cell">${esc2(u.email)}</td>
        <td><span class="badge-rol ${esc2(u.rol)}">${esc2(u.rol)}</span></td>
        <td>${estadoBadge}</td>
        <td>${acciones}</td>
      </tr>`;
  }).join('');
}

// ── Modal: Nuevo / Editar usuario ───────────────────
function setupModalUsuario() {
  document.getElementById('usr-btn-nuevo')?.addEventListener('click', () => abrirModalUsuario(null));
  document.getElementById('mu-cancelar')?.addEventListener('click', cerrarModalUsuario);
  document.getElementById('mu-guardar')?.addEventListener('click', guardarUsuario);
}

function abrirModalUsuario(usuario) {
  const esNuevo = !usuario;
  document.getElementById('mu-title').textContent   = esNuevo ? 'Nuevo usuario' : 'Editar usuario';
  document.getElementById('mu-id').value            = usuario?.id ?? 0;
  document.getElementById('mu-nombre').value        = usuario?.nombre ?? '';
  document.getElementById('mu-email').value         = usuario?.email ?? '';
  document.getElementById('mu-rol').value           = usuario?.rol ?? 'cajero';
  document.getElementById('mu-password').value      = '';

  // Solo mostrar campo contraseña al crear
  const pwdGroup = document.getElementById('mu-password-group');
  if (pwdGroup) pwdGroup.style.display = esNuevo ? '' : 'none';

  clearUsrErr('mu-error');
  document.getElementById('modal-usuario').classList.remove('hidden');
  document.getElementById('mu-nombre').focus();
}

function cerrarModalUsuario() {
  document.getElementById('modal-usuario').classList.add('hidden');
}

function abrirEditarUsuario(id) {
  const u = usrList.find(u => u.id === id);
  if (u) abrirModalUsuario(u);
}

async function guardarUsuario() {
  clearUsrErr('mu-error');
  const id       = parseInt(document.getElementById('mu-id').value, 10);
  const nombre   = document.getElementById('mu-nombre').value.trim();
  const email    = document.getElementById('mu-email').value.trim();
  const rol      = document.getElementById('mu-rol').value;
  const password = document.getElementById('mu-password').value;

  if (!nombre) { showUsrErr('mu-error', 'El nombre es obligatorio'); return; }
  if (!email)  { showUsrErr('mu-error', 'El email es obligatorio'); return; }
  if (id === 0 && password.length < 8) {
    showUsrErr('mu-error', 'La contraseña debe tener al menos 8 caracteres');
    return;
  }

  const btn = document.getElementById('mu-guardar');
  btn.disabled = true;

  try {
    const body = { id, nombre, email, rol };
    if (id === 0) body.password = password;

    const res  = await fetch('/api/usuarios/guardar.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');

    cerrarModalUsuario();
    await cargarUsuarios();
  } catch (e) {
    showUsrErr('mu-error', e.message);
  } finally {
    btn.disabled = false;
  }
}

// ── Modal: Cambiar contraseña ────────────────────────
function setupModalPassword() {
  document.getElementById('mp-cancelar')?.addEventListener('click', cerrarModalPassword);
  document.getElementById('mp-guardar')?.addEventListener('click', cambiarPassword);
}

function abrirCambiarPassword(id, nombre) {
  document.getElementById('mp-id').value             = id;
  document.getElementById('mp-nombre-label').textContent = nombre;
  document.getElementById('mp-password').value       = '';
  clearUsrErr('mp-error');
  document.getElementById('modal-password').classList.remove('hidden');
  document.getElementById('mp-password').focus();
}

function cerrarModalPassword() {
  document.getElementById('modal-password').classList.add('hidden');
}

async function cambiarPassword() {
  clearUsrErr('mp-error');
  const id       = parseInt(document.getElementById('mp-id').value, 10);
  const password = document.getElementById('mp-password').value;

  if (password.length < 8) {
    showUsrErr('mp-error', 'La contraseña debe tener al menos 8 caracteres');
    return;
  }

  const btn = document.getElementById('mp-guardar');
  btn.disabled = true;

  try {
    const res  = await fetch('/api/usuarios/cambiar_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, password }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');

    cerrarModalPassword();
  } catch (e) {
    showUsrErr('mp-error', e.message);
  } finally {
    btn.disabled = false;
  }
}

// ── Toggle estado ────────────────────────────────────
async function toggleEstado(id) {
  const u = usrList.find(u => u.id === id);
  const accion = u?.activo ? 'desactivar' : 'activar';
  if (!confirm(`¿Deseas ${accion} a ${u?.nombre ?? 'este usuario'}?`)) return;

  try {
    const res  = await fetch('/api/usuarios/toggle_estado.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');
    await cargarUsuarios();
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

// ── Arranque ────────────────────────────────────────
window.initUsuarios       = initUsuarios;
window.abrirEditarUsuario = abrirEditarUsuario;
window.abrirCambiarPassword = abrirCambiarPassword;
window.toggleEstado       = toggleEstado;
