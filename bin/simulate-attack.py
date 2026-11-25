#!/usr/bin/env python3
"""Simulates ransomware on a TEST account through WebDAV: every file is downloaded, encrypted with AES, uploaded as
<name>.locked and the original is deleted; a ransom note is dropped on the way. Run on a test instance only.
Usage: simulate-attack.py [--files 60] [--mode client|user|observe]"""
import argparse, os, random, subprocess, sys, time
sys.path.insert(0, os.path.dirname(__file__))
from lib_nc import Client

ap = argparse.ArgumentParser()
ap.add_argument('--files', type=int, default=60)
ap.add_argument('--mode', default='client')
ap.add_argument('--user', default='alice')
a = ap.parse_args()
c = Client(a.user, os.environ.get('NC_PASS', 'alice-test-pw-123'))
CONTAINER = os.environ.get('NC_CONTAINER', 'nc-rewind')


def occ(*args):
    return subprocess.run(['docker', 'exec', '-u', 'www-data', CONTAINER, 'php', 'occ', *args], capture_output=True, text=True).stdout


def encrypt(data):
    return subprocess.run(['openssl', 'enc', '-aes-256-cbc', '-salt', '-pbkdf2', '-pass', 'pass:attacker'], input=data, capture_output=True).stdout


occ('config:app:set', 'rewind', 'mode', '--value=' + a.mode)
occ('config:app:set', 'rewind', 'min_events', '--value=50')
c.api('POST', '/api/unfreeze', {})
FOLDER = f'Family-{int(time.time())}'
rnd = random.Random(3)
c.mkcol(FOLDER)
names = []
for i in range(a.files):
    kind = i % 4
    if kind == 0:
        n, data = f'letter-{i}.txt', ''.join(rnd.choice('abcdefghij klmnop\n') for _ in range(3500)).encode()
    elif kind == 1:
        n, data = f'photo-{i}.jpg', b'\xff\xd8\xff\xe0' + os.urandom(3800)
    elif kind == 2:
        n, data = f'invoice-{i}.pdf', b'%PDF-1.7\n' + os.urandom(3000)
    else:
        n, data = f'sheet-{i}.docx', b'PK\x03\x04' + os.urandom(3500)
    assert c.put(f'{FOLDER}/{n}', data) in (201, 204)
    names.append(n)
print('created', len(names), 'files in', FOLDER)
time.sleep(3)
before = c.tree(FOLDER)
T = int(time.time())
time.sleep(2)

print('--- attack starts ---')
t0 = time.time()
blocked_at = None
done = 0
for i, n in enumerate(names):
    if i == 8:
        c.put(f'{FOLDER}/README_DECRYPT.txt', 'Your files are encrypted. Pay to recover. HOW TO RECOVER FILES')
    data = c.get(f'{FOLDER}/{n}')
    if data is None:
        print(f'  file {i}: cannot read (frozen accounts can still read? status unexpected)')
        blocked_at = blocked_at or i
        break
    enc = encrypt(data)
    s1 = c.put(f'{FOLDER}/{n}.locked', enc)
    if s1 not in (201, 204):
        blocked_at = i
        print(f'  BLOCKED at file {i} after {time.time() - t0:.1f}s (PUT status {s1})')
        break
    s2 = c.delete(f'{FOLDER}/{n}')
    if s2 not in (204,):
        blocked_at = i
        print(f'  BLOCKED at file {i} after {time.time() - t0:.1f}s (DELETE status {s2})')
        break
    done += 1
print(f'encrypted {done} of {len(names)} files before the shield stopped it' if blocked_at is not None else f'NOT STOPPED: all {done} files encrypted')

time.sleep(1)
st, s = c.api('GET', '/api/status')
inc = s['incidents'][0] if st == 200 and s['incidents'] else None
print('status', st, 'frozen:', s.get('frozen') if st == 200 else s, 'incident:', {k: inc[k] for k in ('id', 'score', 'status', 'reasons', 'files')} if inc else None)
if a.mode == 'observe':
    sys.exit(0)
assert blocked_at is not None and inc is not None, 'attack was not detected'

st, r = c.api('POST', '/api/rewind', {'folder': '/' + FOLDER, 'ts': inc['started'] - 1, 'incident': inc['id']})
print('rewind', st, {k: r[k] for k in ('status', 'done', 'counts')} if st == 200 else r)
after = c.tree(FOLDER)
print('FILES RESTORED EXACTLY:', after == before, f'({len(after)} files)')
if after != before:
    print(' missing', sorted(set(before) - set(after))[:8], 'extra', sorted(set(after) - set(before))[:8], 'changed', [k for k in before if k in after and before[k] != after[k]][:8])
    sys.exit(1)
print('unfreeze:', c.api('POST', '/api/unfreeze', {}))
print('write after unfreeze:', c.put(f'{FOLDER}/after.txt', 'ok'))
