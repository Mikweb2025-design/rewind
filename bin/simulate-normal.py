#!/usr/bin/env python3
"""Normal heavy use on a TEST account: must NOT raise an incident. Big uploads, mass renames, repeated saves, archives."""
import os, random, subprocess, sys, time
sys.path.insert(0, os.path.dirname(__file__))
from lib_nc import Client

c = Client('alice', os.environ.get('NC_PASS', 'alice-test-pw-123'))
CONTAINER = os.environ.get('NC_CONTAINER', 'nc-rewind')
subprocess.run(['docker', 'exec', '-u', 'www-data', CONTAINER, 'php', 'occ', 'config:app:set', 'rewind', 'mode', '--value=client'], capture_output=True)
c.api('POST', '/api/unfreeze', {})
st, before = c.api('GET', '/api/status')
n0 = len(before['recentIncidents'])
F = f'Normal-{int(time.time())}'
c.mkcol(F)
rnd = random.Random(1)
for i in range(120):  # first sync of a client: many creates, mixed content incl. real jpeg/zip (high entropy but correct magic bytes)
    kind = i % 3
    data = (b'\xff\xd8\xff\xe0' + os.urandom(3000)) if kind == 0 else (b'PK\x03\x04' + os.urandom(3000)) if kind == 1 else ''.join(rnd.choice('abc def\n') for _ in range(3000)).encode()
    assert c.put(f'{F}/doc-{i}.{["jpg", "zip", "txt"][kind]}', data) in (201, 204)
for i in range(60):  # reorganise: rename to normal names
    assert c.move(f'{F}/doc-{i}.{["jpg", "zip", "txt"][i % 3]}', f'{F}/sorted-{i}.{["jpg", "zip", "txt"][i % 3]}') in (201, 204)
for i in range(40):  # an editor saving the same document over and over
    c.put(f'{F}/draft.txt', ''.join(rnd.choice('abc def\n') for _ in range(3000)).encode())
time.sleep(2)
st, after = c.api('GET', '/api/status')
new = len(after['recentIncidents']) - n0
print('incidents raised by normal use:', new, '| frozen:', after['frozen'])
print('NO FALSE POSITIVE' if new == 0 and not after['frozen'] else 'FALSE POSITIVE!')
sys.exit(0 if new == 0 and not after['frozen'] else 1)
