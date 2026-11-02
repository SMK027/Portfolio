// Page d'easter eggs : combinaisons propres des éléments verrouillés et effets visuels.
import { listenForSequences } from './keys';
import { play } from './effects';

export default function start() {
    const root = document.querySelector('[data-easter-eggs]');
    const locks = [...root.querySelectorAll('[data-egg-lock]')].map((el) => ({ el, ...JSON.parse(el.dataset.eggLock) }));

    listenForSequences(locks, (keys, lock) => {
        window.axios.post(lock.url, { keys })
            .then(() => window.location.reload())
            .catch(() => lock.el.animate([{ transform: 'translateX(-6px)' }, { transform: 'translateX(6px)' }, { transform: 'none' }], { duration: 250 }));
    });

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
