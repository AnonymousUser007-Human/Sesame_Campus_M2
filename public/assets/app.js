// Sesame Campus — UI (aucune logique de sécurité côté client)
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.reveal').forEach((el, i) => {
    el.style.animationDelay = `${Math.min(i * 60, 240)}ms`;
  });

  const forms = document.querySelectorAll('form');
  forms.forEach((form) => {
    form.addEventListener('submit', () => {
      const btn = form.querySelector('button[type="submit"], button:not([type])');
      if (btn && !btn.disabled) {
        btn.dataset.label = btn.textContent || '';
        btn.textContent = 'Traitement…';
        btn.disabled = true;
      }
    });
  });

  // Afficher / masquer le mot de passe (œil / œil barré)
  const eye = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5c-5 0-9.3 3.1-11 7 1.7 3.9 6 7 11 7s9.3-3.1 11-7c-1.7-3.9-6-7-11-7zm0 12a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z"/></svg>';
  const eyeOff = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3.3 2.2 2.2 3.3l3.1 3.1C3.4 7.6 1.7 9.6 1 12c1.7 3.9 6 7 11 7 2 0 3.9-.5 5.5-1.3l3.2 3.2 1.1-1.1L3.3 2.2zM12 17c-3.7 0-6.9-2.1-8.5-5 .7-1.4 1.9-2.7 3.4-3.6l1.7 1.7A5 5 0 0 0 12 17zm0-10c3.7 0 6.9 2.1 8.5 5-.5 1-1.2 1.9-2.1 2.7l1.5 1.5c1.3-1.1 2.3-2.5 2.9-4.2-1.7-3.9-6-7-11-7-1.2 0-2.3.2-3.4.5l1.6 1.6c.6-.1 1.2-.1 1.8-.1zm-1.1 4.3 2.9 2.9A2.5 2.5 0 0 1 10.9 11.3z"/></svg>';

  document.querySelectorAll('input[type="password"]').forEach((input) => {
    if (input.closest('.password-field')) return;

    const wrap = document.createElement('div');
    wrap.className = 'password-field';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'password-toggle';
    btn.setAttribute('aria-label', 'Afficher le mot de passe');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = eye;
    wrap.appendChild(btn);

    btn.addEventListener('click', () => {
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      btn.setAttribute('aria-pressed', show ? 'true' : 'false');
      btn.setAttribute('aria-label', show ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
      btn.innerHTML = show ? eyeOff : eye;
      input.focus();
    });
  });
});
