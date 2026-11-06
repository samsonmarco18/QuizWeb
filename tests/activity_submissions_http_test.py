"""Isolated local academic gradebook test; never uses the application database."""
import html, http.cookiejar, json, os, pathlib, re, socket, subprocess, time, urllib.error, urllib.parse, urllib.request, uuid
ROOT=pathlib.Path(__file__).resolve().parents[1]
PHP=os.environ.get('QUIZWEB_TEST_PHP',r'C:\xampppp\php\php.exe')
env=os.environ.copy(); env.update(QUIZWEB_DB_DRIVER='mysql',QUIZWEB_DB_NAME='chalk_academic_qa_'+uuid.uuid4().hex[:12],QUIZWEB_ACADEMIC_HTTP_TEST='1')
with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
BASE=f'http://127.0.0.1:{port}/QuizWeb/'
def client(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
def request(c,path,data=None,expected=200):
    body=None if data is None else urllib.parse.urlencode(data).encode()
    try:
        response=c.open(urllib.request.Request(BASE+path,body),timeout=15)
        code=response.status; text=response.read().decode()
    except urllib.error.HTTPError as ex: code=ex.code; text=ex.read().decode()
    assert code==expected,(path,code,text[:400])
    assert 'Fatal error' not in text and 'Warning:' not in text,(path,text[:800])
    return text
def field(text,name):
    match=re.search(r'name="'+re.escape(name)+r'"[^>]*value="([^"]*)"',text)
    assert match,(name,text[:300]); return html.unescape(match.group(1))
def login(c,name):
    page=request(c,'login.php'); return request(c,'login.php',{'csrf':field(page,'csrf'),'email':f'academic-{name}@example.test','password':'TestUser!2026'})
def action(c,class_id,action,extra=None):
    path=f'gradebook.php?classroom_id={class_id}'; page=request(c,path)
    data={'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':action}; data.update(extra or {})
    return request(c,path,data)
server=None; created=False
def upload(c,path,fields,files):
    boundary='quizweb'+uuid.uuid4().hex
    parts=[]
    for name,value in fields.items():
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
    for filename,payload in files:
        parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="submission_files[]"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode()+payload+b'\r\n')
    parts.append(f'--{boundary}--\r\n'.encode())
    response=c.open(urllib.request.Request(BASE+path,b''.join(parts),{'Content-Type':'multipart/form-data; boundary='+boundary}),timeout=15)
    text=response.read().decode(); assert 'Fatal error' not in text and 'Warning:' not in text,text[:1000]
    return text

def download(c,path,expected=200):
    try:
        response=c.open(BASE+path,timeout=15); code=response.status; data=response.read()
    except urllib.error.HTTPError as ex: code=ex.code; data=ex.read()
    assert code==expected,(path,code); return data

try:
    fixture=json.loads(subprocess.check_output([PHP,'tests/academic_http_fixture.php'],cwd=ROOT,env=env,text=True)); created=True
    log=open(ROOT/'data/qa/activity-submissions-http.log','w',encoding='utf-8')
    server=subprocess.Popen([PHP,'-d','upload_max_filesize=10M','-d','post_max_size=60M','-S',f'127.0.0.1:{port}','tests/academic_http_router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    teacher,student,peer,other,outsider=[client() for _ in range(5)]
    for _ in range(40):
        try: request(teacher,'login.php'); break
        except urllib.error.URLError: time.sleep(.1)
    for c,name in [(teacher,'teacher'),(student,'student'),(peer,'peer'),(other,'other'),(outsider,'outsider')]: login(c,name)
    sid=fixture['student']; peer_id=fixture['peer']
    path='gradebook.php?classroom_id=200&view=items&submission=1&period=prelim'
    page=request(teacher,path)
    assert 'Post a submission activity' in page and 'Require student submissions' in page
    page=request(teacher,path,{'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':'item','manual_entry':'yes','name':'Lab report','category_id':'work','period_id':'prelim','max_score':'20','date':'2026-11-06','instructions':'Explain the experiment and attach evidence.','accept_responses':'yes','due_at':'2026-11-05T12:00'})
    assert 'Lab report' in page and 'academic-submission-table' in page
    item=field(page,'item_id'); entry=f'gradebook.php?classroom_id=200&view=entry&item_id={item}&period=prelim'
    portal=f'student_portal.php?view=activities&classroom_id=200&item_id={item}'
    page=request(student,portal); assert 'multipart/form-data' in page and 'Submit activity' in page
    def submit(c,text='',files=None,extra=None):
        page=request(c,portal); data={'csrf':field(page,'csrf'),'classroom_id':'200','item_id':item,'response':text};data.update(extra or {})
        return upload(c,portal,data,files or [])
    assert 'answer or attach' in submit(student)
    assert 'Unsupported file type' in submit(student,files=[('bad.php',b'<?php echo 1;')])
    assert 'up to five files' in submit(student,files=[(f'file-{i}.txt',b'answer') for i in range(6)])
    page=submit(student,files=[('evidence.txt',b'PRIVATE-STUDENT-FILE')])
    assert 'Submission received' in page and 'evidence.txt' in page
    file_url=html.unescape(re.search(r'href="(/QuizWeb/submission_file.php[^"]+)"',page).group(1)).removeprefix('/QuizWeb/')
    assert download(student,file_url)==b'PRIVATE-STUDENT-FILE' and download(teacher,file_url)==b'PRIVATE-STUDENT-FILE'
    for c in [peer,other,outsider]: download(c,file_url,403)
    page=submit(student,'<script>Evidence answer</script>',[('notes.txt',b'ADDITIONAL-NOTES')])
    assert 'evidence.txt' in page and 'notes.txt' in page and '&lt;script&gt;Evidence answer&lt;/script&gt;' in page
    assert 'response' in page
    page=submit(peer,'Peer answer',[('peer.txt',b'PRIVATE-PEER-FILE')])
    peer_file=re.search(r'name="remove_files\[\]" value="([^"]+)"',page).group(1)
    assert 'Attachment access denied' in submit(student,'Attempt remove foreign file',extra={'remove_files[0]':peer_file})
    page=request(student,portal)
    own_files=re.findall(r'name="remove_files\[\]" value="([^"]+)"',page)
    assert 'answer or attach' in submit(student,'',extra={f'remove_files[{i}]':name for i,name in enumerate(own_files)})
    assert 'evidence.txt' in request(student,portal) and 'notes.txt' in request(student,portal)
    evidence_file=re.search(r'name="remove_files\[\]" value="([^"]+)"><span>Remove evidence.txt</span>',page).group(1)
    page=submit(student,'<script>Evidence answer</script>',extra={'remove_files[0]':evidence_file})
    assert 'evidence.txt' not in page and 'notes.txt' in page
    download(student,file_url,404)
    page=submit(student,'<script>Evidence answer</script>',[('evidence.txt',b'REPLACED-EVIDENCE')])
    assert 'five files' in submit(student,'Should roll back',[('more-'+str(i)+'.txt',b'Extra') for i in range(4)])
    page=request(teacher,entry)
    assert 'Academic Student' in page and 'Academic Peer' in page and 'Peer answer' in page and 'evidence.txt' in page and 'notes.txt' in page and 'Late submission' in page
    assert '<script>Evidence answer</script>' not in page
    (ROOT/'data/qa/activity-grading-preview.html').write_text(page.replace('/QuizWeb/assets/','../../assets/'),encoding='utf-8')
    (ROOT/'data/qa/activity-submit-preview.html').write_text(request(student,portal).replace('/QuizWeb/assets/','../../assets/'),encoding='utf-8')
    page=request(teacher,entry,{'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':'scores','item_id':item,f'scores[{sid}]':'21'})
    assert 'between 0 and 20' in page
    page=request(teacher,entry)
    page=request(teacher,entry,{'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':'scores','item_id':item,f'scores[{sid}]':'18',f'scores[{peer_id}]':'15',f'feedback[{sid}]':'Good evidence and reasoning.'})
    assert 'Gradebook changes saved' in page
    student_page=request(student,portal); assert 'Good evidence and reasoning.' not in student_page and 'Submit activity</button>' not in student_page and 'Update submission</button>' not in student_page
    assert 'already been graded' in submit(student,'Attempt to overwrite')
    release='gradebook.php?classroom_id=200&view=semester'
    page=request(teacher,release); request(teacher,release,{'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':'review'})
    page=request(teacher,release); request(teacher,release,{'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':'publish','confirm':'yes','final_confirm':'yes'})
    page=request(student,portal); assert 'Good evidence and reasoning.' in page and '18.00 / 20.00' in page
    request(other,entry,expected=403)
    assert '<table class="academic-table academic-submission-table">' not in request(student,entry)
    print('Non-game posting, text/file submissions, append, upload validation, private download access, teacher grading list, score validation, graded resubmission lock and published feedback passed.')
finally:
    if server: server.terminate(); server.wait(timeout=10); log.close()
    if created: subprocess.run([PHP,'tests/academic_http_fixture.php','cleanup'],cwd=ROOT,env=env,check=True)
