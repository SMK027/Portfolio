// Mesure d'audience : temps où la page est réellement visible, envoyé en la quittant.
export default function startPageTimer() {
    const id = document.body.dataset.pageView;
    const url = document.body.dataset.pageViewUrl;
    if (! id || ! url || ! navigator.sendBeacon) return;

    let total = 0;
    let since = document.visibilityState === 'visible' ? performance.now() : null;

    const send = () => {
        if (since !== null) { total += performance.now() - since; since = null; }
        const seconds = Math.round(total / 1000);
        if (seconds < 1) return;
        const data = new FormData();
        data.append('id', id);
        data.append('seconds', String(seconds));
        navigator.sendBeacon(url, data);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') send();
        else since = performance.now();
    });
    window.addEventListener('pagehide', send);
}
