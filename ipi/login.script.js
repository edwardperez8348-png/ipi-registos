// =============================================
// login.script.js
// =============================================

// Mostrar / ocultar password
function togglePassword() {
  const input = document.getElementById('password');
  const btn = document.querySelector('.toggle-pass');
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = '🙈';
  } else {
    input.type = 'password';
    btn.textContent = '👁';
  }
}

// Validação antes de submeter
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('form-login');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    const utilizador = document.getElementById('utilizador').value.trim();
    const password   = document.getElementById('password').value.trim();

    if (!utilizador || !password) {
      e.preventDefault();
    }
  });
});