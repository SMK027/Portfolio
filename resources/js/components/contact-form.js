/**
 * Formulaire de contact protégé par Google reCAPTCHA v3 :
 * un jeton est demandé au moment de l'envoi puis transmis au serveur.
 */
export default ({ siteKey = null } = {}) => ({
    sending: false,
    captchaError: false,

    async submit(event) {
        const form = event.target;
        if (this.sending) return;
        this.sending = true;
        this.captchaError = false;

        if (!siteKey) {
            form.submit();
            return;
        }

        try {
            const token = await new Promise((resolve, reject) => {
                if (!window.grecaptcha) {
                    reject(new Error('reCAPTCHA indisponible'));
                    return;
                }
                window.grecaptcha.ready(() => {
                    window.grecaptcha.execute(siteKey, { action: 'contact' }).then(resolve, reject);
                });
            });
            form.querySelector('input[name="recaptcha_token"]').value = token;
            form.submit();
        } catch (error) {
            this.sending = false;
            this.captchaError = true;
        }
    },
});
