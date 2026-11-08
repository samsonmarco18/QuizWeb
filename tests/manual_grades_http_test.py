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
try:
    fixture=json.loads(subprocess.check_output([PHP,'tests/academic_http_fixture.php'],cwd=ROOT,env=env,text=True)); created=True
    log=open(ROOT/'data/qa/manual-grades-http.log','w',encoding='utf-8')
    server=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','tests/academic_http_router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    teacher,student,other=[client() for _ in range(3)]
    for _ in range(40):
        try: request(teacher,'login.php'); break
        except urllib.error.URLError: time.sleep(.1)
    for c,name in [(teacher,'teacher'),(student,'student'),(other,'other')]: login(c,name)
    sid=fixture['student']
    path=f'gradebook.php?classroom_id=200&view=student&student_id={sid}&period=prelim'
    page=request(teacher,path)
    (ROOT/'data/qa/manual-grade-preview.html').write_text(page.replace('/QuizWeb/assets/', '../../assets/'),encoding='utf-8')
    overview=request(teacher,'gradebook.php?classroom_id=200&period=prelim')
    (ROOT/'data/qa/teacher-grading-preview.html').write_text(overview.replace('/QuizWeb/assets/', '../../assets/'),encoding='utf-8')
    assert 'Manual grade input' in page and 'Save Changes' in page and 'name="manual_scores[manual-1]"' in page
    assert 'name="manual_scores[manual-4]"' not in page
    assert 'Record class participation' in request(teacher,'gradebook.php?classroom_id=200&view=items&participation=1&period=midterm')
    assert 'Record a manual exam' in request(teacher,'gradebook.php?classroom_id=200&view=items&exam=1&period=finals')
    def save(scores,reason='Correction test',period='prelim'):
        page=request(teacher,path)
        data={'csrf':field(page,'csrf'),'revision':field(page,'revision'),'action':'student_scores','student_id':sid,'period_id':period,'reason':reason}
        data.update({'manual_scores['+k+']':v for k,v in scores.items()})
        return request(teacher,path,data)
    assert 'between 0 and 100' in save({'manual-1':'-1'})
    assert 'between 0 and 100' in save({'manual-1':'101'})
    assert 'selected grading period' in save({'manual-4':'95'})
    assert 'Correction reason' in save({'manual-1':'91'},reason='')
    page=save({'manual-1':'90','manual-2':'85','manual-3':'95'})
    assert 'Gradebook changes saved' in page
    assert 'value="90"' in page and 'value="85"' in page and 'value="95"' in page
    # Students continue to see their published 80 until the teacher reviews and publishes.
    student_page=request(student,'grades.php?classroom_id=200&period=prelim')
    assert 'name="manual_scores' not in student_page and 'academic-period-tabs' not in student_page
    assert 'academic-period-label' not in student_page
    assert 'academic-period-tabs' in overview
    subject_detail=re.search(r'<dialog[^>]*id="subject-200".*?</dialog>',student_page,re.S).group(0)
    assert '90.00 / 100.00' not in subject_detail
    action(teacher,200,'review'); action(teacher,200,'publish',{'confirm':'yes','final_confirm':'yes'})
    student_page=request(student,'grades.php?classroom_id=200&period=prelim')
    subject_detail=re.search(r'<dialog[^>]*id="subject-200".*?</dialog>',student_page,re.S).group(0)
    assert '90.00 / 100.00' in subject_detail and '85.00 / 100.00' in subject_detail and '95.00 / 100.00' in subject_detail
    request(student,'grades.php',{'action':'student_scores'},expected=403)
    request(other,path,expected=403)
    # Maximum validation rolls back the entire batch, including earlier valid entries.
    save({'manual-1':'99','manual-2':'999'})
    assert 'value="90"' in request(teacher,path)
    # Creating manual participation for another period sends the teacher to editable student entry.
    entry=request(teacher,'gradebook.php?classroom_id=200&view=items&participation=1&period=midterm')
    entry=request(teacher,'gradebook.php?classroom_id=200&view=items&participation=1&period=midterm',{'csrf':field(entry,'csrf'),'revision':field(entry,'revision'),'action':'item','manual_entry':'yes','name':'Class Participation','category_id':'work','period_id':'midterm','max_score':'20','date':'2026-11-06'})
    assert 'Enter scores' in entry and 'Class Participation' in entry and 'name="scores[' in entry
    print('Manual batch save, correction, period isolation, validation rollback, participation entry, published student synchronization and permissions passed.')
finally:
    if server: server.terminate(); server.wait(timeout=10); log.close()
    if created: subprocess.run([PHP,'tests/academic_http_fixture.php','cleanup'],cwd=ROOT,env=env,check=True)
