"""HTTP checks against the isolated records from profile_chat_http_fixture.php."""
import argparse
import base64
import html
import http.cookiejar
import json
import re
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

parser = argparse.ArgumentParser()
parser.add_argument('--base-url', required=True)
args = parser.parse_args()
BASE = args.base_url.rstrip('/') + '/'
assert urllib.parse.urlsplit(BASE).hostname in ('127.0.0.1', 'localhost'), 'Run only against the local test fixture.'
PNG = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')

def client():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

def request(session, route, fields=None, file=None):
    headers, data = {}, None
    if file is not None:
        boundary = 'ChalkTest' + uuid.uuid4().hex
        pieces = []
        for name, value in (fields or {}).items():
            pieces.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
        filename, mime, content = file
        pieces.append(f'--{boundary}\r\nContent-Disposition: form-data; name="profile_photo"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + content + b'\r\n')
        pieces.append(f'--{boundary}--\r\n'.encode())
        data = b''.join(pieces)
        headers['Content-Type'] = 'multipart/form-data; boundary=' + boundary
    elif fields is not None:
        data = urllib.parse.urlencode(fields).encode()
    url = BASE + route if not route.startswith('/QuizWeb/') else urllib.parse.urljoin(BASE, route)
    with session.open(urllib.request.Request(url, data, headers), timeout=20) as response:
        return response.read(), response.headers

def csrf(page):
    return re.search(r'name="csrf" value="([^"]+)"', page)[1]

def photo_url(page):
    match = re.search(r'class="profile-avatar-image" src="([^"]+)"', page)
    return html.unescape(match[1]) if match else None

def denied(session, route, code):
    try:
        request(session, route)
    except urllib.error.HTTPError as error:
        assert error.code == code, (error.code, code)
    else:
        raise AssertionError('Private photo request was allowed')

for retry in range(40):
    try:
        request(client(), 'login.php')
        break
    except urllib.error.URLError:
        time.sleep(.1)
else:
    raise AssertionError('Test PHP server did not start')

sessions = {}
for name in ('teacher', 'student', 'peer', 'outsider'):
    session = client()
    page = request(session, 'login.php')[0].decode()
    page = request(session, 'login.php', {'csrf': csrf(page), 'email': 'photo-test-' + name + '@example.test', 'password': 'TestUser!2026'})[0].decode()
    assert 'Invalid email or password' not in page
    sessions[name] = session

student = sessions['student']
page = request(student, 'profile.php')[0].decode()
page = request(student, 'profile.php', {'csrf': csrf(page), 'action': 'photo_upload'}, ('photo.png', 'image/png', PNG))[0].decode()
url = photo_url(page)
assert url, 'Real multipart upload did not save the photo'
image, headers = request(student, url)
assert image == PNG and headers.get_content_type() == 'image/png'
assert headers['X-Content-Type-Options'] == 'nosniff'
assert request(sessions['peer'], url)[0] == PNG
assert request(sessions['teacher'], url)[0] == PNG
denied(sessions['outsider'], url, 403)
denied(client(), url, 401)

failed = request(student, 'profile.php', {'csrf': 'invalid', 'action': 'photo_upload'}, ('photo.png', 'image/png', PNG))[0].decode()
assert 'Your form expired' in failed and photo_url(failed) == url
failed = request(student, 'profile.php', {'csrf': csrf(page), 'action': 'photo_upload'}, ('photo.png', 'image/png', b'<svg><script>alert(1)</script></svg>'))[0].decode()
assert 'valid JPG, PNG, or WebP' in failed and photo_url(failed) == url

fields = {'csrf': csrf(page), 'action': 'details', 'student_number': '2026-001', 'birthdate': '2004-01-01', 'gender': 'Prefer not to say', 'program': 'Updated Science', 'year_level': '3rd Year'}
page = request(student, 'profile.php', fields)[0].decode()
assert photo_url(page) == url and 'value="Updated Science"' in page
chat_csrf = re.search(r'data-chat-csrf="([^"]+)"', page)[1]
threads = json.loads(request(student, 'chat_api.php', {'csrf': chat_csrf, 'classroom_id': '1', 'chat_body': 'Photo test message', 'request_id': uuid.uuid4().hex})[0])['threads']
members = threads[0]['members']
assert {member['id'] for member in members} == {5, 6, 7}
assert all(set(member) == {'id', 'name', 'role', 'avatar_url'} for member in members)
assert next(member for member in members if member['id'] == 6)['avatar_url'] == url
assert threads[0]['messages'][-1]['avatar_url'] == url
assert json.loads(request(sessions['outsider'], 'chat_api.php')[0])['threads'] == []

page = request(student, 'profile.php', {'csrf': csrf(page), 'action': 'photo_remove'})[0].decode()
assert photo_url(page) is None and 'value="Updated Science"' in page
denied(student, url, 404)
print('Live HTTP upload, protected download, class-member roster, message avatars, CSRF/unsafe-file rejection, detail preservation, and photo removal passed.')
