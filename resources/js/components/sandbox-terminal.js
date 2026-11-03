// Terminal web de la machine SSH : xterm.js ⇄ websocket de sandbox-agent.
// Un ticket à usage unique est demandé au portfolio avant chaque connexion.

// Barre de touches (mobile) : séquences envoyées au terminal
const KEYS = {
    Échap: '\x1b', Tab: '\t', '↑': '\x1b[A', '↓': '\x1b[B', '←': '\x1b[D', '→': '\x1b[C',
    '|': '|', '~': '~', '/': '/', '-': '-',
};

export default function sandboxTerminal({ ticketUrl, username, password }) {
    let socket = null;
    let terminal = null;
    let fit = null;
    let onResize = null;
    let countdown = null;

    return {
        state: 'idle', // idle | connecting | connected | closed
        message: '',
        username: username ?? '',
        password: password ?? '',
        sessionUser: '',
        keys: Object.keys(KEYS),
        ctrl: false,
        fullscreen: false,
        idleLeft: null, // secondes avant la fermeture pour inactivité
        idleLimit: null,
        sessionEnd: null,

        get warning() {
            if (this.idleLeft !== null && this.idleLeft <= 180) {
                return `Fermeture pour inactivité dans ${Math.ceil(this.idleLeft / 60)} min.`;
            }
            const left = this.sessionEnd ? Math.round((this.sessionEnd - Date.now()) / 1000) : null;
            return left !== null && left <= 300 ? `La session se termine dans ${Math.max(1, Math.ceil(left / 60))} min.` : '';
        },

        async connect(ephemeral = false) {
            this.state = 'connecting';
            this.message = 'Connexion…';

            let url;
            try {
                url = (await window.axios.post(ticketUrl)).data.url;
            } catch (e) {
                this.close(e.response?.status === 429 ? 'Trop de tentatives, réessayez dans une minute.' : 'La machine est indisponible.');
                return;
            }

            const [{ Terminal }, { FitAddon }] = await Promise.all([
                import('@xterm/xterm'), import('@xterm/addon-fit'), import('@xterm/xterm/css/xterm.css'),
            ]);
            terminal?.dispose();
            terminal = new Terminal({
                cursorBlink: true, fontSize: 14, convertEol: false,
                fontFamily: 'ui-monospace, SFMono-Regular, Menlo, Consolas, monospace',
                theme: { background: '#0b1020', foreground: '#e2e8f0', cursor: '#4ade80' },
            });
            fit = new FitAddon();
            terminal.loadAddon(fit);
            await this.$nextTick();
            terminal.open(this.$refs.screen);
            fit.fit();

            socket = new WebSocket(url);
            socket.addEventListener('open', () => {
                socket.send(JSON.stringify(ephemeral
                    ? { type: 'auth', ephemeral: true, cols: terminal.cols, rows: terminal.rows }
                    : { type: 'auth', username: this.username, password: this.password, cols: terminal.cols, rows: terminal.rows }));
            });
            socket.addEventListener('message', (event) => {
                const message = JSON.parse(event.data);
                if (message.type === 'output') {
                    terminal.write(message.data);
                } else if (message.type === 'status' && message.message === 'connected') {
                    this.state = 'connected';
                    this.message = '';
                    terminal.focus();
                } else if (message.type === 'limits') {
                    this.sessionUser = message.username ?? '';
                    this.idleLimit = message.idle_minutes * 60;
                    this.sessionEnd = Date.now() + message.max_minutes * 60000;
                    this.activity();
                    clearInterval(countdown);
                    countdown = setInterval(() => { this.idleLeft = Math.max(0, this.idleLeft - 1); }, 1000);
                } else if (message.type === 'closed') {
                    this.close(message.message);
                }
            });
            socket.addEventListener('close', () => this.state !== 'closed' && this.close(this.state === 'connected' ? 'Session terminée.' : 'Connexion impossible.'));

            terminal.onData((data) => this.send(data));
            terminal.onResize(({ cols, rows }) => socket?.readyState === WebSocket.OPEN && socket.send(JSON.stringify({ type: 'resize', cols, rows })));
            // Ctrl+Maj+C / Ctrl+Maj+V : copier / coller (Ctrl+C reste l'interruption)
            terminal.attachCustomKeyEventHandler((event) => {
                if (event.type === 'keydown' && event.ctrlKey && event.shiftKey && ['C', 'V'].includes(event.key.toUpperCase())) {
                    event.key.toUpperCase() === 'C' ? this.copy() : this.paste();
                    return false;
                }
                return true;
            });
            onResize = () => fit?.fit();
            window.addEventListener('resize', onResize);
        },

        /** Saisie envoyée à la machine (Ctrl de la barre mobile appliqué au caractère suivant). */
        send(data) {
            if (socket?.readyState !== WebSocket.OPEN) {
                return;
            }
            if (this.ctrl && data.length === 1) {
                const code = data.toUpperCase().charCodeAt(0);
                if (code >= 64 && code <= 95) {
                    data = String.fromCharCode(code - 64);
                }
                this.ctrl = false;
            }
            socket.send(JSON.stringify({ type: 'input', data }));
            this.activity();
        },

        key(name) {
            this.send(KEYS[name]);
            terminal?.focus();
        },

        activity() {
            if (this.idleLimit) {
                this.idleLeft = this.idleLimit;
            }
        },

        async copy() {
            const text = terminal?.getSelection();
            if (text) {
                await navigator.clipboard?.writeText(text).catch(() => {});
            }
            terminal?.focus();
        },

        async paste() {
            const text = await navigator.clipboard?.readText().catch(() => '');
            if (text) {
                this.send(text);
            }
            terminal?.focus();
        },

        toggleFullscreen() {
            this.fullscreen = ! this.fullscreen;
            document.documentElement.classList.toggle('overflow-hidden', this.fullscreen);
            this.$nextTick(() => { fit?.fit(); terminal?.focus(); });
        },

        disconnect() {
            this.close('Déconnecté.');
        },

        close(message) {
            this.state = 'closed';
            this.message = message;
            clearInterval(countdown);
            this.idleLeft = this.idleLimit = this.sessionEnd = null;
            if (this.fullscreen) {
                this.toggleFullscreen();
            }
            if (onResize) {
                window.removeEventListener('resize', onResize);
            }
            socket?.close();
            socket = null;
        },
    };
}
