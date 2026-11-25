/* Rewind - admin settings page. */
(function (root) {
  'use strict';
  var doc = root.document, S = root.RW_I18N || {};
  var lang = ((doc.documentElement.getAttribute('lang') || 'en') + '').slice(0, 2), STR = S[lang] || S.en || {};
  function t(k) { return STR[k] !== undefined ? STR[k] : (S.en && S.en[k]) || k; }
  function h(tag, a, kids) {
    var el = doc.createElement(tag);
    Object.keys(a || {}).forEach(function (k) { var v = a[k]; if (k === 'text') { el.textContent = v; } else if (k === 'class') { el.className = v; } else if (k.indexOf('on') === 0) { el.addEventListener(k.slice(2), v); } else if (v !== false && v !== null && v !== undefined) { el.setAttribute(k, v); } });
    [].concat(kids || []).forEach(function (c) { if (c) { el.appendChild(typeof c === 'string' ? doc.createTextNode(c) : c); } });
    return el;
  }
  function token() { return doc.head.getAttribute('data-requesttoken') || (root.OC && root.OC.requestToken) || ''; }
  function url(p) { return root.OC && root.OC.generateUrl ? root.OC.generateUrl('/apps/rewind' + p) : '/index.php/apps/rewind' + p; }
  function api(m, p, body) {
    var init = { method: m, credentials: 'same-origin', headers: { Accept: 'application/json', requesttoken: token(), 'OCS-APIRequest': 'true' } };
    if (body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(body); }
    return root.fetch(url(p), init).then(function (r) { return r.json().then(function (j) { if (!r.ok) { var e = new Error('http'); e.data = j; throw e; } return j; }); });
  }
  function fmt(ts) { try { return new Date(ts * 1000).toLocaleString(lang); } catch (e) { return ''; } }

  var box = doc.getElementById('rw-admin'); if (!box) { return; }
  var msg = h('span', { 'aria-live': 'polite' });

  function field(label, input) { return h('label', { class: 'rw-col' }, [h('span', { text: label }), input]); }
  function num(id, v, min, max) { return h('input', { type: 'number', id: id, value: String(v), min: String(min), max: String(max) }); }

  function render(d) {
    var c = d.config;
    box.textContent = '';
    box.appendChild(h('h2', { text: t('a_title') }));
    box.appendChild(h('p', { class: 'rw-note', text: t('a_intro') }));
    var en = h('input', { type: 'checkbox', id: 'rw-enabled' }); en.checked = !!c.enabled;
    var modes = ['observe', 'client', 'user'].map(function (m) {
      var r = h('input', { type: 'radio', name: 'rw-mode', value: m }); r.checked = c.mode === m;
      return h('label', {}, [r, h('span', { text: t('a_mode_' + m) })]);
    });
    var checks = [['versions', 'a_c_versions'], ['trashbin', 'a_c_trashbin']].map(function (x) {
      return h('div', { class: 'rw-check' }, [h('span', { class: 'rw-badge' + (d.checks[x[0]] ? '' : ' bad'), text: d.checks[x[0]] ? t('a_ok') : t('a_missing') }), h('span', { text: t(x[1]) })]);
    });
    var email = h('input', { type: 'email', id: 'rw-email', value: c.email || '', autocomplete: 'off' });
    var excl = h('input', { type: 'text', id: 'rw-excluded', value: (c.excluded_users || []).join(', ') });
    box.appendChild(h('fieldset', {}, [h('legend', { text: t('a_checks') })].concat(checks)));
    box.appendChild(h('fieldset', {}, [h('label', {}, [en, h('span', { text: t('a_enabled') })]), h('p', { class: 'rw-note', style: 'margin:10px 0 2px', text: t('a_mode') })].concat(modes)));
    box.appendChild(h('fieldset', {}, [h('div', { class: 'rw-grid2' }, [
      field(t('a_observe'), num('rw-obs', c.observe_level, 10, 90)), field(t('a_freeze'), num('rw-frz', c.freeze_level, 20, 100)),
      field(t('a_window'), num('rw-win', c.window, 60, 3600)), field(t('a_min'), num('rw-min', c.min_events, 10, 5000)), field(t('a_retention'), num('rw-ret', c.retention_days, 7, 730))
    ]), h('p', { class: 'rw-note', style: 'margin-top:8px', text: t('a_hint_help') })]));
    box.appendChild(h('fieldset', {}, [field(t('a_email'), email), field(t('a_excluded'), excl)]));
    box.appendChild(h('div', { class: 'rw-actions' }, [
      h('button', { class: 'primary', type: 'button', text: t('a_save'), onclick: save }),
      h('button', { type: 'button', text: t('a_test'), onclick: function () { api('POST', '/admin/test', {}).then(function () { msg.textContent = '✓'; }).catch(function () { msg.textContent = '✕ ' + t('a_email'); }); } }), msg
    ]));
    box.appendChild(h('fieldset', {}, [h('legend', { text: t('a_frozen') })].concat(d.frozen.length ? [h('table', {}, d.frozen.map(function (f) {
      return h('tr', {}, [h('td', { text: f.uid }), h('td', { text: f.client ? String(f.client).slice(0, 6) : '*' }), h('td', { text: fmt(f.started) }), h('td', {}, [h('button', { type: 'button', text: t('a_release'), onclick: function () { api('POST', '/admin/unfreeze', { uid: f.uid }).then(load); } })])]);
    }))] : [h('p', { class: 'rw-note', text: t('a_none') })])));
    box.appendChild(h('fieldset', {}, [h('legend', { text: t('a_incidents') })].concat(d.incidents.length ? [h('table', {}, d.incidents.slice(0, 15).map(function (i) {
      return h('tr', {}, [h('td', { text: fmt(i.started) }), h('td', { text: i.uid }), h('td', { text: i.score + '/100' }), h('td', { text: i.status }), h('td', { text: i.reasons.map(function (r) { return t('reason_' + r); }).join(', ') })]);
    }))] : [h('p', { class: 'rw-note', text: t('a_none') })])));
  }
  function save() {
    var mode = (box.querySelector('input[name="rw-mode"]:checked') || {}).value || 'observe', v = function (id) { return box.querySelector('#' + id).value; };
    var body = { enabled: box.querySelector('#rw-enabled').checked, mode: mode, observe_level: v('rw-obs'), freeze_level: v('rw-frz'), window: v('rw-win'), min_events: v('rw-min'), retention_days: v('rw-ret'), email: v('rw-email'), excluded_users: v('rw-excluded') };
    api('POST', '/admin/config', body).then(function () { msg.textContent = '✓ ' + t('a_saved'); }).catch(function (e) { msg.textContent = '✕ ' + ((e.data && e.data.bad) || []).join(', '); });
  }
  function load() { return api('GET', '/admin/config').then(render); }
  load();
}(window));
