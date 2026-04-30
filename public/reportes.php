<?php
// Este archivo es incluido desde index.php — la sesión ya está activa.
// Solo llega aquí si $rol === 'administrador' || $rol === 'dueño'.
?>
<div class="reportes-main">

  <!-- Encabezado -->
  <div class="rpt-header">
    <h3 class="rpt-title">Dashboard</h3>
    <button id="btn-actualizar" class="btn btn-sm btn-outline">&#8635; Actualizar</button>
  </div>

  <!-- Selector de período -->
  <div class="card sec-periodo">
    <div class="periodo-btns">
      <button class="periodo-btn" data-periodo="hoy">Hoy</button>
      <button class="periodo-btn" data-periodo="semana">Esta semana</button>
      <button class="periodo-btn active" data-periodo="mes">Este mes</button>
      <button class="periodo-btn" data-periodo="mes_anterior">Mes anterior</button>
    </div>
    <div class="periodo-custom">
      <select id="select-mes">
        <option value="1">Enero</option>
        <option value="2">Febrero</option>
        <option value="3">Marzo</option>
        <option value="4">Abril</option>
        <option value="5">Mayo</option>
        <option value="6">Junio</option>
        <option value="7">Julio</option>
        <option value="8">Agosto</option>
        <option value="9">Septiembre</option>
        <option value="10">Octubre</option>
        <option value="11">Noviembre</option>
        <option value="12">Diciembre</option>
      </select>
      <select id="select-anio"></select>
      <button id="btn-periodo-custom" class="btn btn-sm btn-outline">Ver</button>
    </div>
    <div id="periodo-label" class="periodo-label"></div>
  </div>

  <!-- Estado: cargando -->
  <div id="rpt-loading" class="rpt-loading hidden">
    <span class="rpt-loading-dot">●</span>&nbsp;
    <span class="rpt-loading-dot" style="animation-delay:.2s">●</span>&nbsp;
    <span class="rpt-loading-dot" style="animation-delay:.4s">●</span>
  </div>

  <!-- Contenido principal -->
  <div id="rpt-content" class="hidden">

    <!-- Tarjetas de resumen -->
    <div class="summary-grid">
      <div class="summary-card sc-ventas-usd">
        <span class="sc-label">Total Ventas</span>
        <div class="sc-value sc-green" id="sc-ventas-usd">$0.00</div>
      </div>
      <div class="summary-card sc-ventas-bs">
        <span class="sc-label">Total Ventas Bs</span>
        <div class="sc-value sc-cyan" id="sc-ventas-bs">Bs 0.00</div>
      </div>
      <div class="summary-card sc-gastos">
        <span class="sc-label">Total Gastos</span>
        <div class="sc-value sc-red" id="sc-gastos-usd">$0.00</div>
      </div>
      <div class="summary-card sc-utilidad">
        <span class="sc-label">Utilidad Bruta</span>
        <div class="sc-value" id="sc-utilidad">$0.00</div>
        <div class="sc-sub">Ventas − Gastos</div>
      </div>
      <div class="summary-card sc-creditos">
        <span class="sc-label">Créditos Pendientes</span>
        <div class="sc-value sc-amber" id="sc-creditos">$0.00</div>
        <div class="sc-sub">Saldo total acumulado</div>
      </div>
      <div class="summary-card sc-abonos">
        <span class="sc-label">Abonos Recibidos</span>
        <div class="sc-value sc-blue" id="sc-abonos">$0.00</div>
        <div class="sc-sub">En el período</div>
      </div>
    </div>

    <!-- Tablas: ventas por método / gastos por categoría -->
    <div class="rpt-tables-grid">

      <div class="card sec-ventas-met">
        <div class="card-title">Ventas por método de pago</div>
        <div id="tabla-ventas-metodo">
          <p class="empty-msg">Sin datos</p>
        </div>
      </div>

      <div class="card sec-gastos-cat">
        <div class="card-title">Gastos por categoría</div>
        <div id="tabla-gastos-cat">
          <p class="empty-msg">Sin datos</p>
        </div>
      </div>

    </div>

    <!-- Gráfica de ventas diarias -->
    <div class="card sec-chart">
      <div class="card-title">Ventas diarias ($)</div>
      <div class="chart-wrapper">
        <canvas id="ventas-chart"></canvas>
      </div>
    </div>

    <!-- Historial de cuadres -->
    <div class="card sec-historial">
      <div class="card-title">Historial de cuadres</div>
      <div id="historial-container">
        <p class="empty-msg">Sin datos</p>
      </div>
    </div>

  </div><!-- /#rpt-content -->

  <!-- Sin datos -->
  <div id="rpt-empty" class="rpt-empty-msg hidden">
    Sin datos para este período
  </div>

</div><!-- /.reportes-main -->

<!-- Modal: detalle del día -->
<div id="modal-detalle" class="modal-overlay hidden">
  <div class="modal-card">
    <div class="modal-header">
      <h4 id="modal-title">Detalle del día</h4>
      <button id="modal-close" class="modal-close">✕</button>
    </div>
    <div id="modal-body" class="modal-body">
      <p class="empty-msg">Cargando…</p>
    </div>
  </div>
</div>
