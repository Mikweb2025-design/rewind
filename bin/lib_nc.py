"""Tiny WebDAV/API helper for the test scripts (standard library only)."""
import base64, hashlib, json, os, ssl, time, urllib.error, urllib.request, xml.etree.ElementTree as ET

BASE = os.environ.get('NC_URL', 'http://localhost:8089')


class Client:
    def __init__(self, user, pw):
        self.user = user
        self.auth = 'Basic ' + base64.b64encode(f'{user}:{pw}'.encode()).decode()

    def req(self, method, url, data=None, headers=None, ok=(200, 201, 204, 207)):
        h = {'Authorization': self.auth, 'OCS-APIRequest': 'true'}
        h.update(headers or {})
        r = urllib.request.Request(url if url.startswith('http') else BASE + url, data=data, method=method, headers=h)
        try:
            with urllib.request.urlopen(r, timeout=120) as resp:
                return resp.status, resp.read()
        except urllib.error.HTTPError as e:
            return e.code, e.read()

    def dav(self, path=''):
        from urllib.parse import quote
        return f'/remote.php/dav/files/{self.user}/' + quote(path.lstrip('/'))

    def put(self, path, data):
        return self.req('PUT', self.dav(path), data if isinstance(data, bytes) else data.encode())[0]

    def mkcol(self, path):
        return self.req('MKCOL', self.dav(path))[0]

    def delete(self, path):
        return self.req('DELETE', self.dav(path))[0]

    def move(self, a, b):
        return self.req('MOVE', self.dav(a), headers={'Destination': BASE + self.dav(b), 'Overwrite': 'F'})[0]

    def get(self, path):
        s, b = self.req('GET', self.dav(path))
        return b if s == 200 else None

    def api(self, method, path, body=None):
        data = json.dumps(body).encode() if body is not None else None
        s, b = self.req(method, '/index.php/apps/rewind' + path, data, {'Content-Type': 'application/json', 'Accept': 'application/json'})
        try:
            return s, json.loads(b)
        except Exception:
            return s, b.decode(errors='replace')[:300]

    def tree(self, folder):
        """{relative path: sha256} of all files below folder"""
        out = {}

        def walk(p):
            s, b = self.req('PROPFIND', self.dav(p), b'<?xml version="1.0"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/></d:prop></d:propfind>', {'Depth': '1'})
            if s != 207:
                return
            root = ET.fromstring(b)
            for r in root.findall('{DAV:}response'):
                href = r.find('{DAV:}href').text
                rel = href.split(f'/remote.php/dav/files/{self.user}/', 1)[1]
                from urllib.parse import unquote
                rel = unquote(rel).rstrip('/')
                if rel == p.strip('/'):
                    continue
                if r.find('.//{DAV:}collection') is not None:
                    walk(rel)
                else:
                    out[rel] = hashlib.sha256(self.get(rel) or b'').hexdigest()
        walk(folder)
        return out
