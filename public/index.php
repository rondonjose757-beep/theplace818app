<?php
declare(strict_types=1);
session_start();

$loggedIn = isset($_SESSION['user_id']);
$rol      = $_SESSION['rol']    ?? '';
$nombre   = $_SESSION['nombre'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="theme-color" content="#e94560" />
  <meta name="apple-mobile-web-app-capable" content="yes" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
  <title>The Place 818 — Control de Caja</title>
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="/public/assets/css/app.css" />
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
    <?php if (in_array($rol, ['administrador', 'dueño'])): ?>
    <button class="nav-btn" data-view="reportes">Reportes</button>
    <button class="nav-btn" data-view="creditos">Créditos</button>
    <?php endif; ?>
    <?php if ($rol === 'administrador'): ?>
    <button class="nav-btn" data-view="usuarios">Usuarios</button>
    <?php endif; ?>
  </nav>

  <main id="view-container" class="view-container">
    <!-- Las vistas se cargan dinámicamente con JS -->
    <div id="view-caja" class="view active">
      <h3>Caja del día</h3>
      <p class="placeholder-text">Módulo en construcción.</p>
    </div>

    <?php if (in_array($rol, ['administrador', 'dueño'])): ?>
    <div id="view-reportes" class="view">
      <h3>Reportes</h3>
      <p class="placeholder-text">Módulo en construcción.</p>
    </div>
    <div id="view-creditos" class="view">
      <h3>Créditos</h3>
      <p class="placeholder-text">Módulo en construcción.</p>
    </div>
    <?php endif; ?>

    <?php if ($rol === 'administrador'): ?>
    <div id="view-usuarios" class="view">
      <h3>Gestión de usuarios</h3>
      <p class="placeholder-text">Módulo en construcción.</p>
    </div>
    <?php endif; ?>
  </main>
</div>
<?php endif; ?>

<script src="/public/assets/js/app.js"></script>
</body>
</html>
