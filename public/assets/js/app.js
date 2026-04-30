'use strict';

/* ===================================================
   The Place 818 — App JS
=================================================== */

// ---- PWA: registrar Service Worker ----
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/sw.js').catch(() => {});
  });
}

// ---- Login ----
const loginForm = document.getElementById('login-form');
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('login-btn');
    const errBox = document.getElementById('login-error');

    btn.disabled = true;
    btn.textContent = 'Iniciando…';
    errBox.classList.add('hidden');

    const body = {
      email:    document.getElementById('email').value.trim(),
      password: document.getElementById('password').value,
    };

    try {
      const res  = await fetch('/api/auth/login.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify(body),
      });
      const data = await res.json();

      if (data.success) {
        window.location.reload();
      } else {
        errBox.textContent = data.error ?? 'Error al iniciar sesión';
        errBox.classList.remove('hidden');
      }
    } catch {
      errBox.textContent = 'No se pudo conectar con el servidor';
      errBox.classList.remove('hidden');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Iniciar sesión';
    }
  });
}

// ---- Logout ----
const logoutBtn = document.getElementById('logout-btn');
if (logoutBtn) {
  logoutBtn.addEventListener('click', async () => {
    await fetch('/api/auth/logout.php');
    window.location.reload();
  });
}

// ---- Navegación entre vistas ----
const navBtns = document.querySelectorAll('.nav-btn');
const views   = document.querySelectorAll('.view');

navBtns.forEach(btn => {
  btn.addEventListener('click', () => {
    const target = btn.dataset.view;

    navBtns.forEach(b => b.classList.remove('active'));
    views.forEach(v => v.classList.remove('active'));

    btn.classList.add('active');
    const view = document.getElementById(`view-${target}`);
    if (view) view.classList.add('active');
  });
});
