<?php /* Vista de Créditos — incluida desde index.php */ ?>

<!-- ===== CABECERA ===== -->
<div class="cred-header">
  <div>
    <h3 class="cred-title">Módulo de Créditos</h3>
    <p id="cred-total-label" class="cred-total-label">Cargando saldo…</p>
  </div>
  <button class="btn btn-primary btn-sm" id="cred-btn-nuevo-cliente">+ Nuevo cliente</button>
</div>

<!-- ===== LAYOUT DOS COLUMNAS ===== -->
<div class="cred-layout">

  <!-- ── Columna izquierda: lista de clientes ── -->
  <aside class="cred-sidebar">
    <input type="search" id="cred-buscador" class="cred-search" placeholder="Buscar cliente…" />
    <div id="cred-lista-clientes" class="cred-client-list">
      <p class="cred-empty">Cargando clientes…</p>
    </div>
  </aside>

  <!-- ── Columna derecha: detalle del cliente ── -->
  <section id="cred-detalle" class="cred-detalle">
    <div class="cred-detalle-placeholder">
      <p>Selecciona un cliente para ver sus créditos</p>
    </div>
  </section>

</div>

<!-- ===== MODAL CLIENTE ===== -->
<div id="modal-cliente" class="cred-modal-overlay hidden">
  <div class="cred-modal">
    <h4 id="modal-cliente-title">Nuevo cliente</h4>
    <input type="hidden" id="mc-id" value="0" />
    <div class="form-group">
      <label for="mc-nombre">Nombre *</label>
      <input type="text" id="mc-nombre" placeholder="Nombre completo" maxlength="150" />
    </div>
    <div class="form-group">
      <label for="mc-telefono">Teléfono</label>
      <input type="tel" id="mc-telefono" placeholder="0414-0000000" maxlength="20" />
    </div>
    <div class="form-group">
      <label for="mc-cedula">Cédula</label>
      <input type="text" id="mc-cedula" placeholder="V-00000000" maxlength="20" />
    </div>
    <div id="mc-error" class="error-msg hidden"></div>
    <div class="cred-modal-actions">
      <button class="btn btn-outline btn-sm" id="mc-cancelar">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="mc-guardar">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== MODAL CRÉDITO ===== -->
<div id="modal-credito" class="cred-modal-overlay hidden">
  <div class="cred-modal">
    <h4>Nuevo crédito</h4>
    <input type="hidden" id="mcr-cliente-id" value="0" />
    <div class="form-group">
      <label for="mcr-descripcion">Descripción *</label>
      <input type="text" id="mcr-descripcion" placeholder="Ej: Consumo del día" maxlength="255" />
    </div>
    <div class="form-group">
      <label for="mcr-monto-usd">Monto USD *</label>
      <input type="number" id="mcr-monto-usd" placeholder="0.00" step="0.01" min="0.01" />
    </div>
    <div class="form-group">
      <label for="mcr-monto-bs">Monto Bs (opcional)</label>
      <input type="number" id="mcr-monto-bs" placeholder="0.00" step="0.01" min="0" />
    </div>
    <div id="mcr-error" class="error-msg hidden"></div>
    <div class="cred-modal-actions">
      <button class="btn btn-outline btn-sm" id="mcr-cancelar">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="mcr-guardar">Registrar crédito</button>
    </div>
  </div>
</div>

<!-- ===== MODAL ABONO ===== -->
<div id="modal-abono" class="cred-modal-overlay hidden">
  <div class="cred-modal">
    <h4>Registrar abono</h4>
    <p id="mab-info" class="cred-abono-info"></p>
    <input type="hidden" id="mab-credito-id" value="0" />
    <div class="form-group">
      <label for="mab-monto-usd">Monto USD *</label>
      <input type="number" id="mab-monto-usd" placeholder="0.00" step="0.01" min="0.01" />
    </div>
    <div class="form-group">
      <label for="mab-monto-bs">Monto Bs (opcional)</label>
      <input type="number" id="mab-monto-bs" placeholder="0.00" step="0.01" min="0" />
    </div>
    <div id="mab-error" class="error-msg hidden"></div>
    <div class="cred-modal-actions">
      <button class="btn btn-outline btn-sm" id="mab-cancelar">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="mab-guardar">Registrar abono</button>
    </div>
  </div>
</div>
