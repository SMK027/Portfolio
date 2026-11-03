// Terminal web de la machine SSH : xterm.js ⇄ websocket de sandbox-agent.
// Un ticket à usage unique est demandé au portfolio avant chaque connexion.
export default function sandboxTerminal({ ticketUrl, username, password }) {
    let socket = null;
    let terminal = null;
    let fit = null;
    let onResize = null;

    return {
        state: 'idle', // idle | connecting | connected | closed
        message: '',
        username: username ?? '',
        password: password ?? '',

        async connect() {
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
                socket.send(JSON.stringify({ type: 'auth', username: this.username, password: this.password, cols: terminal.cols, rows: terminal.rows }));
            });
            socket.addEventListener('message', (event) => {
                const message = JSON.parse(event.data);
                if (message.type === 'output') {
                    terminal.write(message.data);
                } else if (message.type === 'status' && message.message === 'connected') {
                    this.state = 'connected';
                    this.message = '';
                    terminal.focus();
                } else if (message.type === 'closed') {
                    this.close(message.message);
                }
            });
            socket.addEventListener('close', () => this.state !== 'closed' && this.close(this.state === 'connected' ? 'Session terminée.' : 'Connexion impossible.'));

            terminal.onData((data) => socket?.readyState === WebSocket.OPEN && socket.send(JSON.stringify({ type: 'input', data })));
            terminal.onResize(({ cols, rows }) => socket?.readyState === WebSocket.OPEN && socket.send(JSON.stringify({ type: 'resize', cols, rows })));
            onResize = () => fit?.fit();
            window.addEventListener('resize', onResize);
        },

        disconnect() {
            this.close('Déconnecté.');
        },

        close(message) {
            this.state = 'closed';
            this.message = message;
            if (onResize) {
                window.removeEventListener('resize', onResize);
            }
            socket?.close();
            socket = null;
        },
    };
}
