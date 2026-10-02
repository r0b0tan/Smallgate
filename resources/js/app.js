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
