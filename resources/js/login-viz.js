/**
 * Sign-in illustration (eMotion Dashboard design): visit "threads" flow into a
 * score ring whose Poor / Watch / Good split slowly shifts towards Good.
 * Respects prefers-reduced-motion (draws one still frame).
 */
const COLS = ['#A8A4F7', '#A8A4F7', '#A8A4F7', '#D9CC00', '#E3A048', '#F44336'];

export function mountLoginViz(cv) {
    if (!cv || cv.dataset.vizMounted) return;
    cv.dataset.vizMounted = '1';
    const ctx = cv.getContext('2d');
    const reduce = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    let W = 0, H = 0, raf = 0;
    const t0 = performance.now();
    const fit = () => {
        const r = cv.getBoundingClientRect(), d = Math.min(2, window.devicePixelRatio || 1);
        W = r.width; H = r.height; cv.width = W * d; cv.height = H * d; ctx.setTransform(d, 0, 0, d, 0, 0);
    };
    fit();
    const ro = new ResizeObserver(fit);
    ro.observe(cv);

    let s = 9;
    const rnd = () => { s = (Math.imul(s, 1664525) + 1013904223) >>> 0; return s / 4294967296; };
    const threads = Array.from({ length: 34 }, () => ({ y: rnd(), a: 0.02 + rnd() * 0.06, f: 1 + rnd() * 2.2, ph: rnd() * 6.28, sp: 0.03 + rnd() * 0.05 }));
    const parts = Array.from({ length: 90 }, () => ({ th: Math.floor(rnd() * 34), x: rnd(), v: 0.0006 + rnd() * 0.0012, c: COLS[Math.floor(rnd() * COLS.length)], r: 0.8 + rnd() * 1.6 }));
    const ringR = () => Math.min(W * 0.16, H * 0.22);
    const ringX = () => W - ringR() * 1.9 - 12;
    const path = (th, x, t) => {
        const cx = ringX(), cy = H * 0.5, pull = Math.pow(x, 2.4);
        const y0 = H * (0.08 + th.y * 0.84) + Math.sin(x * th.f * 6.28 + th.ph + t * th.sp) * H * th.a;
        return [x * cx, y0 + (cy - y0) * pull];
    };
    const hx = c => [1, 3, 5].map(i => parseInt(c.slice(i, i + 2), 16));
    const RED = hx('#F44336'), AMB = hx('#E3A048'), GRN = hx('#D9CC00');
    const ease = k => (k < 0.5 ? 2 * k * k : 1 - Math.pow(-2 * k + 2, 2) / 2);
    const K = [[0, [0.72, 0.2, 0.08]], [1.2, [0.72, 0.2, 0.08]], [4.6, [0.3, 0.44, 0.26]], [6.6, [0.08, 0.17, 0.75]], [8.4, [0, 0, 1]], [10.2, [0, 0, 1]], [11, [0.72, 0.2, 0.08]]];

    const draw = now => {
        if (!cv.isConnected) { ro.disconnect(); return; }
        const t = (now - t0) / 1000;
        ctx.clearRect(0, 0, W, H);
        const R0 = ringR(), cx = ringX(), cy = H * 0.5;
        for (let g = 1; g <= 7; g++) {
            const gr = ctx.createLinearGradient(0, 0, 0, H);
            gr.addColorStop(0, 'rgba(255,255,255,0)'); gr.addColorStop(0.5, 'rgba(255,255,255,0.07)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
            ctx.fillStyle = gr; ctx.fillRect(cx * g / 8, 0, 1, H);
        }
        ctx.lineWidth = 1;
        threads.forEach(th => {
            ctx.beginPath();
            for (let k = 0; k <= 60; k++) { const [x, y] = path(th, k / 60, t); k ? ctx.lineTo(x, y) : ctx.moveTo(x, y); }
            ctx.strokeStyle = 'rgba(168,164,247,0.09)'; ctx.stroke();
        });
        parts.forEach(p => {
            if (!reduce) { p.x += p.v * (1 + p.x * 1.5); if (p.x > 1) { p.x = 0; p.th = Math.floor(rnd() * 34); } }
            const [x, y] = path(threads[p.th], p.x, t);
            const fade = Math.min(1, p.x * 6) * (1 - Math.pow(p.x, 8));
            ctx.globalAlpha = 0.85 * fade; ctx.fillStyle = p.c; ctx.beginPath(); ctx.arc(x, y, p.r, 0, 6.283); ctx.fill();
            ctx.globalAlpha = 0.18 * fade; ctx.beginPath(); ctx.arc(x, y, p.r * 4, 0, 6.283); ctx.fill();
        });
        ctx.globalAlpha = 1;
        const glow = ctx.createRadialGradient(cx, cy, 0, cx, cy, R0 * 1.9);
        glow.addColorStop(0, 'rgba(168,164,247,0.28)'); glow.addColorStop(1, 'rgba(168,164,247,0)');
        ctx.fillStyle = glow; ctx.beginPath(); ctx.arc(cx, cy, R0 * 1.9, 0, 6.283); ctx.fill();

        const cyc = reduce ? 9 : t % 11;
        let k = 0; while (k < K.length - 2 && cyc > K[k + 1][0]) k++;
        const kt = ease(Math.max(0, Math.min(1, (cyc - K[k][0]) / ((K[k + 1][0] - K[k][0]) || 1))));
        const sh = K[k][1].map((v, i) => v + (K[k + 1][1][i] - v) * kt);
        const rc = [0, 1, 2].map(i => Math.round(RED[i] * sh[0] + AMB[i] * sh[1] + GRN[i] * sh[2]));
        ctx.lineCap = 'round'; ctx.lineWidth = 5; ctx.globalAlpha = 0.95;
        let aa = -Math.PI / 2 + t * 0.12;
        [[sh[0], '#F44336'], [sh[1], '#E3A048'], [sh[2], '#D9CC00']].forEach(([f, c]) => {
            const len = f * 6.283;
            if (len > 0.12) {
                const gap = f > 0.985 ? 0 : 0.06 * Math.min(1, (1 - f) * 12);
                ctx.strokeStyle = c; ctx.beginPath(); ctx.arc(cx, cy, R0, aa + gap, aa + len - gap); ctx.stroke();
            }
            aa += len;
        });
        const halo = ctx.createRadialGradient(cx, cy, R0 * 0.6, cx, cy, R0 * 1.3);
        halo.addColorStop(0, `rgba(${rc.join(',')},0)`); halo.addColorStop(0.75, `rgba(${rc.join(',')},0.16)`); halo.addColorStop(1, `rgba(${rc.join(',')},0)`);
        ctx.globalAlpha = 1; ctx.fillStyle = halo; ctx.beginPath(); ctx.arc(cx, cy, R0 * 1.3, 0, 6.283); ctx.fill();
        ctx.lineWidth = 1; ctx.strokeStyle = 'rgba(255,255,255,0.12)';
        [1.35, 1.7].forEach((m, i) => { ctx.setLineDash([2, 6]); ctx.lineDashOffset = (i ? 1 : -1) * t * 6; ctx.beginPath(); ctx.arc(cx, cy, R0 * m, 0, 6.283); ctx.stroke(); });
        ctx.setLineDash([]);
        const br = 0.5 + 0.5 * Math.sin(t * 1.6);
        ctx.fillStyle = `rgba(255,255,255,${0.06 + br * 0.05})`; ctx.beginPath(); ctx.arc(cx, cy, R0 * 0.72, 0, 6.283); ctx.fill();
        if (!reduce) raf = requestAnimationFrame(draw);
    };
    raf = requestAnimationFrame(draw);
}

export function mountAll() {
    document.querySelectorAll('canvas[data-login-viz]').forEach(mountLoginViz);
}
