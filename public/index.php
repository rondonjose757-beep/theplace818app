<?php
declare(strict_types=1);
session_start();

$loggedIn = isset($_SESSION['user_id']);
$rol      = $_SESSION['rol']    ?? '';
$nombre   = $_SESSION['nombre'] ?? '';

$esAdmin  = $rol === 'administrador';
$esDueno  = in_array($rol, ['administrador', 'dueño'], true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#e94560" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <link rel="apple-touch-icon" href="/assets/icons/icon-192.png" />
  <title>The Place 818 — Control de Caja</title>
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="/public/assets/css/app.css" />
  <link rel="stylesheet" href="/public/assets/css/cajero.css" />
  <?php if ($esDueno): ?>
  <link rel="stylesheet" href="/public/assets/css/reportes.css" />
  <link rel="stylesheet" href="/public/assets/css/creditos.css" />
  <?php endif; ?>
  <?php if ($esAdmin): ?>
  <link rel="stylesheet" href="/public/assets/css/usuarios.css" />
  <?php endif; ?>
</head>
<body>

<?php if (!$loggedIn): ?>
<!-- ======================== LOGIN ======================== -->
<div id="login-screen" class="screen active">
  <div class="login-card">
    <div class="login-logo">
      <h1>The Place <span>818</span></h1>
      <p>Control de Caja</p>
    </div>
    <form id="login-form">
      <div class="form-group">
        <label for="email">Correo electrónico</label>
        <input type="email" id="email" name="email" required autocomplete="email" placeholder="usuario@theplace818.com" />
      </div>
      <div class="form-group">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="••••••••" />
      </div>
      <div id="login-error" class="error-msg hidden"></div>
      <button type="submit" class="btn btn-primary btn-block" id="login-btn">
        Iniciar sesión
      </button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ======================== APP ======================== -->
<div id="app">
  <header class="app-header">
    <h2 class="app-title">The Place <span>818</span></h2>
    <div class="user-info">
      <span class="user-name"><?= htmlspecialchars($nombre) ?></span>
      <span class="user-role badge badge-<?= htmlspecialchars($rol) ?>"><?= htmlspecialchars($rol) ?></span>
      <button id="logout-btn" class="btn btn-sm btn-outline">Salir</button>
    </div>
  </header>

  <nav class="app-nav">
    <button class="nav-btn active" data-view="caja">Caja del día</button>
    <?php if ($esDueno): ?>
    <button class="nav-btn" data-view="reportes">Reportes</button>
    <button class="nav-btn" data-view="creditos">Créditos</button>
    <?php endif; ?>
    <?php if ($esAdmin): ?>
    <button class="nav-btn" data-view="usuarios">Usuarios</button>
    <?php endif; ?>
  </nav>

  <main id="view-container" class="view-container">

    <div id="view-caja" class="view active">
      <?php include __DIR__ . '/cajero.php'; ?>
    </div>

    <?php if ($esDueno): ?>
    <div id="view-reportes" class="view">
      <?php include __DIR__ . '/reportes.php'; ?>
    </div>
    <div id="view-creditos" class="view">
      <?php include __DIR__ . '/creditos.php'; ?>
    </div>
    <?php endif; ?>

    <?php if ($esAdmin): ?>
    <div id="view-usuarios" class="view">
      <?php include __DIR__ . '/usuarios.php'; ?>
    </div>
    <?php endif; ?>

  </main>
</div>
<?php endif; ?>

<script src="/public/assets/js/app.js"></script>
<script src="/public/assets/js/cajero.js"></script>
<?php if ($esDueno): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script src="/public/assets/js/reportes.js"></script>
<script src="/public/assets/js/creditos.js"></script>
<?php endif; ?>
<?php if ($esAdmin): ?>
<script src="/public/assets/js/usuarios.js"></script>
<?php endif; ?>

<?php if ($loggedIn): ?>
<script>
// Inicializar módulos al cambiar de vista
(function () {
  let creditosIniciado = false;
  let usuariosIniciado = false;

  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const view = btn.dataset.view;
      if (view === 'creditos' && !creditosIniciado && typeof initCreditos === 'function') {
        creditosIniciado = true;
        initCreditos();
      }
      if (view === 'usuarios' && !usuariosIniciado && typeof initUsuarios === 'function') {
        usuariosIniciado = true;
        initUsuarios();
      }
    });
  });
})();
</script>
<?php endif; ?>

</body>
</html>
