/* ==========================================================================
   ClapsLearn ERP — charts
   Declarative: <div data-chart="area|donut|heatmap" data-series='[…]'>.
   Mark specs: 2px lines, ~10% area wash, ≤24px bars, 2px surface gaps and
   rings, hairline solid grid. Every chart has a hover/keyboard tooltip and a
   visually-hidden data table. Categorical palette validated (light + dark).
   ========================================================================== */
(() => {
  const NS = 'http://www.w3.org/2000/svg';
  const css = name => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  const inr = n => '₹' + Math.round(n).toLocaleString('en-IN');
  const inrCompact = n => n >= 1e7 ? `₹${+(n / 1e7).toFixed(1)}Cr` : n >= 1e5 ? `₹${+(n / 1e5).toFixed(1)}L` : n >= 1e3 ? `₹${+(n / 1e3).toFixed(0)}K` : `₹${n}`;
  const FORMATS = { inr, inrCompact, num: n => n.toLocaleString('en-IN') };
  const el = (tag, attrs = {}, parent) => { const n = document.createElementNS(NS, tag); for (const k in attrs) n.setAttribute(k, attrs[k]); parent?.appendChild(n); return n; };

  /* One shared tooltip */
  let tip;
  function showTip(html, x, y) {
    if (!tip) { tip = document.createElement('div'); tip.className = 'viz-tip'; tip.setAttribute('role', 'status'); document.body.appendChild(tip); }
    tip.innerHTML = html;
    tip.dataset.show = 'true';
    const w = tip.offsetWidth, h = tip.offsetHeight;
    let left = x - w / 2, top = y - h - 12;
    left = Math.max(8, Math.min(left, innerWidth - w - 8));
    if (top < 8) top = y + 16;
    tip.style.transform = `translate(${left}px, ${top}px)`;
  }
  const hideTip = () => { if (tip) tip.dataset.show = 'false'; };

  function srTable(host, head, rows) {
    host.querySelector('.viz-table')?.remove();
    const t = document.createElement('table');
    t.className = 'sr-only viz-table';
    t.innerHTML = `<thead><tr>${head.map(h => `<th>${h}</th>`).join('')}</tr></thead><tbody>${rows.map(r => `<tr>${r.map(c => `<td>${c}</td>`).join('')}</tr>`).join('')}</tbody>`;
    host.appendChild(t);
  }

  /* Clean ticks with minimal headroom: pick a 1/2/2.5/5 step, then only as many as the data needs */
  function niceTicks(max, target = 4) {
    const raw = max / target, mag = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = [1, 2, 2.5, 5, 10].map(m => m * mag).find(s => s >= raw);
    const n = Math.max(1, Math.ceil(max / step));
    return Array.from({ length: n + 1 }, (_, i) => i * step);
  }

  /* ---------------- Area (single series → no legend; title names it) ---------------- */
  function area(host) {
    const data = JSON.parse(host.dataset.series);
    const fmt = FORMATS[host.dataset.format || 'num'];
    const axisFmt = FORMATS[host.dataset.axisFormat || host.dataset.format || 'num'];
    const H = +host.dataset.height || 220, padL = 44, padR = 16, padT = 12, padB = 28;
    const draw = () => {
      host.querySelector('svg')?.remove();
      const W = host.clientWidth; if (!W) return;
      const ticks = niceTicks(Math.max(...data.map(d => d.v)) * 1.04);
      const yMax = ticks[ticks.length - 1];
      const x = i => padL + (i * (W - padL - padR)) / (data.length - 1);
      const y = v => padT + (1 - v / yMax) * (H - padT - padB);
      const svg = el('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}`, class: 'viz', role: 'img', 'aria-label': host.getAttribute('aria-label') || '' });
      const defs = el('defs', {}, svg);
      const gid = 'g' + Math.random().toString(36).slice(2, 7);
      const lg = el('linearGradient', { id: gid, x1: 0, x2: 0, y1: 0, y2: 1 }, defs);
      el('stop', { offset: 0, 'stop-color': 'var(--series-1)', 'stop-opacity': .16 }, lg);
      el('stop', { offset: 1, 'stop-color': 'var(--series-1)', 'stop-opacity': 0 }, lg);
      ticks.forEach(t => {
        el('line', { x1: padL, x2: W - padR, y1: y(t), y2: y(t), class: t === 0 ? 'viz-base' : 'viz-grid' }, svg);
        el('text', { x: padL - 8, y: y(t) + 4, 'text-anchor': 'end', class: 'viz-axis' }, svg).textContent = t === 0 ? '0' : axisFmt(t);
      });
      data.forEach((d, i) => el('text', { x: x(i), y: H - 8, 'text-anchor': 'middle', class: 'viz-axis' }, svg).textContent = d.l);
      /* Smooth monotone-ish curve via cardinal spline */
      const pts = data.map((d, i) => [x(i), y(d.v)]);
      const path = pts.reduce((acc, p, i, a) => {
        if (!i) return `M${p[0]},${p[1]}`;
        const p0 = a[i - 2] || a[i - 1], p1 = a[i - 1], p2 = p, p3 = a[i + 1] || p;
        const t = .18;
        return acc + ` C${p1[0] + (p2[0] - p0[0]) * t},${p1[1] + (p2[1] - p0[1]) * t} ${p2[0] - (p3[0] - p1[0]) * t},${p2[1] - (p3[1] - p1[1]) * t} ${p2[0]},${p2[1]}`;
      }, '');
      el('path', { d: `${path} L${x(data.length - 1)},${y(0)} L${padL},${y(0)} Z`, fill: `url(#${gid})`, class: 'viz-area' }, svg);
      const line = el('path', { d: path, class: 'viz-line' }, svg);
      const len = line.getTotalLength(); line.style.setProperty('--len', len);
      /* End marker + direct label on the latest point only */
      const last = pts[pts.length - 1];
      el('circle', { cx: last[0], cy: last[1], r: 4, class: 'viz-dot viz-dot-end' }, svg);
      /* Crosshair */
      const cross = el('line', { y1: padT, y2: H - padB, class: 'viz-cross' }, svg);
      const hover = el('circle', { r: 4, class: 'viz-dot viz-hover' }, svg);
      const hit = el('rect', { x: padL, y: 0, width: W - padL - padR, height: H, fill: 'transparent', tabindex: 0, class: 'viz-hit' }, svg);
      let idx = data.length - 1;
      const focus = (i, cx, cy) => {
        idx = Math.max(0, Math.min(data.length - 1, i));
        const [px, py] = pts[idx];
        cross.setAttribute('x1', px); cross.setAttribute('x2', px); cross.dataset.on = 'true';
        hover.setAttribute('cx', px); hover.setAttribute('cy', py); hover.dataset.on = 'true';
        const r = svg.getBoundingClientRect();
        const prev = data[idx - 1];
        const delta = prev ? ((data[idx].v - prev.v) / prev.v) * 100 : null;
        showTip(`<span class="viz-tip-k">${data[idx].full || data[idx].l}</span><b>${fmt(data[idx].v)}</b>${delta !== null ? `<span class="viz-tip-d ${delta >= 0 ? 'up' : 'down'}">${delta >= 0 ? '↑' : '↓'} ${Math.abs(delta).toFixed(1)}%</span>` : ''}`, cx ?? r.left + px, cy ?? r.top + py);
      };
      hit.addEventListener('pointermove', e => { const r = svg.getBoundingClientRect(); const rel = e.clientX - r.left; focus(Math.round(((rel - padL) / (W - padL - padR)) * (data.length - 1))); });
      const off = () => { cross.dataset.on = 'false'; hover.dataset.on = 'false'; hideTip(); };
      hit.addEventListener('pointerleave', off); hit.addEventListener('blur', off);
      hit.addEventListener('focus', () => focus(idx));
      hit.addEventListener('keydown', e => { if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') { e.preventDefault(); focus(idx + (e.key === 'ArrowRight' ? 1 : -1)); } });
      host.prepend(svg);
    };
    draw();
    new ResizeObserver(() => draw()).observe(host);
    srTable(host, ['Period', 'Value'], data.map(d => [d.full || d.l, fmt(d.v)]));
  }

  /* ---------------- Donut (≤4 slots, legend + direct values) ---------------- */
  function donut(host) {
    const data = JSON.parse(host.dataset.series);
    const fmt = FORMATS[host.dataset.format || 'num'];
    const total = data.reduce((s, d) => s + d.v, 0);
    const S = +host.dataset.size || 168, R = S / 2, w = 18, r = R - w / 2 - 2;
    const C = 2 * Math.PI * r, gap = 2 / r; /* 2px surface gap expressed in radians */
    const svg = el('svg', { width: S, height: S, viewBox: `0 0 ${S} ${S}`, class: 'viz viz-donut', role: 'img', 'aria-label': host.getAttribute('aria-label') || '' });
    el('circle', { cx: R, cy: R, r, class: 'viz-track', 'stroke-width': w, fill: 'none' }, svg);
    let a0 = -Math.PI / 2;
    const segs = data.map((d, i) => {
      const frac = d.v / total, a1 = a0 + frac * 2 * Math.PI;
      const dash = Math.max(0, (a1 - a0 - gap) * r);
      const seg = el('circle', { cx: R, cy: R, r, fill: 'none', 'stroke-width': w, stroke: `var(--series-${i + 1})`, 'stroke-dasharray': `${dash} ${C}`, 'stroke-dashoffset': -(a0 + gap / 2) * r, class: 'viz-seg', tabindex: 0, style: `--d:${i * 80}ms` }, svg);
      a0 = a1;
      return seg;
    });
    const center = document.createElement('div');
    center.className = 'viz-donut-center';
    center.innerHTML = `<b>${fmt(total)}</b><span>${host.dataset.centerLabel || 'Total'}</span>`;
    const wrap = document.createElement('div'); wrap.className = 'viz-donut-wrap'; wrap.style.width = wrap.style.height = S + 'px';
    wrap.append(svg, center);
    const legend = document.createElement('ul'); legend.className = 'viz-legend';
    legend.innerHTML = data.map((d, i) => `<li data-i="${i}"><span class="viz-key" style="background:var(--series-${i + 1})"></span><span class="grow">${d.l}</span><span class="viz-legend-v">${fmt(d.v)}</span><span class="viz-legend-p">${Math.round((d.v / total) * 100)}%</span></li>`).join('');
    host.append(wrap, legend);
    const emph = i => {
      host.dataset.emph = i ?? '';
      segs.forEach((s, j) => s.dataset.dim = i != null && i !== j);
      legend.querySelectorAll('li').forEach((li, j) => li.dataset.dim = i != null && i !== j);
      center.innerHTML = i == null ? `<b>${fmt(total)}</b><span>${host.dataset.centerLabel || 'Total'}</span>` : `<b>${Math.round((data[i].v / total) * 100)}%</b><span>${data[i].l}</span>`;
    };
    segs.forEach((s, i) => {
      s.addEventListener('pointerenter', () => emph(i)); s.addEventListener('focus', () => emph(i));
      s.addEventListener('pointerleave', () => emph(null)); s.addEventListener('blur', () => emph(null));
    });
    legend.querySelectorAll('li').forEach((li, i) => { li.addEventListener('pointerenter', () => emph(i)); li.addEventListener('pointerleave', () => emph(null)); });
    srTable(host, ['Segment', 'Value', 'Share'], data.map(d => [d.l, fmt(d.v), Math.round((d.v / total) * 100) + '%']));
  }

  /* ---------------- Heatmap (sequential, one hue) ---------------- */
  function heatmap(host) {
    const { rows, cols, values } = JSON.parse(host.dataset.series);
    const max = Math.max(...values.flat());
    const steps = 6;
    const grid = document.createElement('div');
    grid.className = 'viz-heat';
    grid.style.gridTemplateColumns = `40px repeat(${cols.length}, minmax(0, 1fr))`;
    grid.innerHTML = '<span></span>' + cols.map((c, i) => `<span class="viz-heat-col">${i % 2 === 0 ? c : ''}</span>`).join('') +
      rows.map((r, ri) => `<span class="viz-heat-row">${r}</span>` + cols.map((c, ci) => {
        const v = values[ri][ci], s = v === 0 ? 0 : Math.ceil((v / max) * (steps - 1));
        return `<button class="viz-cell" style="--s:var(--seq-${s});--d:${(ri + ci) * 12}ms" data-r="${r}" data-c="${c}" data-v="${v}" aria-label="${r} ${c}: ${v} lessons"></button>`;
      }).join('')).join('');
    const legend = document.createElement('div');
    legend.className = 'viz-heat-legend';
    legend.innerHTML = `<span>Fewer</span>${Array.from({ length: steps }, (_, i) => `<i style="background:var(--seq-${i})"></i>`).join('')}<span>More</span>`;
    host.append(grid, legend);
    grid.addEventListener('pointerover', e => { const c = e.target.closest('.viz-cell'); if (!c) return; const b = c.getBoundingClientRect(); showTip(`<span class="viz-tip-k">${c.dataset.r} · ${c.dataset.c}</span><b>${c.dataset.v} lessons</b>`, b.left + b.width / 2, b.top); });
    grid.addEventListener('focusin', e => { const c = e.target.closest('.viz-cell'); if (!c) return; const b = c.getBoundingClientRect(); showTip(`<span class="viz-tip-k">${c.dataset.r} · ${c.dataset.c}</span><b>${c.dataset.v} lessons</b>`, b.left + b.width / 2, b.top); });
    grid.addEventListener('pointerleave', hideTip); grid.addEventListener('focusout', hideTip);
    srTable(host, ['Day', ...cols], rows.map((r, i) => [r, ...values[i]]));
  }

  const KINDS = { area, donut, heatmap };
  const boot = () => document.querySelectorAll('[data-chart]').forEach(h => { if (!h.dataset.ready) { h.dataset.ready = '1'; KINDS[h.dataset.chart]?.(h); } });
  document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
  window.addEventListener('scroll', hideTip, { passive: true });
})();
