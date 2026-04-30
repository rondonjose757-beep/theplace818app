'use strict';

/* ===================================================
   The Place 818 — Dashboard de Reportes
=================================================== */

const BASE_URL = 'https://theplace818app.gastroredes.com';

const METODO_LABELS = {
    tarjeta:           'Tarjeta',
    pago_movil:        'Pago Móvil',
    dolares_efectivo:  'Dólares Efectivo',
    zelle:             'Zelle',
    credito:           'Crédito',
    efectivo_bs:       'Efectivo Bs',
};

const CAT_LABELS = {
    materia_prima: 'Materia Prima',
    operativos:    'Operativos',
    nomina:        'Nómina',
    otros:         'Otros',
};

const MESES = [
    'Enero','Febrero','Marzo','Abril','Mayo','Junio',
    'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre',
];

// ---- Utilidades de formato ----
function fmtUSD(n) {
    return '$' + Number(n).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtBs(n) {
    return 'Bs ' + Number(n).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtPct(n) {
    return Number(n).toFixed(1) + '%';
}
function fmtFecha(str) {
    // "2026-04-30" → "30/04/2026"
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y}`;
}
function esc(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

// ---- Estado del dashboard ----
const rptState = {
    periodo:  'mes',
    mes:      new Date().getMonth() + 1,
    anio:     new Date().getFullYear(),
    data:     null,
    chart:    null,
};

// ---- Elementos DOM ----
function el(id) { return document.getElementById(id); }

// ---- Arranque ----
(function init() {
    // Solo inicializar si el módulo de reportes está en el DOM
    if (!el('rpt-loading')) return;

    poblarSelectAnio();
    sincronizarSelectores();

    // Click en tab "Reportes"
    const navBtn = document.querySelector('[data-view="reportes"]');
    if (navBtn) {
        navBtn.addEventListener('click', cargarDatos);
    }

    // Botón actualizar
    el('btn-actualizar')?.addEventListener('click', cargarDatos);

    // Botones de período predefinido
    document.querySelectorAll('.periodo-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.periodo-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            rptState.periodo = btn.dataset.periodo;
            cargarDatos();
        });
    });

    // Botón "Ver" del selector personalizado
    el('btn-periodo-custom')?.addEventListener('click', () => {
        document.querySelectorAll('.periodo-btn').forEach(b => b.classList.remove('active'));
        rptState.periodo = 'personalizado';
        rptState.mes     = parseInt(el('select-mes').value, 10);
        rptState.anio    = parseInt(el('select-anio').value, 10);
        cargarDatos();
    });

    // Cierre de modal
    el('modal-close')?.addEventListener('click', cerrarModal);
    el('modal-detalle')?.addEventListener('click', e => {
        if (e.target === el('modal-detalle')) cerrarModal();
    });
})();

function poblarSelectAnio() {
    const sel  = el('select-anio');
    if (!sel) return;
    const anioActual = new Date().getFullYear();
    for (let a = anioActual; a >= anioActual - 4; a--) {
        const opt = document.createElement('option');
        opt.value = a;
        opt.textContent = a;
        sel.appendChild(opt);
    }
}

function sincronizarSelectores() {
    const selMes  = el('select-mes');
    const selAnio = el('select-anio');
    if (!selMes || !selAnio) return;
    selMes.value  = rptState.mes;
    selAnio.value = rptState.anio;
}

// ---- Carga de datos ----
async function cargarDatos() {
    mostrarEstado('loading');

    const params = new URLSearchParams({ periodo: rptState.periodo });
    if (rptState.periodo === 'personalizado') {
        params.set('mes',  rptState.mes);
        params.set('anio', rptState.anio);
    }

    try {
        const res  = await fetch(`${BASE_URL}/api/reportes/resumen.php?${params}`, {
            credentials: 'include',
        });

        if (res.status === 401) { window.location.reload(); return; }
        if (res.status === 403) { mostrarEstado('empty'); return; }

        const data = await res.json();
        if (!data.success) { mostrarEstado('empty'); return; }

        rptState.data = data;

        const hayCuadres = data.cuadres.length > 0;
        mostrarEstado(hayCuadres ? 'content' : 'empty');

        if (hayCuadres) {
            renderPeriodoLabel(data.periodo.label);
            renderSummary(data.resumen);
            renderVentasPorMetodo(data.ventas_por_metodo);
            renderGastosPorCat(data.gastos_por_categoria);
            renderChart(data.ventas_diarias);
            renderHistorial(data.cuadres);
        }
    } catch {
        mostrarEstado('empty');
    }
}

function mostrarEstado(estado) {
    el('rpt-loading')?.classList.add('hidden');
    el('rpt-content')?.classList.add('hidden');
    el('rpt-empty')?.classList.add('hidden');

    if (estado === 'loading')  el('rpt-loading')?.classList.remove('hidden');
    if (estado === 'content')  el('rpt-content')?.classList.remove('hidden');
    if (estado === 'empty')    el('rpt-empty')?.classList.remove('hidden');
}

// ---- Renderizado de secciones ----

function renderPeriodoLabel(label) {
    const lbl = el('periodo-label');
    if (lbl) lbl.textContent = label;
}

function renderSummary(r) {
    el('sc-ventas-usd').textContent = fmtUSD(r.total_ventas_usd);
    el('sc-ventas-bs').textContent  = fmtBs(r.total_ventas_bs);
    el('sc-gastos-usd').textContent = fmtUSD(r.total_gastos_usd);

    const utilEl = el('sc-utilidad');
    utilEl.textContent = fmtUSD(r.utilidad_usd);
    utilEl.className   = 'sc-value ' + (r.utilidad_usd >= 0 ? 'sc-pos' : 'sc-neg');

    el('sc-creditos').textContent = fmtUSD(r.total_creditos_pendientes);
    el('sc-abonos').textContent   = fmtUSD(r.total_abonos_usd);
}

function renderVentasPorMetodo(filas) {
    const cont = el('tabla-ventas-metodo');
    if (!cont) return;

    if (!filas.length) {
        cont.innerHTML = '<p class="empty-msg">Sin datos</p>';
        return;
    }

    const totalUsd = filas.reduce((s, f) => s + f.total_usd, 0);
    let html = `
        <table class="rpt-table">
          <thead>
            <tr>
              <th>Método</th>
              <th>Total Bs</th>
              <th>Total $</th>
              <th>%</th>
            </tr>
          </thead>
          <tbody>`;

    filas.forEach(f => {
        html += `
            <tr>
              <td>
                <span class="met-badge met-${esc(f.metodo)}">${esc(METODO_LABELS[f.metodo] || f.metodo)}</span>
                <div class="pct-bar-wrap"><div class="pct-bar-fill fill-green" style="width:${f.pct}%"></div></div>
              </td>
              <td class="td-bs">${fmtBs(f.total_bs)}</td>
              <td class="td-usd">${fmtUSD(f.total_usd)}</td>
              <td class="td-pct">${fmtPct(f.pct)}</td>
            </tr>`;
    });

    html += `
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td></td>
              <td class="td-usd">${fmtUSD(totalUsd)}</td>
              <td>100%</td>
            </tr>
          </tfoot>
        </table>`;

    cont.innerHTML = html;
}

function renderGastosPorCat(filas) {
    const cont = el('tabla-gastos-cat');
    if (!cont) return;

    if (!filas.length) {
        cont.innerHTML = '<p class="empty-msg">Sin datos</p>';
        return;
    }

    const totalUsd = filas.reduce((s, f) => s + f.total_usd, 0);
    let html = `
        <table class="rpt-table">
          <thead>
            <tr>
              <th>Categoría</th>
              <th>Total Bs</th>
              <th>Total $</th>
              <th>%</th>
            </tr>
          </thead>
          <tbody>`;

    filas.forEach(f => {
        html += `
            <tr>
              <td>
                <span class="cat-badge cat-${esc(f.categoria)}">${esc(CAT_LABELS[f.categoria] || f.categoria)}</span>
                <div class="pct-bar-wrap"><div class="pct-bar-fill fill-red" style="width:${f.pct}%"></div></div>
              </td>
              <td class="td-bs">${fmtBs(f.total_bs)}</td>
              <td class="td-usd">${fmtUSD(f.total_usd)}</td>
              <td class="td-pct">${fmtPct(f.pct)}</td>
            </tr>`;
    });

    html += `
          </tbody>
          <tfoot>
            <tr>
              <td>Total</td>
              <td></td>
              <td class="td-usd">${fmtUSD(totalUsd)}</td>
              <td>100%</td>
            </tr>
          </tfoot>
        </table>`;

    cont.innerHTML = html;
}

function renderChart(ventasDiarias) {
    const canvas = el('ventas-chart');
    if (!canvas || typeof Chart === 'undefined') return;

    const labels = ventasDiarias.map(v => fmtFecha(v.fecha));
    const datos  = ventasDiarias.map(v => v.total_usd);

    if (rptState.chart) {
        rptState.chart.destroy();
        rptState.chart = null;
    }

    rptState.chart = new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                label: 'Ventas ($)',
                data: datos,
                backgroundColor: 'rgba(233, 69, 96, 0.65)',
                borderColor:     '#e94560',
                borderWidth:     1,
                borderRadius:    4,
            }],
        },
        options: {
            responsive:          true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => fmtUSD(ctx.parsed.y),
                    },
                },
            },
            scales: {
                x: {
                    ticks: { color: '#8888aa', font: { size: 10 }, maxRotation: 45 },
                    grid:  { color: 'rgba(42,42,74,0.8)' },
                },
                y: {
                    ticks: {
                        color: '#8888aa',
                        font:  { size: 10 },
                        callback: v => '$' + v.toLocaleString('es-VE'),
                    },
                    grid: { color: 'rgba(42,42,74,0.8)' },
                    beginAtZero: true,
                },
            },
        },
    });
}

function renderHistorial(cuadres) {
    const cont = el('historial-container');
    if (!cont) return;

    if (!cuadres.length) {
        cont.innerHTML = '<p class="empty-msg">Sin datos</p>';
        return;
    }

    let html = `
        <div class="historial-header">
          <span>Fecha</span>
          <span>Tasa</span>
          <span>Ventas $</span>
          <span>Gastos $</span>
          <span>Estado</span>
        </div>`;

    cuadres.forEach(c => {
        const statusClass = c.cerrado ? 'status-cerrado' : 'status-abierto';
        const statusText  = c.cerrado ? 'Cerrado' : 'Abierto';
        html += `
          <div class="historial-row" data-cuadre-id="${c.id}" role="button" tabindex="0">
            <span class="h-fecha">${fmtFecha(c.fecha)}</span>
            <span class="h-tasa">${c.tasa.toFixed(2)}</span>
            <span class="h-ventas">${fmtUSD(c.total_ventas_usd)}</span>
            <span class="h-gastos">${fmtUSD(c.total_gastos_usd)}</span>
            <span><span class="badge-cerrado ${statusClass}">${statusText}</span></span>
          </div>`;
    });

    cont.innerHTML = html;

    cont.querySelectorAll('.historial-row').forEach(row => {
        row.addEventListener('click',   () => abrirDetalle(parseInt(row.dataset.cuadreId, 10)));
        row.addEventListener('keydown', e => { if (e.key === 'Enter') abrirDetalle(parseInt(row.dataset.cuadreId, 10)); });
    });
}

// ---- Modal de detalle ----
async function abrirDetalle(cuadreId) {
    const modal = el('modal-detalle');
    const body  = el('modal-body');
    const title = el('modal-title');
    if (!modal || !body || !title) return;

    body.innerHTML = '<p class="empty-msg">Cargando…</p>';
    title.textContent = 'Detalle del día';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    try {
        const res  = await fetch(`${BASE_URL}/api/reportes/detalle_dia.php?cuadre_id=${cuadreId}`, {
            credentials: 'include',
        });
        const data = await res.json();

        if (!data.success) {
            body.innerHTML = `<p class="empty-msg">${esc(data.error || 'Error al cargar')}</p>`;
            return;
        }

        const { cuadre, pagos, gastos, consumo, totales } = data;
        title.textContent = `Detalle — ${fmtFecha(cuadre.fecha)}`;

        const balClass = totales.balance_usd >= 0 ? 'sc-pos' : 'sc-neg';

        let html = `
          <!-- Totales del día -->
          <div>
            <p class="modal-section-title">Resumen del día</p>
            <div class="modal-totals-grid">
              <div class="mt-item">
                <span class="mt-label">Tasa BCV</span>
                <span class="mt-val sc-cyan">Bs ${cuadre.tasa.toFixed(2)}</span>
              </div>
              <div class="mt-item">
                <span class="mt-label">Ventas</span>
                <span class="mt-val sc-green">${fmtUSD(totales.ventas_usd)}</span>
              </div>
              <div class="mt-item">
                <span class="mt-label">Ventas Bs</span>
                <span class="mt-val sc-cyan">${fmtBs(totales.ventas_bs)}</span>
              </div>
              <div class="mt-item">
                <span class="mt-label">Gastos</span>
                <span class="mt-val sc-red">${fmtUSD(totales.gastos_usd)}</span>
              </div>
              <div class="mt-item">
                <span class="mt-label">Consumo Fam.</span>
                <span class="mt-val sc-amber">${fmtUSD(totales.consumo_usd)}</span>
              </div>
              <div class="mt-item">
                <span class="mt-label">Balance</span>
                <span class="mt-val ${balClass}">${fmtUSD(totales.balance_usd)}</span>
              </div>
            </div>
          </div>`;

        // Pagos
        if (pagos.length) {
            html += `
              <div>
                <p class="modal-section-title">Pagos recibidos</p>
                <table class="rpt-table">
                  <thead><tr><th>Método</th><th>Monto Bs</th><th>Monto $</th></tr></thead>
                  <tbody>`;
            pagos.forEach(p => {
                html += `<tr>
                  <td><span class="met-badge met-${esc(p.metodo)}">${esc(METODO_LABELS[p.metodo] || p.metodo)}</span></td>
                  <td class="td-bs">${fmtBs(p.monto_bs)}</td>
                  <td class="td-usd">${fmtUSD(p.monto_usd)}</td>
                </tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Gastos
        if (gastos.length) {
            html += `
              <div>
                <p class="modal-section-title">Gastos del día</p>
                <table class="rpt-table">
                  <thead><tr><th>Categoría / Descripción</th><th>Monto Bs</th><th>Monto $</th></tr></thead>
                  <tbody>`;
            gastos.forEach(g => {
                html += `<tr>
                  <td>
                    <span class="cat-badge cat-${esc(g.categoria)}">${esc(CAT_LABELS[g.categoria] || g.categoria)}</span>
                    <div style="font-size:.78rem;color:var(--color-muted);margin-top:2px">${esc(g.descripcion)}</div>
                  </td>
                  <td class="td-bs">${fmtBs(g.monto_bs)}</td>
                  <td class="td-usd">${fmtUSD(g.monto_usd)}</td>
                </tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Consumo familiar
        if (consumo.length) {
            html += `
              <div>
                <p class="modal-section-title">Consumo familiar</p>
                <table class="rpt-table">
                  <thead><tr><th>Responsable / Descripción</th><th>Cant.</th><th>Subtotal $</th></tr></thead>
                  <tbody>`;
            consumo.forEach(c => {
                const sub = c.cantidad * c.precio_usd;
                html += `<tr>
                  <td>
                    <strong style="font-size:.82rem">${esc(c.responsable)}</strong>
                    <div style="font-size:.78rem;color:var(--color-muted)">${esc(c.descripcion)}</div>
                  </td>
                  <td class="text-right" style="font-size:.82rem">${c.cantidad}</td>
                  <td class="td-usd">${fmtUSD(sub)}</td>
                </tr>`;
            });
            html += `</tbody></table></div>`;
        }

        // Observaciones
        if (cuadre.observaciones) {
            html += `
              <div>
                <p class="modal-section-title">Observaciones</p>
                <div class="modal-obs">${esc(cuadre.observaciones)}</div>
              </div>`;
        }

        body.innerHTML = html;

    } catch {
        body.innerHTML = '<p class="empty-msg">Error al cargar el detalle</p>';
    }
}

function cerrarModal() {
    el('modal-detalle')?.classList.add('hidden');
    document.body.style.overflow = '';
}
