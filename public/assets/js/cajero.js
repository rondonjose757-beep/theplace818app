'use strict';

/* =====================================================
   The Place 818 — Cajero JS
   Depende de: cajero.php (HTML) + cajero.css
===================================================== */

// ─── Configuración ────────────────────────────────────────────────────────

const METODOS = [
    { key: 'tarjeta',          label: 'Tarjeta Bancaria',  icon: '💳', moneda: 'bs'  },
    { key: 'pago_movil',       label: 'Pago Móvil',        icon: '📱', moneda: 'bs'  },
    { key: 'dolares_efectivo', label: 'Dólares Efectivo',  icon: '💵', moneda: 'usd' },
    { key: 'zelle',            label: 'Zelle',             icon: '🏦', moneda: 'usd' },
    { key: 'credito',          label: 'Crédito',           icon: '📋', moneda: 'usd' },
    { key: 'efectivo_bs',      label: 'Efectivo Bs',       icon: '💰', moneda: 'bs'  },
];

const CATEGORIAS = {
    materia_prima: 'Materia prima',
    operativos:    'Operativos',
    nomina:        'Nómina',
    otros:         'Otros',
};

// ─── Estado ───────────────────────────────────────────────────────────────

const state = {
    tasa:     0,
    tasaId:   null,
    cuadreId: null,
    cerrado:  false,
    pagos:    {},   // { metodo: { id, monto_bs, monto_usd } }
    gastos:   [],   // [{ id, categoria, descripcion, monto_bs, monto_usd }]
    consumo:  [],   // [{ id, responsable, descripcion, cantidad, precio_usd, precio_bs }]
};

// ─── Utilidades ───────────────────────────────────────────────────────────

const fmtUSD = n => '$' + (+n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtBs  = n => 'Bs ' + (+n).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function esc(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

async function api(url, body = null) {
    const opts = { headers: { 'Content-Type': 'application/json' } };
    if (body !== null) {
        opts.method = 'POST';
        opts.body   = JSON.stringify(body);
    }
    const res = await fetch(url, opts);
    if (res.status === 401) { window.location.href = '/'; return null; }
    return res.json();
}

let _toastTimer;
function toast(msg, type = 'ok') {
    const el = document.getElementById('toast');
    el.textContent  = msg;
    el.className    = `toast toast-${type}`;
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(() => { el.className = 'toast hidden'; }, 3200);
}

function setBusy(btn, busy, label) {
    btn.disabled    = busy;
    btn.textContent = busy ? '…' : label;
}

// ─── Tasa BCV ─────────────────────────────────────────────────────────────

function renderTasaActiva() {
    const display = document.getElementById('tasa-activa');
    const valEl   = document.getElementById('tasa-val');
    const inputEl = document.getElementById('input-tasa');
    display.classList.remove('hidden');
    valEl.textContent  = `${state.tasa.toFixed(2)} Bs/$`;
    inputEl.value      = state.tasa;
    inputEl.disabled   = state.cerrado;
}

async function guardarTasa() {
    const input = document.getElementById('input-tasa');
    const btn   = document.getElementById('btn-tasa');
    const tasa  = parseFloat(input.value);

    if (!tasa || tasa <= 0) { toast('Ingresa una tasa válida mayor a 0', 'err'); return; }

    setBusy(btn, true, 'Guardar');
    const data = await api('https://theplace818app.gastroredes.com/api/cajero/guardar_tasa.php', { tasa });
    setBusy(btn, false, 'Guardar');

    if (!data) return;
    if (data.success) {
        state.tasa    = data.tasa;
        state.tasaId  = data.tasa_id;
        const esNuevoCuadre = !state.cuadreId;
        state.cuadreId = data.cuadre_id;
        renderTasaActiva();
        document.getElementById('form-body').classList.remove('hidden');
        // Re-calcular conversiones si la tasa cambia
        METODOS.forEach(m => updateConversion(m.key));
        updateResumen();
        toast('Tasa guardada');
        if (esNuevoCuadre) {
            // Hacer scroll suave al primer campo
            document.getElementById('sec-pagos').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    } else {
        toast(data.error ?? 'Error al guardar tasa', 'err');
    }
}

// ─── Métodos de pago ─────────────────────────────────────────────────────

function buildPagosHTML() {
    const container = document.getElementById('pagos-container');
    container.innerHTML = METODOS.map(m => {
        const esBs     = m.moneda === 'bs';
        const prefijo  = esBs ? 'Bs' : '$';
        const convPre  = esBs ? '→ $' : '→ Bs';
        const tagClass = esBs ? 'tag-bs' : 'tag-usd';
        const pago     = state.pagos[m.key];
        const valInput = pago ? (esBs ? pago.monto_bs : pago.monto_usd) : 0;

        return `
        <div class="metodo-row">
          <div class="metodo-header">
            <span class="metodo-icon">${m.icon}</span>
            <span class="metodo-label">${esc(m.label)}</span>
            <span class="metodo-tag ${tagClass}">${prefijo} ${convPre}</span>
          </div>
          <div class="metodo-body">
            <span class="input-currency">${prefijo}</span>
            <input
              type="number" min="0" step="0.01" placeholder="0.00"
              class="metodo-input"
              id="pago-${m.key}"
              data-metodo="${m.key}"
              data-moneda="${m.moneda}"
              value="${valInput}"
            />
            <span class="metodo-conversion" id="conv-${m.key}">
              ${esBs ? fmtUSD(state.tasa > 0 ? valInput / state.tasa : 0)
                     : fmtBs(valInput * state.tasa)}
            </span>
          </div>
        </div>`;
    }).join('');

    updateTotalesPagos();
}

function updateConversion(metodo) {
    const m     = METODOS.find(x => x.key === metodo);
    const input = document.getElementById(`pago-${metodo}`);
    const convEl= document.getElementById(`conv-${metodo}`);
    if (!m || !input || !convEl) return;

    const val = parseFloat(input.value) || 0;
    convEl.textContent = m.moneda === 'bs'
        ? fmtUSD(state.tasa > 0 ? val / state.tasa : 0)
        : fmtBs(val * state.tasa);

    updateTotalesPagos();
}

function updateTotalesPagos() {
    let totalUSD = 0, totalBs = 0;
    METODOS.forEach(m => {
        const input = document.getElementById(`pago-${m.key}`);
        if (!input) return;
        const val = parseFloat(input.value) || 0;
        if (m.moneda === 'bs') {
            totalBs  += val;
            totalUSD += state.tasa > 0 ? val / state.tasa : 0;
        } else {
            totalUSD += val;
            totalBs  += val * state.tasa;
        }
    });
    document.getElementById('total-pagos-usd').textContent = fmtUSD(totalUSD);
    document.getElementById('total-pagos-bs').textContent  = fmtBs(totalBs);
    updateResumen();
}

async function guardarPagos() {
    if (!state.cuadreId) { toast('Guarda la tasa primero', 'err'); return; }
    const btn = document.getElementById('btn-guardar-pagos');
    setBusy(btn, true, 'Guardar pagos del día');

    const calls = METODOS.map(m => {
        const input   = document.getElementById(`pago-${m.key}`);
        const val     = parseFloat(input?.value) || 0;
        const monto_bs  = m.moneda === 'bs'  ? +val.toFixed(2)  : +(val * state.tasa).toFixed(2);
        const monto_usd = m.moneda === 'usd' ? +val.toFixed(2)  : +(state.tasa > 0 ? val / state.tasa : 0).toFixed(2);
        return api('https://theplace818app.gastroredes.com/api/cajero/guardar_pago.php', {
            cuadre_id: state.cuadreId,
            metodo: m.key,
            monto_bs,
            monto_usd,
        });
    });

    try {
        const results = await Promise.all(calls);
        const errores = results.filter(r => !r?.success).length;
        if (errores === 0) toast('Pagos guardados correctamente');
        else toast(`${errores} pago(s) no se guardaron`, 'err');
    } catch {
        toast('Error de conexión', 'err');
    } finally {
        setBusy(btn, false, 'Guardar pagos del día');
    }
}

// ─── Gastos ───────────────────────────────────────────────────────────────

function renderGastos() {
    const lista  = document.getElementById('lista-gastos');
    const totDiv = document.getElementById('totales-gastos');

    if (state.gastos.length === 0) {
        lista.innerHTML  = '<p class="empty-msg">Sin gastos registrados hoy</p>';
        totDiv.style.display = 'none';
        updateResumen();
        return;
    }

    lista.innerHTML = state.gastos.map(g => `
        <div class="item-row" data-id="${g.id}">
          <div class="item-info">
            <div class="item-top">
              <span class="badge-cat cat-${esc(g.categoria)}">${esc(CATEGORIAS[g.categoria] ?? g.categoria)}</span>
              <span class="item-desc">${esc(g.descripcion)}</span>
            </div>
          </div>
          <div class="item-right">
            <span class="item-monto-usd num-red">${fmtUSD(g.monto_usd)}</span>
            <span class="item-monto-bs">${fmtBs(g.monto_bs)}</span>
          </div>
          ${state.cerrado ? '' : `<button class="btn-del hide-cerrado" onclick="eliminarGasto(${g.id})" title="Eliminar">✕</button>`}
        </div>`).join('');

    const tUSD = state.gastos.reduce((s, g) => s + g.monto_usd, 0);
    const tBs  = state.gastos.reduce((s, g) => s + g.monto_bs, 0);
    document.getElementById('g-total-usd').textContent = fmtUSD(tUSD);
    document.getElementById('g-total-bs').textContent  = fmtBs(tBs);
    totDiv.style.display = 'block';
    updateResumen();
}

async function agregarGasto(e) {
    e.preventDefault();
    const form       = e.target;
    const btn        = form.querySelector('button[type="submit"]');
    const categoria  = form.categoria.value;
    const descripcion= form.descripcion.value.trim();
    const monto_bs   = parseFloat(form.monto_bs.value) || 0;
    const monto_usd  = state.tasa > 0 ? +(monto_bs / state.tasa).toFixed(2) : 0;

    if (!categoria || !descripcion || monto_bs <= 0) {
        toast('Completa todos los campos del gasto', 'err'); return;
    }

    setBusy(btn, true, '+ Agregar gasto');
    const data = await api('https://theplace818app.gastroredes.com/api/cajero/guardar_gasto.php', {
        cuadre_id: state.cuadreId, categoria, descripcion,
        monto_bs: +monto_bs.toFixed(2), monto_usd,
    });
    setBusy(btn, false, '+ Agregar gasto');

    if (data?.success) {
        state.gastos.push({ id: data.id, categoria, descripcion, monto_bs: +monto_bs.toFixed(2), monto_usd });
        renderGastos();
        form.reset();
        document.getElementById('gasto-hint').textContent = '';
        toast('Gasto agregado');
    } else {
        toast(data?.error ?? 'Error al agregar gasto', 'err');
    }
}

window.eliminarGasto = async function(id) {
    if (!confirm('¿Eliminar este gasto?')) return;
    const data = await api('https://theplace818app.gastroredes.com/api/cajero/eliminar_gasto.php', { gasto_id: id });
    if (data?.success) {
        state.gastos = state.gastos.filter(g => g.id !== id);
        renderGastos();
        toast('Gasto eliminado');
    } else {
        toast(data?.error ?? 'Error al eliminar', 'err');
    }
};

// ─── Consumo familiar ─────────────────────────────────────────────────────

function renderConsumo() {
    const lista  = document.getElementById('lista-consumo');
    const totDiv = document.getElementById('totales-consumo');

    if (state.consumo.length === 0) {
        lista.innerHTML      = '<p class="empty-msg">Sin consumos registrados hoy</p>';
        totDiv.style.display = 'none';
        updateResumen();
        return;
    }

    lista.innerHTML = state.consumo.map(c => {
        const subtotal = c.cantidad * c.precio_usd;
        const subBs    = subtotal * state.tasa;
        return `
        <div class="item-row" data-id="${c.id}">
          <div class="item-info">
            <div class="item-top">
              <span class="badge-resp">${esc(c.responsable)}</span>
              <span class="item-desc">${esc(c.descripcion)} ×${c.cantidad}</span>
            </div>
          </div>
          <div class="item-right">
            <span class="item-monto-usd num-amber">${fmtUSD(subtotal)}</span>
            <span class="item-monto-bs">${fmtBs(subBs)}</span>
          </div>
          ${state.cerrado ? '' : `<button class="btn-del hide-cerrado" onclick="eliminarConsumo(${c.id})" title="Eliminar">✕</button>`}
        </div>`;
    }).join('');

    const tUSD = state.consumo.reduce((s, c) => s + c.cantidad * c.precio_usd, 0);
    document.getElementById('c-total-usd').textContent = fmtUSD(tUSD);
    totDiv.style.display = 'block';
    updateResumen();
}

async function agregarConsumo(e) {
    e.preventDefault();
    const form       = e.target;
    const btn        = form.querySelector('button[type="submit"]');
    const responsable= form.responsable.value.trim();
    const descripcion= form.descripcion.value.trim();
    const cantidad   = Math.max(1, parseInt(form.cantidad.value) || 1);
    const precio_usd = parseFloat(form.precio_usd.value) || 0;
    const precio_bs  = +(precio_usd * state.tasa).toFixed(2);

    if (!responsable || !descripcion || precio_usd <= 0) {
        toast('Completa todos los campos del consumo', 'err'); return;
    }

    setBusy(btn, true, '+ Agregar consumo');
    const data = await api('https://theplace818app.gastroredes.com/api/cajero/guardar_consumo.php', {
        cuadre_id: state.cuadreId, responsable, descripcion,
        cantidad, precio_usd: +precio_usd.toFixed(2), precio_bs,
    });
    setBusy(btn, false, '+ Agregar consumo');

    if (data?.success) {
        state.consumo.push({ id: data.id, responsable, descripcion, cantidad, precio_usd: +precio_usd.toFixed(2), precio_bs });
        renderConsumo();
        form.reset();
        form.cantidad.value = 1;
        document.getElementById('consumo-hint').textContent = '';
        toast('Consumo agregado');
    } else {
        toast(data?.error ?? 'Error al agregar consumo', 'err');
    }
}

window.eliminarConsumo = async function(id) {
    if (!confirm('¿Eliminar este consumo?')) return;
    const data = await api('https://theplace818app.gastroredes.com/api/cajero/eliminar_consumo.php', { consumo_id: id });
    if (data?.success) {
        state.consumo = state.consumo.filter(c => c.id !== id);
        renderConsumo();
        toast('Consumo eliminado');
    } else {
        toast(data?.error ?? 'Error al eliminar', 'err');
    }
};

// ─── Resumen ─────────────────────────────────────────────────────────────

function updateResumen() {
    let ventasUSD = 0;
    METODOS.forEach(m => {
        const input = document.getElementById(`pago-${m.key}`);
        const val   = parseFloat(input?.value) || 0;
        ventasUSD  += m.moneda === 'bs'
            ? (state.tasa > 0 ? val / state.tasa : 0)
            : val;
    });

    const gastosUSD  = state.gastos.reduce((s, g) => s + g.monto_usd, 0);
    const consumoUSD = state.consumo.reduce((s, c) => s + c.cantidad * c.precio_usd, 0);
    const balance    = ventasUSD - gastosUSD - consumoUSD;

    document.getElementById('res-ventas').textContent  = fmtUSD(ventasUSD);
    document.getElementById('res-gastos').textContent  = fmtUSD(gastosUSD);
    document.getElementById('res-consumo').textContent = fmtUSD(consumoUSD);

    const balEl = document.getElementById('res-balance');
    balEl.textContent = fmtUSD(balance);
    balEl.className   = `resumen-num ${balance >= 0 ? 'num-up' : 'num-down'}`;
}

// ─── Cerrar caja ─────────────────────────────────────────────────────────

async function cerrarCaja() {
    const obs = document.getElementById('observaciones').value.trim();
    if (!confirm('¿Confirmas el cierre de caja?\n\nEsta acción no se puede deshacer.')) return;

    const btn = document.getElementById('btn-cerrar-caja');
    setBusy(btn, true, '🔒 Cerrar caja del día');

    const data = await api('https://theplace818app.gastroredes.com/api/cajero/cerrar_caja.php', {
        cuadre_id: state.cuadreId,
        observaciones: obs,
    });

    setBusy(btn, false, '🔒 Cerrar caja del día');

    if (data?.success) {
        state.cerrado = true;
        activarModoSoloLectura();
        toast('Caja cerrada correctamente');
    } else {
        toast(data?.error ?? 'Error al cerrar caja', 'err');
    }
}

function activarModoSoloLectura() {
    document.body.classList.add('is-cerrado');
    document.getElementById('banner-cerrado').classList.remove('hidden');
    document.getElementById('observaciones').disabled = true;
    METODOS.forEach(m => {
        const el = document.getElementById(`pago-${m.key}`);
        if (el) el.disabled = true;
    });
    document.getElementById('input-tasa').disabled = true;
    // Redibujar listas para ocultar botones de eliminar
    renderGastos();
    renderConsumo();
}

// ─── Carga inicial ───────────────────────────────────────────────────────

async function init() {
    const data = await api('https://theplace818app.gastroredes.com/api/cajero/obtener_cuadre.php');
    if (!data) return;

    if (data.tasa) {
        state.tasa   = data.tasa.tasa;
        state.tasaId = data.tasa.id;
        renderTasaActiva();
    }

    if (data.cuadre) {
        state.cuadreId = data.cuadre.id;
        state.cerrado  = data.cuadre.cerrado;
        state.pagos    = data.pagos   ?? {};
        state.gastos   = data.gastos  ?? [];
        state.consumo  = data.consumo ?? [];

        document.getElementById('form-body').classList.remove('hidden');
        buildPagosHTML();
        renderGastos();
        renderConsumo();
        updateResumen();

        if (data.cuadre.observaciones) {
            document.getElementById('observaciones').value = data.cuadre.observaciones;
        }
        if (state.cerrado) activarModoSoloLectura();
    }
}

// ─── Eventos ─────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
    init();

    // Tasa
    document.getElementById('btn-tasa').addEventListener('click', guardarTasa);
    document.getElementById('input-tasa').addEventListener('keydown', e => {
        if (e.key === 'Enter') guardarTasa();
    });

    // Pagos — delegación de eventos
    document.getElementById('pagos-container').addEventListener('input', e => {
        if (e.target.classList.contains('metodo-input')) {
            updateConversion(e.target.dataset.metodo);
        }
    });
    document.getElementById('btn-guardar-pagos').addEventListener('click', guardarPagos);

    // Gastos
    document.getElementById('form-gasto').addEventListener('submit', agregarGasto);
    document.getElementById('gasto-monto-bs').addEventListener('input', e => {
        const val = parseFloat(e.target.value) || 0;
        const usd = state.tasa > 0 ? val / state.tasa : 0;
        const hint = document.getElementById('gasto-hint');
        hint.innerHTML = usd > 0 ? `<span>${fmtUSD(usd)}</span>` : '';
    });

    // Consumo
    document.getElementById('form-consumo').addEventListener('submit', agregarConsumo);
    const actualizarConsumoHint = () => {
        const form     = document.getElementById('form-consumo');
        const cantidad = parseInt(form.cantidad.value) || 1;
        const precio   = parseFloat(form.precio_usd.value) || 0;
        const sub      = cantidad * precio;
        const hint     = document.getElementById('consumo-hint');
        hint.innerHTML = sub > 0
            ? `<span>${fmtUSD(sub)}</span> = ${fmtBs(sub * state.tasa)}`
            : '';
    };
    document.getElementById('consumo-precio-usd').addEventListener('input', actualizarConsumoHint);
    document.getElementById('consumo-cantidad').addEventListener('input', actualizarConsumoHint);

    // Cerrar caja
    document.getElementById('btn-cerrar-caja').addEventListener('click', cerrarCaja);
});
