<?php /* Vista de Usuarios — incluida desde index.php */ ?>

<!-- ===== CABECERA ===== -->
<div class="usr-header">
  <h3 class="usr-title">Gestión de Usuarios</h3>
  <button class="btn btn-primary btn-sm" id="usr-btn-nuevo">+ Nuevo usuario</button>
</div>

<!-- ===== TABLA ===== -->
<div class="usr-table-wrap">
  <table class="usr-table" id="usr-tabla">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Email</th>
        <th>Rol</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody id="usr-tbody">
      <tr><td colspan="5" class="usr-empty">Cargando usuarios…</td></tr>
    </tbody>
  </table>
</div>

<!-- ===== MODAL USUARIO ===== -->
<div id="modal-usuario" class="usr-modal-overlay hidden">
  <div class="usr-modal">
    <h4 id="mu-title">Nuevo usuario</h4>
    <input type="hidden" id="mu-id" value="0" />

    <div class="form-group">
      <label for="mu-nombre">Nombre *</label>
      <input type="text" id="mu-nombre" placeholder="Nombre completo" maxlength="100" autocomplete="off" />
    </div>
    <div class="form-group">
      <label for="mu-email">Email *</label>
      <input type="email" id="mu-email" placeholder="correo@theplace818.com" autocomplete="off" />
    </div>
    <div class="form-group">
      <label for="mu-rol">Rol *</label>
      <select id="mu-rol">
        <option value="cajero">Cajero</option>
        <option value="dueño">Dueño</option>
        <option value="administrador">Administrador</option>
      </select>
    </div>
    <div id="mu-password-group" class="form-group">
      <label for="mu-password">Contraseña * <small>(mín. 8 caracteres)</small></label>
      <input type="password" id="mu-password" placeholder="••••••••" autocomplete="new-password" />
    </div>

    <div id="mu-error" class="error-msg hidden"></div>
    <div class="usr-modal-actions">
      <button class="btn btn-outline btn-sm" id="mu-cancelar">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="mu-guardar">Guardar</button>
    </div>
  </div>
</div>

<!-- ===== MODAL CONTRASEÑA ===== -->
<div id="modal-password" class="usr-modal-overlay hidden">
  <div class="usr-modal">
    <h4>Cambiar contraseña</h4>
    <p id="mp-nombre-label" class="usr-modal-subtitle"></p>
    <input type="hidden" id="mp-id" value="0" />
    <div class="form-group">
      <label for="mp-password">Nueva contraseña * <small>(mín. 8 caracteres)</small></label>
      <input type="password" id="mp-password" placeholder="••••••••" autocomplete="new-password" />
    </div>
    <div id="mp-error" class="error-msg hidden"></div>
    <div class="usr-modal-actions">
      <button class="btn btn-outline btn-sm" id="mp-cancelar">Cancelar</button>
      <button class="btn btn-primary btn-sm" id="mp-guardar">Cambiar contraseña</button>
    </div>
  </div>
</div>
