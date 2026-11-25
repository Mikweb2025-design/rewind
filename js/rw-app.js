/* Rewind - app page. Vanilla JS, no build step. */
(function (root) {
  'use strict';
  var doc = root.document;
  var S = root.RW_I18N || {};
  var lang = doc && doc.documentElement ? ((doc.documentElement.getAttribute('lang') || 'en') + '').slice(0, 2) : 'en';
  var STR = S[lang] || S.en || {};
  var SVGNS = 'http://www.w3.org/2000/svg';
  var reduce = root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function t(k, p) {
    var s = STR[k] !== undefined ? STR[k] : ((S.en || {})[k] !== undefined ? S.en[k] : k);
    return p ? s.replace(/\{(\w+)\}/g, function (m, n) { return p[n] !== undefined ? p[n] : m; }) : s;
  }

  function h(tag, attrs, kids) {
    var el = doc.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (k === 'text') { el.textContent = v; } else if (k === 'class') { el.className = v; } else if (k.indexOf('on') === 0) { el.addEventListener(k.slice(2), v); } else if (v !== false && v !== null && v !== undefined) { el.setAttribute(k, v); }
    });
    [].concat(kids || []).forEach(function (c) { if (c !== null && c !== undefined && c !== false) { el.appendChild(typeof c === 'string' ? doc.createTextNode(c) : c); } });
    return el;
  }
  function svg(tag, attrs, kids) {
    var el = doc.createElementNS(SVGNS, tag);
    Object.keys(attrs || {}).forEach(function (k) { el.setAttribute(k, attrs[k]); });
    [].concat(kids || []).forEach(function (c) { if (c) { el.appendChild(c); } });
    return el;
  }
  function clear(el) { while (el.firstChild) { el.removeChild(el.firstChild); } return el; }

  /* ---------- helpers (also exported for tests) ---------- */
  function ago(sec) {
    sec = Math.max(0, Math.round(sec));
    if (sec < 90) { return sec + ' s'; }
    if (sec < 5400) { return Math.round(sec / 60) + ' min'; }
    if (sec < 172800) { return Math.round(sec / 3600) + ' h'; }
    return Math.round(sec / 86400) + ' d';
  }
  function deviceName(ua) {
    ua = ua || '';
    if (/mirall|nextcloud-desktop/i.test(ua)) { return 'Nextcloud Desktop'; }
    if (/android/i.test(ua)) { return 'Android'; }
    if (/ios|iphone|ipad/i.test(ua)) { return 'iPhone / iPad'; }
    if (/mozilla|safari|chrome|firefox/i.test(ua)) { return 'Browser'; }
    return ua.split('/')[0].slice(0, 28) || '?';
  }
  function fmtDate(ts) {
    try { return new Intl.DateTimeFormat(lang, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }).format(new Date(ts * 1000)); } catch (e) { return new Date(ts * 1000).toLocaleString(); }
  }
  function fmtTime(ts) {
    try { return new Intl.DateTimeFormat(lang, { hour: '2-digit', minute: '2-digit' }).format(new Date(ts * 1000)); } catch (e) { return ''; }
  }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }

  /* ---------- api ---------- */
  function token() { return doc.head.getAttribute('data-requesttoken') || (root.OC && root.OC.requestToken) || ''; }
  function url(path) { return root.OC && root.OC.generateUrl ? root.OC.generateUrl('/apps/rewind' + path) : '/index.php/apps/rewind' + path; }
  function api(method, path, body) {
    var init = { method: method, credentials: 'same-origin', headers: { Accept: 'application/json', requesttoken: token(), 'OCS-APIRequest': 'true' } };
    if (body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(body); }
    return root.fetch(url(path), init).then(function (r) { return r.json().then(function (j) { if (!r.ok) { var e = new Error('http ' + r.status); e.data = j; throw e; } return j; }); });
  }
  function toast(msg) {
    var el = h('div', { class: 'rw-toast', role: 'status', text: msg });
    doc.body.appendChild(el);
    root.setTimeout(function () { if (el.parentNode) { el.parentNode.removeChild(el); } }, 3500);
  }

  /* ---------- state ---------- */
  var RANGES = { '6h': 6, '24h': 24, '7d': 168, '30d': 720 };
  var st = { status: null, folder: '/', range: '24h', ts: null, hist: null, preview: null, incident: null, busy: false, picker: null, last: null };
  var ui = {};

  /* ---------- hero ---------- */
  function shieldState(s) {
    if (!s.available || !s.enabled) { return 'off'; }
    if (s.frozen) { return 'frozen'; }
    if (s.incidents.length) { return s.incidents.some(function (i) { return i.status === 'frozen'; }) ? 'frozen' : 'watch'; }
    return 'ok';
  }
  function shieldSvg() {
    var hex = 'M75 12 L128 34 V78 C128 108 106 130 75 142 C44 130 22 108 22 78 V34 Z';
    return svg('svg', { viewBox: '0 0 150 150', 'aria-hidden': 'true' }, [
      svg('circle', { class: 'rw-ring-a', cx: 75, cy: 75, r: 70, fill: 'none', stroke: 'currentColor', 'stroke-width': 1.5, 'stroke-dasharray': '3 9', opacity: .55 }),
      svg('circle', { class: 'rw-ring-b', cx: 75, cy: 75, r: 62, fill: 'none', stroke: 'currentColor', 'stroke-width': 1, 'stroke-dasharray': '40 14', opacity: .3 }),
      svg('g', { class: 'rw-core' }, [
        svg('path', { d: hex, fill: 'currentColor', opacity: .14 }),
        svg('path', { d: hex, fill: 'none', stroke: 'currentColor', 'stroke-width': 3, 'stroke-linejoin': 'round' }),
        svg('circle', { cx: 75, cy: 78, r: 22, fill: 'none', stroke: 'currentColor', 'stroke-width': 3 }),
        svg('path', { d: 'M75 64 V78 L66 84', fill: 'none', stroke: 'currentColor', 'stroke-width': 3.5, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' }),
        svg('path', { d: 'M56 64 L52 56 L61 55', fill: 'none', stroke: 'currentColor', 'stroke-width': 3, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })
      ])
    ]);
  }
  function heroEl(s) {
    var state = shieldState(s);
    var titles = { ok: 'shield_ok', watch: 'shield_watch', frozen: 'shield_frozen', alert: 'shield_alert', off: 'shield_off' };
    var sub = !s.available ? t('unavailable') : (!s.enabled ? t('shield_off_sub') : t('shield_ok_sub'));
    var from = s.journalFrom;
    var chips = [];
    if (s.available) {
      chips.push(h('span', { class: 'rw-pill', text: from ? t('back_to', { date: fmtDate(from) }) : t('history_new') }));
      if (ui.events24 !== undefined) { chips.push(h('span', { class: 'rw-pill', text: t('events24', { n: ui.events24 }) })); }
    }
    if (s.frozen) {
      chips.push(h('button', { class: 'rw-btn rw-btn-light', type: 'button', text: '🔓 ' + t('unfreeze'), onclick: function () { if (root.confirm(t('unfreeze_confirm'))) { api('POST', '/api/unfreeze', {}).then(function () { reload(false); }); } } }));
    }
    var wrap = h('section', { class: 'rw-hero rw-state-' + state, 'aria-live': 'polite' }, [
      h('div', { class: 'rw-shield', style: 'color: var(--rw-accent)' }, [shieldSvg()]),
      h('div', { class: 'rw-hero-text' }, [h('h2', null, [h('span', { class: 'rw-dot' }), t(titles[state])]), h('p', { text: sub }), h('div', { class: 'rw-chips' }, chips)])
    ]);
    return wrap;
  }

  /* ---------- incidents ---------- */
  function reasonList(r) { return h('div', { class: 'rw-reasons' }, (r || []).map(function (x) { return h('span', { text: t('reason_' + x) }); })); }
  function incidentEl(i) {
    var frozen = i.status === 'frozen';
    var now = st.status.now;
    return h('section', { class: 'rw-incident' + (frozen ? '' : ' rw-watching'), role: 'alert' }, [
      h('div', { class: 'rw-i-text' }, [
        h('strong', { text: t('incident_title') + ' · ' + (frozen ? t('incident_frozen') : t('incident_watch')) }),
        h('span', { text: t('incident_text', { n: i.files, device: deviceName(i.ua) || i.ip, score: i.score }) + ' · ' + t('incident_when', { when: t('ago', { x: ago(now - i.started) }) }) }),
        reasonList(i.reasons)
      ]),
      h('div', { class: 'rw-i-actions' }, [
        h('button', { class: 'rw-btn rw-btn-light', type: 'button', text: t('rewind_before'), onclick: function () { chooseIncident(i); } }),
        frozen ? h('button', { class: 'rw-btn rw-btn-ghost', type: 'button', text: t('unfreeze'), onclick: function () { if (root.confirm(t('unfreeze_confirm'))) { api('POST', '/api/unfreeze', {}).then(reload); } } }) : null,
        h('button', { class: 'rw-btn rw-btn-ghost', type: 'button', text: t('false_alarm'), onclick: function () { api('POST', '/api/incident/' + i.id + '/dismiss', {}).then(reload); } })
      ])
    ]);
  }
  function chooseIncident(i) {
    st.incident = i; st.folder = '/'; st.range = i.started < st.status.now - 24 * 3600 ? '7d' : (i.started < st.status.now - 3600 ? '24h' : '6h');
    st.ts = i.started - 1; st.preview = null;
    refreshMachine().then(function () { ui.machine.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); doPreview(); });
  }

  /* ---------- dial ---------- */
  function buildDial() {
    var g = [];
    g.push(svg('circle', { cx: 125, cy: 125, r: 118, fill: 'rgba(255,255,255,.03)', stroke: 'rgba(255,255,255,.12)', 'stroke-width': 1.5 }));
    g.push(svg('circle', { cx: 125, cy: 125, r: 104, fill: 'none', stroke: 'rgba(255,255,255,.08)', 'stroke-width': 10 }));
    ui.arc = svg('circle', { class: 'rw-arc', cx: 125, cy: 125, r: 104, fill: 'none', stroke: 'url(#rw-grad)', 'stroke-width': 10, 'stroke-linecap': 'round', 'stroke-dasharray': '653.45', 'stroke-dashoffset': '653.45', transform: 'rotate(-90 125 125) scale(1 -1) translate(0 -250)' });
    g.push(ui.arc);
    for (var i = 0; i < 60; i++) {
      var a = i * 6 * Math.PI / 180, big = i % 5 === 0, r1 = big ? 86 : 91, r2 = 95;
      g.push(svg('line', { x1: 125 + Math.sin(a) * r1, y1: 125 - Math.cos(a) * r1, x2: 125 + Math.sin(a) * r2, y2: 125 - Math.cos(a) * r2, stroke: big ? 'rgba(255,255,255,.55)' : 'rgba(255,255,255,.2)', 'stroke-width': big ? 2 : 1 }));
    }
    ui.handH = svg('line', { class: 'rw-hand rw-hand-h', x1: 125, y1: 135, x2: 125, y2: 82, stroke: '#e9eeff', 'stroke-width': 5, 'stroke-linecap': 'round' });
    ui.handM = svg('line', { class: 'rw-hand rw-hand-m', x1: 125, y1: 138, x2: 125, y2: 58, stroke: '#5ad2ff', 'stroke-width': 3, 'stroke-linecap': 'round' });
    g.push(ui.handH, ui.handM, svg('circle', { cx: 125, cy: 125, r: 6, fill: '#8b7bff' }));
    var defs = svg('defs', null, [svg('linearGradient', { id: 'rw-grad', x1: 0, y1: 0, x2: 1, y2: 1 }, [svg('stop', { offset: '0', 'stop-color': '#5ad2ff' }), svg('stop', { offset: '1', 'stop-color': '#8b7bff' })])]);
    ui.dialTime = h('b', { text: '--:--' }); ui.dialSub = h('small', { text: '' });
    return h('div', { class: 'rw-dial' }, [svg('svg', { viewBox: '0 0 250 250', 'aria-hidden': 'true' }, [defs].concat(g)), h('div', { class: 'rw-dial-center' }, [ui.dialTime, ui.dialSub])]);
  }
  function updateDial() {
    if (!ui.handH || st.ts === null) { return; }
    var d = new Date(st.ts * 1000);
    ui.handH.style.transform = 'rotate(' + (((d.getHours() % 12) + d.getMinutes() / 60) * 30) + 'deg)';
    ui.handM.style.transform = 'rotate(' + (d.getMinutes() * 6 + d.getSeconds() * .1) + 'deg)';
    var span = Math.max(1, st.status.now - (st.hist ? st.hist.from : st.status.now - RANGES[st.range] * 3600));
    var frac = clamp((st.status.now - st.ts) / span, 0, 1);
    ui.arc.setAttribute('stroke-dashoffset', String(653.45 * (1 - frac)));
    ui.dialTime.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    ui.dialSub.textContent = '−' + ago(st.status.now - st.ts);
  }

  /* ---------- timeline ---------- */
  function drawTimeline() {
    var cv = ui.canvas; if (!cv) { return; }
    var w = cv.clientWidth, hgt = cv.clientHeight, dpr = root.devicePixelRatio || 1;
    if (cv.width !== Math.round(w * dpr)) { cv.width = Math.round(w * dpr); cv.height = Math.round(hgt * dpr); }
    var c = cv.getContext('2d'); c.setTransform(dpr, 0, 0, dpr, 0, 0); c.clearRect(0, 0, w, hgt);
    var H = st.hist; if (!H) { return; }
    var from = H.from, to = H.to, span = Math.max(1, to - from), plotH = hgt - 26;
    var max = 1; H.buckets.forEach(function (b) { if (b[1] > max) { max = b[1]; } });
    // grid
    c.strokeStyle = 'rgba(255,255,255,.07)'; c.lineWidth = 1;
    for (var g = 1; g < 4; g++) { var gy = Math.round(plotH * g / 4) + .5; c.beginPath(); c.moveTo(0, gy); c.lineTo(w, gy); c.stroke(); }
    var bw = Math.max(2, (w / H.buckets.length) - 1.5);
    H.buckets.forEach(function (b) {
      if (b[1] === 0) { return; }
      var x = (b[0] - from) / span * w, bh = Math.max(3, Math.sqrt(b[1] / max) * (plotH - 8));
      var bad = b[2] > 0;
      var grad = c.createLinearGradient(0, plotH - bh, 0, plotH);
      grad.addColorStop(0, bad ? '#ff4d6d' : '#8b7bff'); grad.addColorStop(1, bad ? 'rgba(255,77,109,.35)' : 'rgba(90,210,255,.45)');
      c.fillStyle = grad; c.beginPath();
      if (c.roundRect) { c.roundRect(x, plotH - bh, bw, bh, 2); } else { c.rect(x, plotH - bh, bw, bh); }
      c.fill();
    });
    // labels
    c.fillStyle = 'rgba(233,238,255,.55)'; c.font = '11px system-ui, sans-serif'; c.textBaseline = 'alphabetic';
    [[0, 'left'], [.5, 'center'], [1, 'right']].forEach(function (p) {
      c.textAlign = p[1]; var ts = from + span * p[0]; c.fillText(RANGES[st.range] > 30 ? fmtDate(ts).replace(/,.*$/, '') + ' ' + fmtTime(ts) : fmtTime(ts), clamp(p[0] * w, 4, w - 4), hgt - 8);
    });
    // cursor
    if (st.ts !== null) {
      var cx = clamp((st.ts - from) / span * w, 0, w);
      var sh = c.createLinearGradient(cx - 18, 0, cx + 18, 0);
      sh.addColorStop(0, 'rgba(90,210,255,0)'); sh.addColorStop(.5, 'rgba(90,210,255,.22)'); sh.addColorStop(1, 'rgba(90,210,255,0)');
      c.fillStyle = sh; c.fillRect(cx - 18, 0, 36, plotH);
      c.strokeStyle = '#5ad2ff'; c.lineWidth = 2; c.shadowColor = '#5ad2ff'; c.shadowBlur = 12; c.beginPath(); c.moveTo(cx, 0); c.lineTo(cx, plotH); c.stroke(); c.shadowBlur = 0;
      c.fillStyle = '#fff'; c.beginPath(); c.arc(cx, plotH / 2, 7, 0, Math.PI * 2); c.fill(); c.strokeStyle = '#5ad2ff'; c.lineWidth = 3; c.stroke();
      // dim the part that will be undone (after the cursor)
      c.fillStyle = 'rgba(255,77,109,.06)'; c.fillRect(cx, 0, w - cx, plotH);
    }
  }
  function tsFromEvent(ev) {
    var r = ui.canvas.getBoundingClientRect(), f = clamp((ev.clientX - r.left) / r.width, 0, 1);
    return Math.round(st.hist.from + f * (st.hist.to - st.hist.from));
  }
  function setTs(ts, keepPreview) {
    st.ts = clamp(ts, st.hist ? st.hist.from : ts, st.status.now - 1);
    if (!keepPreview) { st.preview = null; renderPreview(); }
    updateReadout(); updateDial(); drawTimeline();
  }
  function bindTimeline() {
    var drag = false;
    ui.canvas.addEventListener('pointerdown', function (e) { if (!st.hist) { return; } drag = true; ui.canvas.setPointerCapture(e.pointerId); setTs(tsFromEvent(e)); });
    ui.canvas.addEventListener('pointermove', function (e) {
      if (!st.hist) { return; }
      var ts = tsFromEvent(e);
      if (drag) { setTs(ts); }
      var r = ui.canvas.getBoundingClientRect(); ui.tip.style.left = clamp(e.clientX - r.left, 40, r.width - 40) + 'px'; ui.tip.textContent = fmtDate(ts); ui.tip.style.opacity = 1;
    });
    ui.canvas.addEventListener('pointerleave', function () { ui.tip.style.opacity = 0; });
    ui.canvas.addEventListener('pointerup', function () { drag = false; });
    ui.canvas.addEventListener('keydown', function (e) {
      var step = st.hist ? st.hist.bucket : 600;
      if (e.key === 'ArrowLeft') { setTs(st.ts - step); e.preventDefault(); } else if (e.key === 'ArrowRight') { setTs(st.ts + step); e.preventDefault(); } else if (e.key === 'Enter') { doPreview(); }
    });
    root.addEventListener('resize', drawTimeline);
  }
  function updateReadout() {
    if (!ui.readout || st.ts === null) { return; }
    clear(ui.readout).appendChild(h('span', { text: t('go_to') }));
    ui.readout.appendChild(h('b', { text: fmtDate(st.ts) }));
    ui.readout.appendChild(h('span', { text: t('ago', { x: ago(st.status.now - st.ts) }) }));
  }

  /* ---------- machine card ---------- */
  function folderLabel() { return st.folder === '/' ? t('all_files') : st.folder; }
  function buildMachine() {
    ui.folderBtn = h('button', { class: 'rw-folder', type: 'button', 'aria-label': t('folder'), onclick: togglePicker });
    ui.pickerBox = h('div', {});
    ui.readout = h('div', { class: 'rw-readout' });
    ui.canvas = h('canvas', { class: 'rw-canvas', tabindex: '0', role: 'slider', 'aria-label': t('machine_title'), 'aria-valuetext': '' });
    ui.tip = h('div', { class: 'rw-tip' });
    ui.rangeRow = h('div', { class: 'rw-bar' });
    ui.quickRow = h('div', { class: 'rw-row' });
    ui.previewBox = h('div', {});
    ui.dial = buildDial();
    ui.machine = h('section', { class: 'rw-machine', id: 'rw-machine' }, [
      h('h3', { text: t('machine_title') }), h('p', { class: 'rw-sub', text: t('machine_sub') }),
      h('div', { class: 'rw-bar' }, [h('span', { class: 'rw-muted', style: 'color: var(--rw-ink2)', text: t('folder') + ':' }), ui.folderBtn, h('span', { class: 'rw-spacer' }), ui.rangeRow]),
      ui.pickerBox,
      h('div', { class: 'rw-grid' }, [ui.dial, h('div', {}, [ui.readout, h('div', { class: 'rw-canvas-wrap' }, [ui.canvas, ui.tip]), h('p', { class: 'rw-hint', text: t('activity_hint') }), ui.quickRow])]),
      ui.previewBox
    ]);
    bindTimeline();
    return ui.machine;
  }
  function renderRange() {
    clear(ui.rangeRow);
    Object.keys(RANGES).forEach(function (k) {
      ui.rangeRow.appendChild(h('button', { class: 'rw-chip', type: 'button', 'aria-pressed': st.range === k ? 'true' : 'false', text: t('range_' + k), onclick: function () { st.range = k; st.preview = null; refreshMachine(); } }));
    });
  }
  function renderQuick() {
    clear(ui.quickRow);
    var now = st.status.now, items = [];
    if (st.incident) { items.push(['quick_attack', st.incident.started - 1]); }
    var d = new Date(now * 1000); d.setHours(0, 0, 0, 0);
    items.push(['quick_hour', now - 3600], ['quick_yesterday', Math.round(d.getTime() / 1000) - 12 * 3600], ['quick_week', now - 7 * 86400]);
    items.forEach(function (it) {
      ui.quickRow.appendChild(h('button', { class: 'rw-chip', type: 'button', text: t(it[0]), onclick: function () {
        var need = Math.ceil((now - it[1]) / 3600) + 1;
        if (need > RANGES[st.range]) { st.range = need <= 24 ? '24h' : (need <= 168 ? '7d' : '30d'); st.ts = it[1]; refreshMachine(); } else { setTs(it[1]); }
      } }));
    });
  }
  function refreshMachine() {
    ui.folderBtn.textContent = '📁 ' + folderLabel();
    renderRange(); renderQuick();
    var qs = '?folder=' + encodeURIComponent(st.folder) + '&hours=' + RANGES[st.range];
    return api('GET', '/api/histogram' + qs).then(function (hist) {
      st.hist = hist;
      var def = st.ts === null ? st.status.now - 3600 : st.ts;
      st.ts = clamp(def, hist.from, st.status.now - 1);
      var any = hist.buckets.some(function (b) { return b[1] > 0; });
      ui.canvas.setAttribute('aria-valuetext', fmtDate(st.ts));
      updateReadout(); updateDial(); drawTimeline(); renderPreview();
      ui.canvas.parentNode.title = any ? '' : t('no_activity');
      loadActivity();
    }).catch(function () { toast(t('err')); });
  }

  /* ---------- folder picker ---------- */
  function togglePicker() { if (ui.pickerBox.firstChild) { clear(ui.pickerBox); return; } loadPicker('/'); }
  function loadPicker(path) {
    api('GET', '/api/folders?path=' + encodeURIComponent(path)).then(function (r) {
      var parent = r.path.replace(/\/[^/]+$/, '') || '/';
      clear(ui.pickerBox).appendChild(h('div', { class: 'rw-picker' }, [
        h('div', { class: 'rw-bar', style: 'margin:0' }, [
          r.path !== '/' ? h('button', { class: 'rw-chip', type: 'button', text: '↑ ' + t('up'), onclick: function () { loadPicker(parent); } }) : null,
          h('strong', { text: r.path === '/' ? t('all_files') : r.path }), h('span', { class: 'rw-spacer' }),
          h('button', { class: 'rw-btn rw-btn-light', type: 'button', text: t('use_folder'), onclick: function () { st.folder = r.path; st.preview = null; clear(ui.pickerBox); refreshMachine(); } })
        ]),
        h('ul', {}, r.folders.map(function (f) { return h('li', {}, [h('button', { type: 'button', text: '📁 ' + f.name, onclick: function () { loadPicker(f.path); } })]); }))
      ]));
    });
  }

  /* ---------- preview ---------- */
  function countUp(el, to) {
    if (reduce || to === 0) { el.textContent = to; return; }
    var t0 = root.performance.now(), dur = 700;
    (function tick(now) { var p = clamp((now - t0) / dur, 0, 1); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) { root.requestAnimationFrame(tick); } }(t0));
  }
  var TILE = { rollback: ['p_rollback', '#5ad2ff', '#8b7bff'], restore: ['p_restore', '#2ee6a6', '#5ad2ff'], trash: ['p_trash', '#ffb547', '#ff4d6d'], rename: ['p_rename', '#8b7bff', '#ff7ad9'] };
  var ICON = { rollback: '↶', restore: '♻', trash: '🗑', rename: '✎' };
  function doPreview() {
    if (st.busy) { return; }
    clear(ui.previewBox).appendChild(h('p', { class: 'rw-hint', text: t('previewing') }));
    api('POST', '/api/rewind/preview', { folder: st.folder, ts: st.ts }).then(function (p) { st.preview = p; renderPreview(); }).catch(function () { toast(t('err')); renderPreview(); });
  }
  function renderPreview() {
    clear(ui.previewBox);
    var p = st.preview;
    if (!p) {
      ui.previewBox.appendChild(h('div', { class: 'rw-go-row' }, [h('button', { class: 'rw-btn rw-btn-go', type: 'button', text: t('preview'), onclick: doPreview })]));
      return;
    }
    if (p.total === 0) { ui.previewBox.appendChild(h('div', { class: 'rw-preview' }, [h('p', { text: '✓ ' + t('nothing') })])); return; }
    var tiles = h('div', { class: 'rw-tiles' }), nums = [];
    Object.keys(TILE).forEach(function (k) {
      if (!p.counts[k]) { return; }
      var n = h('b', { text: '0' }); nums.push([n, p.counts[k]]);
      tiles.appendChild(h('div', { class: 'rw-tile', style: '--c1:' + TILE[k][1] + ';--c2:' + TILE[k][2] }, [n, h('span', { text: t(TILE[k][0]) })]));
    });
    var steps = h('ul', { class: 'rw-steps' }, p.steps.slice(0, 40).map(function (s) {
      return h('li', {}, [h('span', { class: 'rw-tag', text: ICON[s.type] + ' ' + s.type }), h('code', { text: s.path || (s.from + ' → ' + s.to) }), s.status !== 'ok' ? h('span', { class: 'rw-tag rw-tag-bad', text: s.status }) : null]);
    }));
    var box = h('div', { class: 'rw-preview' }, [tiles, p.problems ? h('div', { class: 'rw-warn', text: '⚠ ' + t('problems', { n: p.problems }) }) : null, steps,
      h('div', { class: 'rw-go-row' }, [h('button', { class: 'rw-btn rw-btn-go', type: 'button', text: '⏪ ' + t('rewind_now'), onclick: doRewind }), h('button', { class: 'rw-btn rw-btn-ghost', type: 'button', style: 'color:var(--rw-ink)', text: t('cancel'), onclick: function () { st.preview = null; renderPreview(); } })])]);
    ui.previewBox.appendChild(box);
    nums.forEach(function (x) { countUp(x[0], x[1]); });
  }

  /* ---------- rewind animation ---------- */
  function startFx(msg, onClose) {
    var cv = h('canvas', {}), title = h('h3', { text: msg }), sub = h('p', { text: fmtDate(st.ts) });
    var clock = svg('svg', { class: 'rw-bigclock', viewBox: '0 0 120 120', 'aria-hidden': 'true' }, [
      svg('circle', { cx: 60, cy: 60, r: 54, fill: 'none', stroke: '#5ad2ff', 'stroke-width': 3, 'stroke-dasharray': '6 8' }),
      svg('line', { class: 'rw-hand-m', x1: 60, y1: 60, x2: 60, y2: 18, stroke: '#5ad2ff', 'stroke-width': 4, 'stroke-linecap': 'round', style: 'transform-origin:60px 60px;animation:rw-spin .8s linear infinite reverse' }),
      svg('line', { x1: 60, y1: 60, x2: 60, y2: 34, stroke: '#fff', 'stroke-width': 5, 'stroke-linecap': 'round', style: 'transform-origin:60px 60px;animation:rw-spin 9.6s linear infinite reverse' })
    ]);
    var ov = h('div', { class: 'rw-overlay', role: 'status' }, [cv, clock, title, sub]);
    ui.machine.appendChild(ov); ui.dial.classList.add('rw-spinning');
    var c = cv.getContext('2d'), W, H, parts = [], alive = true, mode = 'rewind', burst = [];
    function size() { W = cv.width = ov.clientWidth; H = cv.height = ov.clientHeight; }
    size();
    for (var i = 0; i < 70; i++) { parts.push({ x: Math.random() * W, y: Math.random() * H, v: 6 + Math.random() * 16, s: 3 + Math.random() * 7, a: .25 + Math.random() * .6, hue: Math.random() < .5 ? 190 : 255 }); }
    (function frame() {
      if (!alive) { return; }
      c.clearRect(0, 0, W, H);
      if (mode === 'rewind') {
        parts.forEach(function (p) {
          p.x -= p.v; if (p.x < -40) { p.x = W + 20; p.y = Math.random() * H; }
          var g = c.createLinearGradient(p.x, 0, p.x + p.v * 5, 0); g.addColorStop(0, 'hsla(' + p.hue + ',90%,65%,' + p.a + ')'); g.addColorStop(1, 'hsla(' + p.hue + ',90%,65%,0)');
          c.fillStyle = g; c.fillRect(p.x, p.y, p.v * 5, p.s * .5); c.fillStyle = 'hsla(' + p.hue + ',90%,75%,' + p.a + ')'; c.fillRect(p.x, p.y - p.s / 2, p.s, p.s);
        });
      } else {
        burst.forEach(function (b) { b.x += b.vx; b.y += b.vy; b.vy += .12; b.life -= .016; c.globalAlpha = Math.max(0, b.life); c.fillStyle = 'hsl(' + b.h + ',90%,65%)'; c.fillRect(b.x, b.y, b.s, b.s * .6); });
        c.globalAlpha = 1;
      }
      root.requestAnimationFrame(frame);
    }());
    return {
      done: function (ok, text, detail, undoFn) {
        mode = 'done'; ui.dial.classList.remove('rw-spinning');
        for (var k = 0; k < 90; k++) { var a = Math.random() * Math.PI * 2, sp = 3 + Math.random() * 8; burst.push({ x: W / 2, y: H / 2 - 20, vx: Math.cos(a) * sp, vy: Math.sin(a) * sp - 4, s: 5 + Math.random() * 7, life: 1.4, h: ok ? 140 + Math.random() * 110 : 20 }); }
        clear(ov).appendChild(cv);
        ov.appendChild(svg('svg', { class: 'rw-success-ring', viewBox: '0 0 120 120', 'aria-hidden': 'true' }, [svg('circle', { cx: 60, cy: 60, r: 54, fill: 'none', stroke: ok ? '#2ee6a6' : '#ffb547', 'stroke-width': 5, 'stroke-linecap': 'round', transform: 'rotate(-90 60 60)' }), svg('path', { d: 'M36 62 L54 80 L86 42', fill: 'none', stroke: '#fff', 'stroke-width': 7, 'stroke-linecap': 'round', 'stroke-linejoin': 'round' })]));
        ov.appendChild(h('h3', { text: text })); ov.appendChild(h('p', { text: detail }));
        ov.appendChild(h('div', { class: 'rw-go-row', style: 'justify-content:center' }, [
          undoFn ? h('button', { class: 'rw-btn rw-btn-ghost', type: 'button', text: '↩ ' + t('undo'), onclick: function () { stop(); undoFn(); } }) : null,
          h('button', { class: 'rw-btn rw-btn-light', type: 'button', text: 'OK', onclick: stop })
        ]));
        root.setTimeout(function () { burst = []; }, 2200);
      },
      stop: stop
    };
    function stop() { var wasDone = mode === 'done'; alive = false; ui.dial.classList.remove('rw-spinning'); if (ov.parentNode) { ov.parentNode.removeChild(ov); } if (wasDone && onClose) { var f = onClose; onClose = null; f(); } }
  }
  function doRewind() {
    if (st.busy) { return; }
    st.busy = true;
    var fx = startFx(t('rewind_running'), function () { st.preview = null; reload(true); }), t0 = Date.now(), inc = st.incident ? st.incident.id : null;
    var body = { folder: st.folder, ts: st.ts }; if (inc) { body.incident = inc; }
    api('POST', '/api/rewind', body).then(function (r) {
      var wait = Math.max(0, 2400 - (Date.now() - t0));
      root.setTimeout(function () {
        var failed = (r.failed || []).length;
        fx.done(failed === 0, t('done_title'), failed ? t('done_partial', { n: r.done, m: failed }) : t('done_text', { n: r.done }), function () { doUndo(r.op); });
        st.busy = false; st.incident = null;
      }, wait);
    }).catch(function () { fx.stop(); st.busy = false; toast(t('err')); });
  }
  function doUndo(op) {
    st.busy = true;
    var fx = startFx(t('rewind_running'), function () { reload(true); });
    api('POST', '/api/rewind/undo', { op: op }).then(function (r) { root.setTimeout(function () { fx.done(true, t('undone'), t('done_text', { n: r.done }), null); st.busy = false; }, 1500); }).catch(function () { fx.stop(); st.busy = false; toast(t('err')); });
  }

  /* ---------- activity ---------- */
  var ACT = { create: ['+', '#2e9f6b'], write: ['✎', '#5a6fd6'], rename: ['⇄', '#8b7bff'], delete: ['✕', '#d6584f'], copy: ['⧉', '#3aa0c9'] };
  function loadActivity() {
    return api('GET', '/api/activity?folder=' + encodeURIComponent(st.folder) + '&days=3').then(function (r) {
      var items = r.items.slice(0, 40), now = st.status.now;
      ui.events24 = r.items.reduce(function (a, i) { return a + (now - i.ts < 86400 ? i.count : 0); }, 0);
      var list = items.length ? h('ul', { class: 'rw-feed' }, items.map(function (i) {
        var a = ACT[i.action] || ['•', '#5a6a90'];
        return h('li', { class: (i.flagged ? 'rw-flag ' : '') + (i.rewind ? 'rw-rewindrow' : '') }, [
          h('span', { class: 'rw-ico', style: '--ic:' + a[1], text: a[0], 'aria-hidden': 'true' }),
          h('div', { class: 'rw-main' }, [h('b', { text: t('files_n', { n: i.count }) + ' ' + t('act_' + i.action) + (i.flagged ? ' · ⚠ ' + t('flagged') : '') + (i.rewind ? ' · ⏪ ' + t('rewinding_tag') : '') }), h('div', { class: 'rw-path', text: (i.rewind ? 'Rewind' : deviceName(i.ua)) + ' · ' + i.sample[0] })]),
          h('span', { class: 'rw-when', text: t('ago', { x: ago(now - i.ts) }) })]);
      })) : h('div', { class: 'rw-empty', text: t('no_activity') });
      clear(ui.activityBox).appendChild(list);
      var heroNew = heroEl(st.status); if (ui.hero && ui.hero.parentNode) { ui.hero.parentNode.replaceChild(heroNew, ui.hero); ui.hero = heroNew; }
    });
  }
  function historyEl(ops) {
    if (!ops.length) { return null; }
    return h('div', { class: 'rw-section' }, [h('h3', { text: t('history_title') }), h('div', { class: 'rw-history' }, ops.slice(0, 5).map(function (o) {
      var n = Object.keys(o.counts).reduce(function (a, k) { return a + o.counts[k]; }, 0);
      return h('div', { class: 'rw-hcard' }, [h('b', { text: (o.folder === '/' ? t('all_files') : o.folder) }), h('span', { class: 'rw-muted', text: fmtDate(o.created) + ' → ' + fmtDate(o.target) }), h('span', { text: t('files_n', { n: n }) + ' · ' + t('st_' + o.status) })]);
    }))]);
  }

  /* ---------- page ---------- */
  function render() {
    var s = st.status, page = clear(ui.root);
    ui.hero = heroEl(s); page.appendChild(ui.hero);
    s.incidents.forEach(function (i) { page.appendChild(incidentEl(i)); });
    if (s.available && s.enabled) {
      page.appendChild(buildMachine());
      ui.activityBox = h('div', {});
      page.appendChild(h('div', { class: 'rw-section' }, [h('h3', { text: t('activity_title') }), ui.activityBox]));
      var hi = historyEl(s.operations); if (hi) { page.appendChild(hi); }
    }
    page.appendChild(h('p', { class: 'rw-foot', text: t('footer') }));
  }
  function reload(soft) {
    return api('GET', '/api/status').then(function (s) {
      st.status = s;
      if (soft && ui.machine && ui.machine.parentNode) {
        var keep = { ts: st.ts, range: st.range, folder: st.folder };
        render(); st.ts = keep.ts; refreshMachine();
      } else { st.ts = null; render(); if (s.available && s.enabled) { refreshMachine(); handleHash(); } }
    }).catch(function () { clear(ui.root).appendChild(h('div', { class: 'rw-empty', text: t('err') })); });
  }
  function handleHash() {
    var m = /^#incident\/(\d+)/.exec(root.location.hash || '');
    if (!m) { return; }
    api('GET', '/api/incident/' + m[1]).then(function (i) { if (i.status === 'open' || i.status === 'frozen') { chooseIncident(i); } }).catch(function () {});
  }
  function init() {
    var el = doc.getElementById('rw-root'); if (!el) { return; }
    ui.root = el; clear(el).appendChild(h('div', { class: 'rw-empty', text: t('loading') }));
    reload(false);
  }

  root.RW_TEST = { ago: ago, deviceName: deviceName, t: t, clamp: clamp };
  if (typeof module !== 'undefined') { module.exports = root.RW_TEST; }
  if (doc && doc.readyState !== undefined && typeof module === 'undefined') { if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', init); } else { init(); } }
}(typeof window !== 'undefined' ? window : globalThis));
