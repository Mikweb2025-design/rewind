'use strict';
/* Frontend checks without a browser: string parity, pure helpers, syntax of every script. */
const fs = require('fs'), path = require('path'), vm = require('vm');
const root = path.join(__dirname, '..', '..');
let failed = 0;
const ok = (c, m) => { if (!c) { failed++; console.error('FAIL', m); } };

const S = require(path.join(root, 'js/rw-i18n.js'));
const keys = Object.keys(S.en);
for (const l of ['de', 'it']) {
  ok(keys.every((k) => k in S[l]), l + ' misses keys: ' + keys.filter((k) => !(k in S[l])).join(','));
  ok(Object.keys(S[l]).every((k) => k in S.en), l + ' has extra keys');
  for (const k of keys) { const a = (S.en[k].match(/\{\w+\}/g) || []).sort().join(), b = ((S[l][k] || '').match(/\{\w+\}/g) || []).sort().join(); ok(a === b, 'placeholders differ for ' + k + ' in ' + l); }
}
for (const f of fs.readdirSync(path.join(root, 'js'))) { try { new vm.Script(fs.readFileSync(path.join(root, 'js', f), 'utf8'), { filename: f }); } catch (e) { ok(false, 'syntax ' + f + ': ' + e.message); } }

global.window = undefined;
const T = require(path.join(root, 'js/rw-app.js'));
ok(T.ago(30) === '30 s' && T.ago(600) === '10 min' && T.ago(7200) === '2 h' && T.ago(3 * 86400) === '3 d', 'ago');
ok(T.deviceName('Mozilla/5.0 (Mac) mirall/3.13') === 'Nextcloud Desktop', 'device desktop');
ok(T.deviceName('Mozilla/5.0 (Linux; Android 14)') === 'Android', 'device android');
ok(T.deviceName('Python-urllib/3.13') === 'Python-urllib', 'device other');
ok(T.clamp(5, 0, 3) === 3, 'clamp');
ok(/\{n\}/.test(S.en.files_n) && T.t('files_n', { n: 4 }) === '4 files', 't() params');

if (failed) { console.error(failed + ' frontend check(s) failed'); process.exit(1); }
console.log('OK: frontend checks passed (' + keys.length + ' strings x 3 languages)');
