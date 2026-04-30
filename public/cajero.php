<?php
declare(strict_types=1);
session_start();

// Guard: solo cajero o administrador
if (!isset($_SESSION['user_id'])) {
    header('Location: /');
    exit;
}
$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['cajero', 'administrador'], true)) {
    header('Location: /');
    exit;
}

$nombre = htmlspecialchars($_SESSION['nombre'] ?? '', ENT_QUOTES);
$rolLabel = match($rol) {
    'administrador' => 'Administrador',
    'cajero'        => 'Cajero',
    default         => $rol,
};
$fechaHoy = date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="theme-color" content="#1a1a2e" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <title>Caja del Día — The Place 818</title>
  <link rel="manifest" href="/manifest.json" />
  <link rel="stylesheet" href="/assets/css/app.css" />
  <link rel="stylesheet" href="/assets/css/cajero.css" />
</head>
<body>

<!-- ================================================================
     CABECERA
================================================================ -->
<header class="app-header">
  <div class="app-title">The Place <span>818</span></div>
  <div class="user-info">
    <div style="text-align:right;line-height:1.2;">
      <span class="user-name"><?= $nombre ?></span><br>
      <span style="font-size:0.7rem;color:var(--color-muted);"><?= $fechaHoy ?></span>
    </div>
    <span class="badge badge-<?= htmlspecialchars($rol) ?>"><?= $rolLabel ?></span>
    <button id="logout-btn" class="btn btn-sm btn-outline">Salir</button>
  </div>
</header>

<!-- Banner caja cerrada (oculto por defecto) -->
<div id="banner-cerrado" class="banner-cerrado hidden">
  🔒 CAJA CERRADA — Solo lectura
</div>

<!-- ================================================================
     CONTENIDO PRINCIPAL
================================================================ -->
<main class="cajero-main">

  <!-- ── 1. TASA BCV ─────────────────────────────────────────────── -->
  <section class="card sec-tasa" id="sec-tasa">
    <p class="card-title">💱 Tasa BCV del Día</p>

    <div class="tasa-row">
      <input
        type="number" id="input-tasa"
        min="0.01" step="0.01" placeholder="Ej: 87.37"
        inputmode="decimal"
        style="font-size:1.25rem;font-weight:700;text-align:right;"
      />
      <button id="btn-tasa" class="btn btn-primary hide-cerrado">Guardar</button>
    </div>

    <div id="tasa-activa" class="tasa-activa hidden">
      <span>Tasa activa:</span>
      <strong id="tasa-val"></strong>
    </div>
  </section>

  <!-- ── RESTO DEL FORMULARIO (aparece tras guardar tasa) ──────── -->
  <div id="form-body" class="hidden">

    <!-- ── 2. MÉTODOS DE PAGO ───────────────────────────────────── -->
    <section class="card sec-pagos" id="sec-pagos">
      <p class="card-title">💳 Métodos de Pago</p>

      <!-- Los 6 métodos se generan con JS -->
      <div id="pagos-container"></div>

      <div class="section-totales">
        <div class="totales-row">
          <span>Total ventas del día</span>
          <div class="totales-nums">
            <span class="t-usd" id="total-pagos-usd">$0.00</span>
            <span class="t-bs"  id="total-pagos-bs">Bs 0.00</span>
          </div>
        </div>
      </div>

      <div style="margin-top:1rem;">
        <button id="btn-guardar-pagos" class="btn btn-green btn-block hide-cerrado">
          Guardar pagos del día
        </button>
      </div>
    </section>

    <!-- ── 3. GASTOS DEL DÍA ────────────────────────────────────── -->
    <section class="card sec-gastos" id="sec-gastos">
      <p class="card-title">💸 Gastos del Día</p>

      <form id="form-gasto" class="add-form hide-cerrado">
        <div class="form-grid-2">
          <div class="form-group">
            <label>Categoría</label>
            <select name="categoria" required>
              <option value="">Seleccionar…</option>
              <option value="materia_prima">Materia prima</option>
              <option value="operativos">Operativos</option>
              <option value="nomina">Nómina</option>
              <option value="otros">Otros</option>
            </select>
          </div>
          <div class="form-group">
            <label>Monto (Bs)</label>
            <input
              type="number" name="monto_bs" id="gasto-monto-bs"
              min="0" step="0.01" placeholder="0.00"
              inputmode="decimal" required
            />
          </div>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <input type="text" name="descripcion" placeholder="Ej: Pollo, gas, pago personal…" required />
        </div>

        <div class="conversion-hint" id="gasto-hint"></div>

        <button type="submit" class="btn btn-red btn-block">+ Agregar gasto</button>
      </form>

      <div class="lista-items" id="lista-gastos">
        <p class="empty-msg">Sin gastos registrados hoy</p>
      </div>

      <div class="section-totales" id="totales-gastos" style="display:none;">
        <div class="totales-row">
          <span>Total gastos</span>
          <div class="totales-nums">
            <span class="t-usd num-red" id="g-total-usd">$0.00</span>
            <span class="t-bs"         id="g-total-bs">Bs 0.00</span>
          </div>
        </div>
      </div>
    </section>

    <!-- ── 4. CONSUMO FAMILIAR ──────────────────────────────────── -->
    <section class="card sec-consumo" id="sec-consumo">
      <p class="card-title">🍽️ Consumo Familiar</p>

      <form id="form-consumo" class="add-form hide-cerrado">
        <div class="form-grid-2">
          <div class="form-group">
            <label>Responsable</label>
            <input type="text" name="responsable" placeholder="Nombre" required />
          </div>
          <div class="form-group">
            <label>Cantidad</label>
            <input type="number" name="cantidad" id="consumo-cantidad" min="1" value="1" required />
          </div>
        </div>

        <div class="form-group">
          <label>Descripción</label>
          <input type="text" name="descripcion" placeholder="Ej: Pizza familiar, refresco…" required />
        </div>

        <div class="form-group">
          <label>Precio unitario ($)</label>
          <input
            type="number" name="precio_usd" id="consumo-precio-usd"
            min="0" step="0.01" placeholder="0.00"
            inputmode="decimal" required
          />
        </div>

        <div class="conversion-hint" id="consumo-hint"></div>

        <button type="submit" class="btn btn-amber btn-block">+ Agregar consumo</button>
      </form>

      <div class="lista-items" id="lista-consumo">
        <p class="empty-msg">Sin consumos registrados hoy</p>
      </div>

      <div class="section-totales" id="totales-consumo" style="display:none;">
        <div class="totales-row">
          <span>Total consumo familiar</span>
          <span class="t-usd num-amber" id="c-total-usd">$0.00</span>
        </div>
      </div>
    </section>

    <!-- ── 5. RESUMEN + CERRAR CAJA ─────────────────────────────── -->
    <section class="card sec-resumen" id="sec-resumen">
      <p class="card-title">📊 Resumen del Día</p>

      <div class="resumen-grid">
        <div class="resumen-card">
          <span class="resumen-label-sm">Total ventas</span>
          <span class="resumen-num num-green" id="res-ventas">$0.00</span>
        </div>
        <div class="resumen-card">
          <span class="resumen-label-sm">Total gastos</span>
          <span class="resumen-num num-red" id="res-gastos">$0.00</span>
        </div>
        <div class="resumen-card">
          <span class="resumen-label-sm">Consumo familiar</span>
          <span class="resumen-num num-amber" id="res-consumo">$0.00</span>
        </div>
        <div class="resumen-card full">
          <span class="resumen-label-sm">Balance estimado (ventas − gastos − consumo)</span>
          <span class="resumen-num num-green" id="res-balance">$0.00</span>
        </div>
      </div>

      <div class="form-group">
        <label for="observaciones">Observaciones del día</label>
        <textarea
          id="observaciones"
          rows="3"
          placeholder="Notas, incidencias, comentarios del día…"
          style="resize:vertical;"
        ></textarea>
      </div>

      <button id="btn-cerrar-caja" class="btn btn-cerrar btn-block hide-cerrado" style="margin-top:0.5rem;">
        🔒 Cerrar caja del día
      </button>
    </section>

  </div><!-- /form-body -->

</main>

<!-- Toast de notificaciones -->
<div id="toast" class="toast hidden"></div>

<script src="/assets/js/cajero.js"></script>
</body>
</html>
