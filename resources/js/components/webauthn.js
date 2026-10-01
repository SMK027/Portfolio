// Clés de sécurité (WebAuthn) : conversion base64url ⇄ binaire et appels au navigateur.

const toBuffer = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const binary = atob(base64 + '='.repeat((4 - (base64.length % 4)) % 4));
    return Uint8Array.from(binary, (c) => c.charCodeAt(0)).buffer;
};

const toBase64Url = (buffer) => btoa(String.fromCharCode(...new Uint8Array(buffer)))
    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

const decodeOptions = ({ publicKey }) => ({
    ...publicKey,
    challenge: toBuffer(publicKey.challenge),
    ...(publicKey.user ? { user: { ...publicKey.user, id: toBuffer(publicKey.user.id) } } : {}),
    ...(publicKey.excludeCredentials ? { excludeCredentials: publicKey.excludeCredentials.map((c) => ({ ...c, id: toBuffer(c.id) })) } : {}),
    ...(publicKey.allowCredentials ? { allowCredentials: publicKey.allowCredentials.map((c) => ({ ...c, id: toBuffer(c.id) })) } : {}),
});

const encodeCredential = (credential) => ({
    id: credential.id,
    type: credential.type,
    response: Object.fromEntries(
        ['clientDataJSON', 'attestationObject', 'authenticatorData', 'signature', 'userHandle']
            .filter((key) => credential.response[key])
            .map((key) => [key, toBase64Url(credential.response[key])]),
    ),
});

const errorMessage = (error) => {
    if (error?.name === 'NotAllowedError') return 'Opération annulée ou délai dépassé.';
    if (error?.name === 'InvalidStateError') return 'Cette clé est déjà enregistrée sur ce compte.';
    if (error?.name === 'SecurityError') return 'Les clés de sécurité exigent une connexion HTTPS.';
    return error?.response?.data?.message ?? error?.response?.data?.errors?.security_key?.[0] ?? error?.message ?? 'Erreur inattendue.';
};

const supported = () => typeof window.PublicKeyCredential !== 'undefined';

/** « Mon compte » : enregistrement d'une nouvelle clé. */
export function securityKeyRegister({ optionsUrl, storeUrl }) {
    return {
        name: '', busy: false, error: '', supported: supported(),
        async register() {
            if (! this.name.trim() || this.busy) return;
            this.busy = true; this.error = '';
            try {
                const { data } = await window.axios.post(optionsUrl);
                const credential = await navigator.credentials.create({ publicKey: decodeOptions(data) });
                const { data: result } = await window.axios.post(storeUrl, { name: this.name.trim(), credential: encodeCredential(credential) });
                window.location.assign(result.redirect);
                window.location.reload();
            } catch (error) {
                this.error = errorMessage(error);
            } finally {
                this.busy = false;
            }
        },
    };
}

/** Connexion : vérification avec l'une des clés du compte. */
export function securityKeyLogin({ optionsUrl, verifyUrl }) {
    return {
        busy: false, error: '', supported: supported(),
        async verify() {
            if (this.busy) return;
            this.busy = true; this.error = '';
            try {
                const { data } = await window.axios.post(optionsUrl);
                const credential = await navigator.credentials.get({ publicKey: decodeOptions(data) });
                const { data: result } = await window.axios.post(verifyUrl, { credential: encodeCredential(credential) });
                window.location.assign(result.redirect);
            } catch (error) {
                this.error = errorMessage(error);
                this.busy = false;
            }
        },
    };
}
