"""Real HTTP integration checks, using an isolated temporary database.
Run: python tests/smoke.py [path/to/php]
"""
import http.cookiejar
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[1]
local = ROOT / '.runtime/php/php.exe'
php = [sys.argv[1] if len(sys.argv) > 1 else str(local) if local.exists() else 'php',
       '-c', str(ROOT / 'config/php.ini')]
if local.exists() and Path(php[0]).resolve() == local.resolve():
    php += ['-d', f'extension_dir={local.parent / "ext"}', '-d', 'extension=pdo_sqlite', '-d', 'extension=mbstring']


class Browser:
    def __init__(self, base):
        self.base = base
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.cookies))

    def request(self, path='/', data=None):
        encoded = urllib.parse.urlencode(data).encode() if data is not None else None
        try:
            r = self.opener.open(self.base + path, encoded, timeout=5)
        except urllib.error.HTTPError as error:
            r = error
        return r.status, r.read().decode('utf-8'), r.headers

    def token(self, path='/'):
        status, body, _ = self.request(path)
        assert status == 200, (path, status)
        return re.search(r'name="csrf" value="([a-f0-9]+)"', body).group(1)


def main():
    with tempfile.TemporaryDirectory(prefix='breadnet-test-') as temporary:
        env = dict(os.environ, BREADNET_DB=str(Path(temporary) / 'test.sqlite'))
        setup = subprocess.run(php + [str(ROOT / 'bin/setup.php')], env=env, capture_output=True, text=True, check=True)
        password = re.search(r'Password: (\w+)', setup.stdout).group(1)
        duplicate = subprocess.run(php + [str(ROOT / 'bin/setup.php')], env=env, capture_output=True)
        assert duplicate.returncode == 1
        config = subprocess.check_output(php + ['-r', 'echo ini_get("session.upload_progress.enabled");'], env=env, text=True)
        assert config == '0'
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        with open(Path(temporary) / 'server.log', 'w') as log:
            server = subprocess.Popen(php + ['-S', f'127.0.0.1:{port}', '-t', str(ROOT / 'public')], env=env, stdout=log, stderr=log)
            try:
                browser = Browser(f'http://127.0.0.1:{port}')
                for _ in range(50):
                    try:
                        status, home, headers = browser.request()
                        break
                    except urllib.error.URLError:
                        time.sleep(.1)
                else:
                    raise AssertionError('PHP server did not start')
                assert status == 200 and 'Breadnet' in home
                assert "form-action 'self'" in headers['Content-Security-Policy']
                assert 'HttpOnly' in headers['Set-Cookie'] and 'SameSite=Lax' in headers['Set-Cookie']
                assert browser.request('/style.css')[0] == 200
                for path in ['/var/breadnet.sqlite', '/app/bootstrap.php', '/bin/setup.php', '/config/php.ini']:
                    assert browser.request(path)[0] == 404, path
                assert browser.request('/guestbook.php')[0] == 405
                assert browser.request('/guestbook.php', {'name': 'no csrf', 'body': 'x'})[0] == 403
                token = browser.token()
                assert browser.request('/admin.php', {'csrf': token, 'action': 'save', 'title': 'unauthorized', 'body': 'x'})[0] == 403
                status, body, _ = browser.request('/guestbook.php', {'csrf': token, 'name': ['a', 'b'], 'body': ''})
                assert status == 200 and '1~40' in body
                name = "guest' OR 1=1 --"
                payload = '<script>alert("xss")</script>'
                status, body, _ = browser.request('/guestbook.php', {'csrf': token, 'name': name, 'body': payload})
                assert status == 200 and '주인장이 확인하면' in body and name not in body
                con = sqlite3.connect(env['BREADNET_DB'])
                guest_id, approved = con.execute('SELECT id,approved FROM guestbook').fetchone()
                assert approved == 0
                status, body, _ = browser.request('/admin.php', {'csrf': token, 'action': 'login', 'username': 'breadkeeper', 'password': password})
                assert status == 200 and '일기 관리' in body and payload not in body
                new_token = browser.token('/admin.php')
                assert new_token != token
                assert browser.request('/admin.php', {'csrf': token, 'action': 'logout'})[0] == 403
                status, body, _ = browser.request('/admin.php', {'csrf': new_token, 'action': 'save', 'title': '테스트 <b>일기</b>', 'body': payload})
                assert status == 200 and '일기를 저장했어요' in body
                post_id = con.execute('SELECT max(id) FROM posts').fetchone()[0]
                _, home, _ = browser.request()
                assert '&lt;script&gt;' in home and payload not in home and '&lt;b&gt;일기&lt;/b&gt;' in home
                browser.request('/admin.php', {'csrf': new_token, 'action': 'save', 'id': post_id, 'title': '수정된 일기', 'body': '수정된 내용'})
                assert '수정된 내용' in browser.request()[1]
                browser.request('/admin.php', {'csrf': new_token, 'action': 'approve_guest', 'id': guest_id})
                home = browser.request()[1]
                assert 'guest&#039; OR 1=1 --' in home and '&lt;script&gt;' in home and payload not in home
                browser.request('/admin.php', {'csrf': new_token, 'action': 'delete_guest', 'id': guest_id})
                browser.request('/admin.php', {'csrf': new_token, 'action': 'delete_post', 'id': post_id})
                assert con.execute('SELECT count(*) FROM guestbook').fetchone()[0] == 0
                assert con.execute('SELECT count(*) FROM posts WHERE id=?', (post_id,)).fetchone()[0] == 0
                for i in range(2):
                    assert browser.request('/guestbook.php', {'csrf': new_token, 'name': '방문자', 'body': f'인사 {i}'})[0] == 200
                assert browser.request('/guestbook.php', {'csrf': new_token, 'name': '방문자', 'body': 'too many'})[0] == 429
                now = '2026-11-07 12:00:00'
                con.executemany('INSERT INTO posts(title,body,created_at) VALUES (?,?,?)', [(f'page-{i}', '내용', now) for i in range(6)])
                con.commit()
                assert '다음 →' in browser.request()[1]
                assert '이전' in browser.request('/?page=2')[1]
                assert browser.request('/?page[]=bad&guest_page[]=bad')[0] == 200
                reset = subprocess.run(php + [str(ROOT / 'bin/reset-password.php')], env=env, capture_output=True, text=True, check=True)
                assert '로그인</button>' in browser.request('/admin.php')[1]
                password = re.search(r'New password: (\w+)', reset.stdout).group(1)
                assert '일기 관리' in browser.request('/admin.php', {'csrf': new_token, 'action': 'login', 'username': 'breadkeeper', 'password': password})[1]
                new_token = browser.token('/admin.php')
                browser.request('/admin.php', {'csrf': new_token, 'action': 'logout'})
                new_token = browser.token('/admin.php')
                for i in range(3):
                    assert '아이디 또는 비밀번호' in browser.request('/admin.php', {'csrf': new_token, 'action': 'login', 'username': 'breadkeeper', 'password': 'wrong'})[1]
                assert browser.request('/admin.php', {'csrf': new_token, 'action': 'login', 'username': 'breadkeeper', 'password': password})[0] == 429
                con.close()
                print('PASS: setup, security headers/config, private paths, CSRF, auth, session rotation/revocation, diary CRUD, escaping, moderation, pagination, guest/login rate limits')
            finally:
                server.terminate()
                server.wait(timeout=10)


if __name__ == '__main__':
    main()
