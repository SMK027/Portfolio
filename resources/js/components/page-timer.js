// Mesure d'audience : temps où la page est réellement visible, et présence en temps réel.
// Un battement part toutes les 30 s tant que la page est visible ; un dernier signal
// (« left ») part quand elle est masquée ou quittée.
const HEARTBEAT_MS = 30000;

export default function startPageTimer() {
    const id = document.body.dataset.pageView;
    const url = document.body.dataset.pageViewUrl;
    if (! id || ! url || ! navigator.sendBeacon) return;

    let total = 0;
    let since = document.visibilityState === 'visible' ? performance.now() : null;
    let timer = null;

    const elapsed = () => Math.round((total + (since !== null ? performance.now() - since : 0)) / 1000);

    const send = (left) => {
        const data = new FormData();
        data.append('id', id);
        data.append('seconds', String(elapsed()));
        if (left) data.append('left', '1');
        navigator.sendBeacon(url, data);
    };

    const startHeartbeat = () => {
        clearInterval(timer);
        timer = setInterval(() => send(false), HEARTBEAT_MS);
    };

    const leave = () => {
        if (since !== null) { total += performance.now() - since; since = null; }
        clearInterval(timer);
        send(true);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            leave();
        } else {
            since = performance.now();
            send(false); // de retour sur la page
            startHeartbeat();
        }
    });
    window.addEventListener('pagehide', leave);

    if (since !== null) startHeartbeat();
}
