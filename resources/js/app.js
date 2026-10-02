/**
 * The portal is deliberately almost JavaScript free: no framework, no tracking,
 * no third-party scripts. The account menu is a plain <details> element and
 * works without this file; this only closes it again on a click elsewhere or
 * on Escape, which <details> does not do by itself.
 */
const closeMenus = (except = null) => {
    document.querySelectorAll('details[data-menu][open]').forEach((menu) => {
        if (menu !== except) {
            menu.removeAttribute('open');
        }
    });
};

document.addEventListener('click', (event) => {
    closeMenus(event.target.closest('details[data-menu]'));
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeMenus();
    }
});

/**
 * Show/hide toggle for password fields. The button stays hidden without
 * JavaScript, so the field then simply remains masked. Before the form is
 * sent the field is masked again, so the browser never stores the password
 * as ordinary text input.
 */
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);

    if (!input) {
        return;
    }

    const render = (visible) => {
        input.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));
        button.setAttribute('aria-label', visible ? 'Passwort verbergen' : 'Passwort anzeigen');
        button.querySelector('[data-show]')?.classList.toggle('hidden', visible);
        button.querySelector('[data-hide]')?.classList.toggle('hidden', !visible);
    };

    button.hidden = false;
    button.addEventListener('click', () => render(input.type === 'password'));
    input.form?.addEventListener('submit', () => render(false));
});
