// Déclencheurs de la page d'easter eggs, présents sur toutes les pages publiques :
// séquence de touches, mot magique, clics répétés sur un élément.
import { listenForSequences } from './keys';

export default function start() {
    const config = JSON.parse(document.getElementById('easter-egg-triggers').textContent);
    let busy = false;

    const open = (payload) => {
        if (busy) return;
        busy = true;
        window.axios.post(config.url, payload)
            .then(({ data }) => { window.location.href = data.url; })
            .catch(() => { busy = false; });
    };

    listenForSequences(config.sequences, (keys) => open({ keys }));

    if (config.click) {
        let count = 0;
        let timer = null;
        document.addEventListener('click', (event) => {
            if (! event.target.closest?.(config.click.selector)) return;
            count += 1;
            clearTimeout(timer);
            timer = setTimeout(() => { count = 0; }, 1500); // clics rapprochés uniquement
            if (count >= config.click.count) {
                event.preventDefault();
                count = 0;
                open({ click: true });
            }
        }, true);
    }
}
