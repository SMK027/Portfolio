// Effets visuels des easter eggs (sans dépendance). Désactivés si l'utilisateur
// préfère réduire les animations, sauf l'inversion des couleurs.

const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function overlay() {
    const canvas = document.createElement('canvas');
    Object.assign(canvas.style, { position: 'fixed', inset: '0', pointerEvents: 'none', zIndex: '9999' });
    canvas.width = window.innerWidth;
    canvas.height = window.innerHeight;
    document.body.appendChild(canvas);
    return canvas;
}

function animate(canvas, duration, frame) {
    const ctx = canvas.getContext('2d');
    const start = performance.now();
    const step = (now) => {
        const t = now - start;
        frame(ctx, t);
        if (t < duration) requestAnimationFrame(step);
        else canvas.remove();
    };
    requestAnimationFrame(step);
}

function confetti() {
    const canvas = overlay();
    const colors = ['#6366f1', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9'];
    const pieces = Array.from({ length: 160 }, () => ({
        x: canvas.width / 2, y: canvas.height / 3,
        vx: (Math.random() - 0.5) * 16, vy: Math.random() * -14 - 4,
        size: 5 + Math.random() * 6, color: colors[Math.floor(Math.random() * colors.length)], spin: Math.random() * 6,
    }));
    animate(canvas, 3500, (ctx, t) => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        for (const p of pieces) {
            p.vy += 0.35; p.vx *= 0.99; p.x += p.vx; p.y += p.vy;
            ctx.save();
            ctx.translate(p.x, p.y);
            ctx.rotate(p.spin * t / 300);
            ctx.fillStyle = p.color;
            ctx.globalAlpha = Math.max(0, 1 - t / 3500);
            ctx.fillRect(-p.size / 2, -p.size / 4, p.size, p.size / 2);
            ctx.restore();
        }
    });
}

function matrix() {
    const canvas = overlay();
    const size = 16;
    const drops = Array.from({ length: Math.ceil(canvas.width / size) }, () => Math.random() * -50);
    const chars = 'アカサタナハマヤラワ0123456789ABCDEF';
    let last = 0;
    animate(canvas, 6000, (ctx, t) => {
        if (t - last < 45) return;
        last = t;
        ctx.fillStyle = 'rgba(0, 0, 0, 0.08)';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = '#22c55e';
        ctx.font = `${size}px monospace`;
        drops.forEach((y, i) => {
            ctx.fillText(chars[Math.floor(Math.random() * chars.length)], i * size, y * size);
            drops[i] = y * size > canvas.height && Math.random() > 0.97 ? 0 : y + 1;
        });
        if (t > 5000) canvas.style.opacity = String(1 - (t - 5000) / 1000);
    });
}

function barrelRoll() {
    document.body.animate([{ transform: 'rotate(0)' }, { transform: 'rotate(360deg)' }], { duration: 1500, easing: 'ease-in-out' });
}

function invert() {
    const html = document.documentElement;
    html.style.transition = 'filter .4s';
    html.style.filter = html.style.filter ? '' : 'invert(1) hue-rotate(180deg)';
}

const EFFECTS = { confetti, matrix, 'barrel-roll': barrelRoll, invert };

export function play(name) {
    if (name !== 'invert' && reduced()) return;
    EFFECTS[name]?.();
}
