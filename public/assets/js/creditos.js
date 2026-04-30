'use strict';

/* ===================================================
   Módulo de Créditos — The Place 818
=================================================== */

const credState = {
  clientes:    [],
  clienteId:   null,
  creditos:    [],
  cuadreId:    null,
  cajaAbierta: false,
};

// ── Utilidades ──────────────────────────────────────
function fmtUsd(n) {
  return '$' + Number(n).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
function fmtBs(n) {
  return 'Bs ' + Number(n).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function esc(str) {
  return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function showError(elId, msg) {
  const el = document.getElementById(elId);
  if (!el) return;
  el.textContent = msg;
  el.classList.remove('hidden');
}
function clearError(elId) {
  const el = document.getElementById(elId);
  if (el) { el.textContent = ''; el.classList.add('hidden'); }
}

// ── Init ────────────────────────────────────────────
async function initCreditos() {
  await Promise.all([cargarCuadre(), cargarClientes()]);
  setupBuscador();
  setupModalCliente();
  setupModalCredito();
  setupModalAbono();
}

async function cargarCuadre() {
  try {
    const res  = await fetch('/api/creditos/cuadre_hoy.php');
    const data = await res.json();
    credState.cuadreId    = data.cuadre_id ?? null;
    credState.cajaAbierta = data.cuadre_id !== null && !data.cerrado;
  } catch {
    credState.cuadreId    = null;
    credState.cajaAbierta = false;
  }
}

async function cargarClientes() {
  try {
    const res  = await fetch('/api/creditos/listar_clientes.php');
    const data = await res.json();
    credState.clientes = data.clientes ?? [];

    // Totales
    const lbl = document.getElementById('cred-total-label');
    if (lbl) {
      if (data.total_usd > 0) {
        lbl.innerHTML = `Saldo total pendiente: <strong>${fmtUsd(data.total_usd)}</strong>`;
      } else {
        lbl.textContent = 'Sin créditos pendientes';
      }
    }

    renderClientes(credState.clientes);
  } catch {
    document.getElementById('cred-lista-clientes').innerHTML =
      '<p class="cred-empty">Error al cargar clientes</p>';
  }
}

function renderClientes(lista) {
  const contenedor = document.getElementById('cred-lista-clientes');
  if (!lista.length) {
    contenedor.innerHTML = '<p class="cred-empty">Sin clientes registrados</p>';
    return;
  }

  contenedor.innerHTML = lista.map(c => {
    const saldoCero = c.saldo_usd <= 0;
    return `
      <div class="cred-client-card ${c.activo ? '' : 'inactivo'} ${credState.clienteId === c.id ? 'active' : ''}"
           data-id="${c.id}" role="button" tabindex="0">
        <p class="cred-client-name">${esc(c.nombre)}${!c.activo ? ' <em>(inactivo)</em>' : ''}</p>
        <p class="cred-client-saldo ${saldoCero ? 'saldo-cero' : ''}">
          ${saldoCero ? 'Sin deuda' : fmtUsd(c.saldo_usd)}
        </p>
        ${c.creditos_pendientes > 0 ? `<p class="cred-client-meta">${c.creditos_pendientes} crédito(s) pendiente(s)</p>` : ''}
      </div>`;
  }).join('');

  contenedor.querySelectorAll('.cred-client-card').forEach(card => {
    card.addEventListener('click', () => abrirCliente(parseInt(card.dataset.id, 10)));
    card.addEventListener('keydown', e => { if (e.key === 'Enter') abrirCliente(parseInt(card.dataset.id, 10)); });
  });
}

// ── Buscador ────────────────────────────────────────
function setupBuscador() {
  const input = document.getElementById('cred-buscador');
  if (!input) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    const filtrados = q
      ? credState.clientes.filter(c => c.nombre.toLowerCase().includes(q) || (c.cedula ?? '').toLowerCase().includes(q))
      : credState.clientes;
    renderClientes(filtrados);
  });
}

// ── Abrir cliente ───────────────────────────────────
async function abrirCliente(id) {
  credState.clienteId = id;

  // Marcar activo en sidebar
  document.querySelectorAll('.cred-client-card').forEach(c => {
    c.classList.toggle('active', parseInt(c.dataset.id, 10) === id);
  });

  const detalle = document.getElementById('cred-detalle');
  detalle.innerHTML = '<p class="cred-empty">Cargando…</p>';

  try {
    const res  = await fetch(`/api/creditos/listar_creditos.php?cliente_id=${id}`);
    const data = await res.json();
    if (data.error) throw new Error(data.error);

    credState.creditos = data.creditos ?? [];
    renderDetalle(data.cliente, data.creditos);
  } catch (e) {
    detalle.innerHTML = `<p class="cred-empty">Error: ${esc(e.message)}</p>`;
  }
}

function renderDetalle(cliente, creditos) {
  const detalle    = document.getElementById('cred-detalle');
  const saldoUsd   = creditos.filter(c => !c.pagado).reduce((s, c) => s + c.pendiente_usd, 0);
  const saldoBs    = creditos.filter(c => !c.pagado).reduce((s, c) => s + c.pendiente_bs, 0);
  const saldoCero  = saldoUsd <= 0;

  const btnCredito = credState.cajaAbierta && cliente.activo
    ? `<button class="btn btn-primary btn-sm" id="cred-btn-nuevo-credito">+ Nuevo crédito</button>`
    : `<span class="cred-client-meta">Abre la caja para registrar créditos</span>`;

  const btnToggle = `<button class="btn btn-outline btn-sm" id="cred-btn-toggle-cliente"
    data-activo="${cliente.activo ? '1' : '0'}">${cliente.activo ? 'Desactivar' : 'Activar'}</button>`;

  const btnEditar = `<button class="btn btn-outline btn-sm" id="cred-btn-editar-cliente">Editar</button>`;

  const creditosHTML = creditos.length
    ? creditos.map(renderCreditoCard).join('')
    : '<p class="cred-empty">Sin créditos registrados</p>';

  detalle.innerHTML = `
    <div class="cred-detalle-header">
      <div class="cred-detalle-info">
        <h4>${esc(cliente.nombre)}</h4>
        ${cliente.telefono ? `<p>Tel: ${esc(cliente.telefono)}</p>` : ''}
        ${cliente.cedula   ? `<p>CI: ${esc(cliente.cedula)}</p>` : ''}
      </div>
      <div class="cred-detalle-saldo">
        <div class="saldo-num ${saldoCero ? 'saldo-cero' : ''}">${saldoCero ? 'Al día' : fmtUsd(saldoUsd)}</div>
        ${!saldoCero ? `<div class="saldo-bs">${fmtBs(saldoBs)}</div>` : ''}
      </div>
    </div>
    <div class="cred-detalle-actions">
      ${btnCredito}
      ${btnEditar}
      ${btnToggle}
    </div>
    <div id="cred-lista-creditos">${creditosHTML}</div>
  `;

  // Listeners del detalle
  document.getElementById('cred-btn-editar-cliente')?.addEventListener('click', () => abrirEditarCliente(cliente));
  document.getElementById('cred-btn-toggle-cliente')?.addEventListener('click', () => toggleCliente(cliente.id));
  document.getElementById('cred-btn-nuevo-credito')?.addEventListener('click', () => abrirNuevoCredito(cliente.id));

  detalle.querySelectorAll('.cred-btn-abono').forEach(btn => {
    btn.addEventListener('click', () => abrirAbono(parseInt(btn.dataset.creditoId, 10)));
  });
  detalle.querySelectorAll('.cred-btn-eliminar-credito').forEach(btn => {
    btn.addEventListener('click', () => eliminarCredito(parseInt(btn.dataset.creditoId, 10)));
  });
}

function renderCreditoCard(cr) {
  const pagado = cr.pagado;
  const abonosHTML = cr.abonos.length
    ? `<div class="cred-abonos-list">
        ${cr.abonos.map(a => `
          <div class="cred-abono-row">
            <strong>+${fmtUsd(a.monto_usd)}</strong> el ${esc(a.fecha_cuadre)}
          </div>`).join('')}
       </div>`
    : '';

  const acciones = !pagado && credState.cajaAbierta
    ? `<div class="cred-credito-actions">
        <button class="btn btn-primary btn-sm cred-btn-abono" data-credito-id="${cr.id}">Registrar abono</button>
        ${cr.abonos.length === 0 ? `<button class="btn btn-outline btn-sm cred-btn-eliminar-credito" data-credito-id="${cr.id}">Eliminar</button>` : ''}
       </div>`
    : '';

  return `
    <div class="cred-credito-card ${pagado ? 'pagado' : 'pendiente'}">
      <div class="cred-credito-top">
        <span class="cred-credito-desc">${esc(cr.descripcion)}</span>
        <span class="${pagado ? 'badge-pagado' : 'badge-pendiente'}">${pagado ? 'Pagado' : 'Pendiente'}</span>
      </div>
      <p class="cred-credito-fecha">${esc(cr.fecha_cuadre)}</p>
      <div class="cred-credito-montos">
        <div class="cred-monto-item total">Total: <span>${fmtUsd(cr.monto_usd)}</span></div>
        ${cr.abonado_usd > 0 ? `<div class="cred-monto-item abonado">Abonado: <span>${fmtUsd(cr.abonado_usd)}</span></div>` : ''}
        ${!pagado ? `<div class="cred-monto-item pendiente">Pendiente: <span>${fmtUsd(cr.pendiente_usd)}</span></div>` : ''}
      </div>
      ${abonosHTML}
      ${acciones}
    </div>`;
}

// ── Modal: Nuevo / Editar cliente ───────────────────
function setupModalCliente() {
  document.getElementById('cred-btn-nuevo-cliente')?.addEventListener('click', () => {
    abrirModalCliente(null);
  });
  document.getElementById('mc-cancelar')?.addEventListener('click', cerrarModalCliente);
  document.getElementById('mc-guardar')?.addEventListener('click', guardarCliente);
}

function abrirModalCliente(cliente) {
  document.getElementById('modal-cliente-title').textContent = cliente ? 'Editar cliente' : 'Nuevo cliente';
  document.getElementById('mc-id').value       = cliente?.id ?? 0;
  document.getElementById('mc-nombre').value   = cliente?.nombre ?? '';
  document.getElementById('mc-telefono').value = cliente?.telefono ?? '';
  document.getElementById('mc-cedula').value   = cliente?.cedula ?? '';
  clearError('mc-error');
  document.getElementById('modal-cliente').classList.remove('hidden');
  document.getElementById('mc-nombre').focus();
}

function cerrarModalCliente() {
  document.getElementById('modal-cliente').classList.add('hidden');
}

function abrirEditarCliente(cliente) {
  abrirModalCliente(cliente);
}

async function guardarCliente() {
  clearError('mc-error');
  const id      = parseInt(document.getElementById('mc-id').value, 10);
  const nombre  = document.getElementById('mc-nombre').value.trim();
  const telefono = document.getElementById('mc-telefono').value.trim();
  const cedula  = document.getElementById('mc-cedula').value.trim();

  if (!nombre) { showError('mc-error', 'El nombre es obligatorio'); return; }

  const btn = document.getElementById('mc-guardar');
  btn.disabled = true;

  try {
    const res  = await fetch('/api/creditos/guardar_cliente.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id, nombre, telefono, cedula }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');

    cerrarModalCliente();
    await cargarClientes();
    if (credState.clienteId) abrirCliente(credState.clienteId);
  } catch (e) {
    showError('mc-error', e.message);
  } finally {
    btn.disabled = false;
  }
}

// ── Toggle cliente ───────────────────────────────────
async function toggleCliente(id) {
  try {
    const res  = await fetch('/api/creditos/toggle_cliente.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error);
    await cargarClientes();
    abrirCliente(id);
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

// ── Modal: Nuevo crédito ────────────────────────────
function setupModalCredito() {
  document.getElementById('mcr-cancelar')?.addEventListener('click', cerrarModalCredito);
  document.getElementById('mcr-guardar')?.addEventListener('click', guardarCredito);
}

function abrirNuevoCredito(clienteId) {
  document.getElementById('mcr-cliente-id').value  = clienteId;
  document.getElementById('mcr-descripcion').value = '';
  document.getElementById('mcr-monto-usd').value   = '';
  document.getElementById('mcr-monto-bs').value    = '';
  clearError('mcr-error');
  document.getElementById('modal-credito').classList.remove('hidden');
  document.getElementById('mcr-descripcion').focus();
}

function cerrarModalCredito() {
  document.getElementById('modal-credito').classList.add('hidden');
}

async function guardarCredito() {
  clearError('mcr-error');
  const clienteId   = parseInt(document.getElementById('mcr-cliente-id').value, 10);
  const descripcion = document.getElementById('mcr-descripcion').value.trim();
  const montoUsd    = parseFloat(document.getElementById('mcr-monto-usd').value) || 0;
  const montoBs     = parseFloat(document.getElementById('mcr-monto-bs').value)  || 0;

  if (!descripcion)  { showError('mcr-error', 'La descripción es obligatoria'); return; }
  if (montoUsd <= 0) { showError('mcr-error', 'El monto en USD debe ser mayor a 0'); return; }

  const btn = document.getElementById('mcr-guardar');
  btn.disabled = true;

  try {
    const res  = await fetch('/api/creditos/guardar_credito.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ cliente_id: clienteId, cuadre_id: credState.cuadreId, descripcion, monto_usd: montoUsd, monto_bs: montoBs }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');

    cerrarModalCredito();
    await cargarClientes();
    abrirCliente(clienteId);
  } catch (e) {
    showError('mcr-error', e.message);
  } finally {
    btn.disabled = false;
  }
}

// ── Modal: Abono ─────────────────────────────────────
function setupModalAbono() {
  document.getElementById('mab-cancelar')?.addEventListener('click', cerrarModalAbono);
  document.getElementById('mab-guardar')?.addEventListener('click', guardarAbono);
}

function abrirAbono(creditoId) {
  const cr = credState.creditos.find(c => c.id === creditoId);
  if (!cr) return;

  document.getElementById('mab-credito-id').value = creditoId;
  document.getElementById('mab-monto-usd').value  = '';
  document.getElementById('mab-monto-bs').value   = '';
  document.getElementById('mab-info').textContent =
    `${cr.descripcion} — Pendiente: ${fmtUsd(cr.pendiente_usd)}`;
  clearError('mab-error');
  document.getElementById('modal-abono').classList.remove('hidden');
  document.getElementById('mab-monto-usd').focus();
}

function cerrarModalAbono() {
  document.getElementById('modal-abono').classList.add('hidden');
}

async function guardarAbono() {
  clearError('mab-error');
  const creditoId = parseInt(document.getElementById('mab-credito-id').value, 10);
  const montoUsd  = parseFloat(document.getElementById('mab-monto-usd').value) || 0;
  const montoBs   = parseFloat(document.getElementById('mab-monto-bs').value)  || 0;

  if (montoUsd <= 0) { showError('mab-error', 'El monto debe ser mayor a 0'); return; }

  const btn = document.getElementById('mab-guardar');
  btn.disabled = true;

  try {
    const res  = await fetch('/api/creditos/guardar_abono.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ credito_id: creditoId, cuadre_id: credState.cuadreId, monto_usd: montoUsd, monto_bs: montoBs }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');

    cerrarModalAbono();
    await cargarClientes();
    abrirCliente(credState.clienteId);
  } catch (e) {
    showError('mab-error', e.message);
  } finally {
    btn.disabled = false;
  }
}

// ── Eliminar crédito ─────────────────────────────────
async function eliminarCredito(id) {
  if (!confirm('¿Eliminar este crédito? Esta acción no se puede deshacer.')) return;

  try {
    const res  = await fetch('/api/creditos/eliminar_credito.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id }),
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error ?? 'Error');

    await cargarClientes();
    abrirCliente(credState.clienteId);
  } catch (e) {
    alert('Error: ' + e.message);
  }
}

// ── Arranque ────────────────────────────────────────
// Se llama desde app.js cuando se activa la vista de créditos
window.initCreditos = initCreditos;
