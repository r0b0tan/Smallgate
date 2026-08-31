/**
 * The portal is deliberately almost JavaScript free: no framework, no tracking,
 * no third-party scripts. This file only toggles the mobile navigation, which
 * has no sensible server-rendered equivalent.
 */
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-toggle]');

    if (!trigger) {
        return;
    }

    const target = document.getElementById(trigger.dataset.toggle);

    if (!target) {
        return;
    }

    const isHidden = target.hasAttribute('hidden');

    target.toggleAttribute('hidden', !isHidden);
    trigger.setAttribute('aria-expanded', String(isHidden));
});
