// Page d'easter eggs : combinaisons propres des éléments verrouillés et effets visuels.
import { findMatch, isTouchDevice, keysFromText, listenForSequences } from './keys';
import { play } from './effects';

export default function start() {
    const root = document.querySelector('[data-easter-eggs]');
    const locks = [...root.querySelectorAll('[data-egg-lock]')].map((el) => ({ el, ...JSON.parse(el.dataset.eggLock) }));

    const shake = (el) => el.animate([{ transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'none' }], { duration: 250 });
    const tryUnlock = (keys, lock) => window.axios.post(lock.url, { keys })
        .then(() => window.location.reload())
        .catch(() => shake(lock.el));

    listenForSequences(locks, tryUnlock);

    // Mobile uniquement : champ de saisie pour les combinaisons au clavier.
    const form = root.querySelector('[data-egg-input]');
    if (form && isTouchDevice()) {
        const input = form.querySelector('input');
        const error = form.querySelector('[data-egg-input-error]');
        form.hidden = false;
        input.addEventListener('input', () => { error.hidden = true; });
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const keys = keysFromText(input.value);
            const lock = keys.length && window.crypto?.subtle ? await findMatch(keys, locks) : null;
            if (lock) {
                input.blur();
                tryUnlock(keys, lock);
            } else {
                error.hidden = false;
                shake(form);
            }
        });
    }

    root.querySelectorAll('[data-egg-effect]').forEach((card) => {
        card.querySelector('[data-egg-play]')?.addEventListener('click', () => play(card.dataset.eggEffect));
        if (card.dataset.eggId === root.dataset.justUnlocked) play(card.dataset.eggEffect);
    });

    // Élément tout juste déverrouillé : mis en évidence.
    const fresh = root.dataset.justUnlocked && document.getElementById(`egg-${root.dataset.justUnlocked}`);
    if (fresh) {
        fresh.scrollIntoView({ behavior: 'smooth', block: 'center' });
        fresh.animate([{ boxShadow: '0 0 0 4px rgb(99 102 241 / .6)' }, { boxShadow: '0 0 0 0 transparent' }], { duration: 1600 });
    }
}
