// public/js/auth.js

async function requireAuth(redirectTo = 'login.html') {
  try {
    // cek session lewat endpoint me.php
    await apiFetch('/users/me.php');
    return true;
  } catch (err) {
    // kalau unauthorized / error -> balik ke login
    window.location.href = redirectTo;
    return false;
  }
}
