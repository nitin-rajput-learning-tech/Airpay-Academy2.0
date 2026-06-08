// Render the user's skill-readiness radar on the profile page.
//
// Hand-rendered SVG (no Chart.js) so the page stays fast + works without
// network access. Pulls labels, current[] and required[] from data-*
// attributes on the canvas-shaped <canvas> placeholder; we draw into an
// inline SVG sibling so screen readers can read titles/descs.
//
// @module     local_sentientia_users/skill_radar
// @copyright  2026 Airpay Payment Services
// @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

const polarPoint = (cx, cy, radius, angleRad) => {
    return [
        cx + radius * Math.cos(angleRad - Math.PI / 2),
        cy + radius * Math.sin(angleRad - Math.PI / 2),
    ];
};

const renderRadar = (canvas) => {
    let labels, current, required;
    try {
        labels = JSON.parse(canvas.dataset.radarLabels || '[]');
        current = JSON.parse(canvas.dataset.radarCurrent || '[]');
        required = JSON.parse(canvas.dataset.radarRequired || '[]');
    } catch (e) {
        return;
    }
    if (!labels.length || labels.length !== current.length
        || labels.length !== required.length) return;

    const size = 280;
    const cx = size / 2;
    const cy = size / 2;
    const r = (size / 2) - 30;
    const max = Math.max(5,
        ...required, ...current);
    const ns = 'http://www.w3.org/2000/svg';

    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('width',  String(size));
    svg.setAttribute('height', String(size));
    svg.setAttribute('viewBox', `0 0 ${size} ${size}`);
    svg.setAttribute('role', 'img');

    // Title + desc for AT.
    const title = document.createElementNS(ns, 'title');
    title.textContent = 'Skill readiness radar';
    svg.appendChild(title);
    const desc = document.createElementNS(ns, 'desc');
    desc.textContent = labels.map((l, i) =>
        `${l}: ${current[i]} of ${required[i]}`).join('. ') + '.';
    svg.appendChild(desc);

    // Concentric grid rings.
    for (let lvl = 1; lvl <= max; lvl++) {
        const ring = document.createElementNS(ns, 'polygon');
        const points = labels.map((_, i) => {
            const a = (i / labels.length) * 2 * Math.PI;
            const [x, y] = polarPoint(cx, cy, (lvl / max) * r, a);
            return `${x.toFixed(1)},${y.toFixed(1)}`;
        }).join(' ');
        ring.setAttribute('points', points);
        ring.setAttribute('fill', 'none');
        ring.setAttribute('stroke', '#e2e6ef');
        ring.setAttribute('stroke-width', '1');
        svg.appendChild(ring);
    }

    // Axis lines + labels.
    labels.forEach((label, i) => {
        const a = (i / labels.length) * 2 * Math.PI;
        const [x, y] = polarPoint(cx, cy, r, a);
        const line = document.createElementNS(ns, 'line');
        line.setAttribute('x1', String(cx));
        line.setAttribute('y1', String(cy));
        line.setAttribute('x2', x.toFixed(1));
        line.setAttribute('y2', y.toFixed(1));
        line.setAttribute('stroke', '#e2e6ef');
        line.setAttribute('stroke-width', '1');
        svg.appendChild(line);

        const [lx, ly] = polarPoint(cx, cy, r + 14, a);
        const text = document.createElementNS(ns, 'text');
        text.setAttribute('x', lx.toFixed(1));
        text.setAttribute('y', ly.toFixed(1));
        text.setAttribute('text-anchor', 'middle');
        text.setAttribute('dominant-baseline', 'middle');
        text.setAttribute('font-size', '10');
        text.setAttribute('fill', '#5a6070');
        const trimmed = label.length > 20 ? label.slice(0, 18) + '…' : label;
        text.textContent = trimmed;
        svg.appendChild(text);
    });

    // Required (target) — dashed teal.
    const requiredPoints = labels.map((_, i) => {
        const a = (i / labels.length) * 2 * Math.PI;
        const [x, y] = polarPoint(cx, cy, (required[i] / max) * r, a);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
    const reqPoly = document.createElementNS(ns, 'polygon');
    reqPoly.setAttribute('points', requiredPoints);
    reqPoly.setAttribute('fill', 'none');
    reqPoly.setAttribute('stroke', '#0f7a73');
    reqPoly.setAttribute('stroke-width', '1.5');
    reqPoly.setAttribute('stroke-dasharray', '4 3');
    svg.appendChild(reqPoly);

    // Current — solid blue with low-opacity fill.
    const currentPoints = labels.map((_, i) => {
        const a = (i / labels.length) * 2 * Math.PI;
        const [x, y] = polarPoint(cx, cy, (current[i] / max) * r, a);
        return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
    const curPoly = document.createElementNS(ns, 'polygon');
    curPoly.setAttribute('points', currentPoints);
    curPoly.setAttribute('fill', 'rgba(0,102,167,0.15)');
    curPoly.setAttribute('stroke', '#0066A7');
    curPoly.setAttribute('stroke-width', '2');
    svg.appendChild(curPoly);

    // Replace the canvas element with the SVG (canvas was just a placeholder
    // for layout; we don't need the canvas API for this drawing).
    canvas.replaceWith(svg);
};

export const init = () => {
    const canvas = document.getElementById('ap-skills-radar-canvas');
    if (!canvas) return;
    renderRadar(canvas);
};
