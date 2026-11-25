#!/usr/bin/env python3
"""End to end test of Rewind on a test instance (never run it against a real account).
Creates files, changes them, rewinds, compares checksums, undoes the rewind, compares again."""
import os, random, sys, time
sys.path.insert(0, os.path.dirname(__file__))
from lib_nc import Client

c = Client(os.environ.get('NC_USER', 'alice'), os.environ.get('NC_PASS', 'alice-test-pw-123'))
FOLDER = f'Docs-{int(time.time())}'
rnd = random.Random(7)
text = lambda n: ''.join(rnd.choice('abcdefghij klmnop\n') for _ in range(n)).encode()

assert c.mkcol(FOLDER) in (201, 405)
assert c.mkcol(FOLDER + '/sub') in (201, 405)
files = [f'{FOLDER}/f{i:02d}.txt' for i in range(24)] + [f'{FOLDER}/sub/s{i}.txt' for i in range(6)]
for f in files:
    assert c.put(f, text(2000)) in (201, 204), f
time.sleep(1)
for f in files[:12]:  # a first overwrite so every file has history and the later one creates a version of known content
    pass
s0 = c.tree(FOLDER)
print('state at T:', len(s0), 'files')
time.sleep(2)
T = int(time.time())
time.sleep(2)

for f in files[:10]:
    assert c.put(f, text(2500)) in (201, 204)
for i, f in enumerate(files[10:15]):
    assert c.move(f, f.replace('.txt', '-renamed.txt')) in (201, 204)
for f in files[15:20]:
    assert c.delete(f) == 204
for i in range(5):
    assert c.put(f'{FOLDER}/new{i}.txt', text(900)) in (201, 204)
assert c.move(files[25], f'{FOLDER}/moved-s.txt') in (201, 204)
s1 = c.tree(FOLDER)
print('state after changes:', len(s1), 'files; differs:', s1 != s0)
assert s1 != s0

st, pv = c.api('POST', '/api/rewind/preview', {'folder': '/' + FOLDER, 'ts': T})
print('preview', st, pv['counts'] if st == 200 else pv, 'problems:', pv.get('problems') if st == 200 else '-')
assert st == 200 and pv['problems'] == 0, pv

st, r = c.api('POST', '/api/rewind', {'folder': '/' + FOLDER, 'ts': T})
print('rewind', st, {k: r[k] for k in ('status', 'done')} if st == 200 else r)
assert st == 200 and r['status'] == 'done', r
s2 = c.tree(FOLDER)
ok = s2 == s0
print('REWIND matches state at T:', ok)
if not ok:
    print(' missing:', sorted(set(s0) - set(s2))[:10], ' extra:', sorted(set(s2) - set(s0))[:10], ' changed:', [k for k in s0 if k in s2 and s0[k] != s2[k]][:10])
    sys.exit(1)

time.sleep(2)
if os.environ.get('DEBUG_UNDO'):
    os.system("docker exec nc-rewind sh -c \"cd /var/www/html && php -r 'define(\\\"OC_CONSOLE\\\",1); require \\\"lib/base.php\\\"; \\\\OC_App::loadApp(\\\"rewind\\\"); \\$db=\\\\OC::\\$server->get(\\\\OCP\\\\IDBConnection::class); \\$o=\\$db->executeQuery(\\\"SELECT * FROM oc_rw_operation ORDER BY id DESC LIMIT 1\\\")->fetch(); \\$j=\\\\OC::\\$server->get(\\\\OCA\\\\Rewind\\\\Service\\\\Journal::class); \\$rows=\\$j->after(\\\"alice\\\", (int)\\$o[\\\"created\\\"]-1, \\$o[\\\"folder\\\"]); echo count(\\$rows).\\\" rows \\\".json_encode(\\\\OCA\\\\Rewind\\\\Domain\\\\RewindPlanner::plan(\\$rows)[\\\"counts\\\"]).PHP_EOL; foreach(\\$rows as \\$r) echo \\$r[\\\"action\\\"].\\\" \\\".\\$r[\\\"path\\\"].\\\" \\\".\\$r[\\\"file_id\\\"].\\\" \\\".\\$r[\\\"ts\\\"].PHP_EOL;'\"")
st, u = c.api('POST', '/api/rewind/undo', {'op': r['op']})
print('undo', st, {k: u[k] for k in ('status', 'done')} if st == 200 else u)
s3 = c.tree(FOLDER)
print('UNDO matches state before rewind:', s3 == s1)
if s3 != s1:
    print(' missing:', sorted(set(s1) - set(s3))[:10], ' extra:', sorted(set(s3) - set(s1))[:10], ' changed:', [k for k in s1 if k in s3 and s1[k] != s3[k]][:10])
    sys.exit(1)
print('ALL OK')
