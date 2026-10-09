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
    log=open(ROOT/'data/qa/academic-http.log','w',encoding='utf-8')
    server=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','tests/academic_http_router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    anonymous=client()
    for _ in range(40):
        try: request(anonymous,'login.php'); break
        except urllib.error.URLError: time.sleep(.1)
    teacher,student,peer,outsider,other,admin=[client() for _ in range(6)]
    for c,name in [(teacher,'teacher'),(student,'student'),(peer,'peer'),(outsider,'outsider'),(other,'other'),(admin,'admin')]: login(c,name)
    for view in ['overview','items','setup','analytics','audit','student&student_id='+str(fixture['student'])]: request(teacher,'gradebook.php?classroom_id=200&view='+view)
    page=request(student,'grades.php?year=2026%E2%80%932027&semester=First%20Semester')
    grade_table=re.search(r'<table class="academic-table">(.*?)</table>',page,re.S).group(1)
    assert 'Subject 200' in grade_table and 'Subject 201' in grade_table and 'Subject 202' not in grade_table and 'Academic Peer' not in grade_table
    assert 'Not Released' in page and '>Pending<' in page and '80.00' in page
    request(student,'grades.php?student_id='+str(fixture['peer']),expected=403)
    request(outsider,'grades.php?classroom_id=200',expected=403)
    request(other,'gradebook.php?classroom_id=200',expected=403)
    assert 'Student gradebook' not in request(student,'gradebook.php?classroom_id=200')
    for id in [200,201]:
        action(teacher,id,'lock',{'confirm':'yes'})
    page=request(student,'grades.php?year=2026%E2%80%932027&semester=First%20Semester'); assert '>85.00<' in page and 'Finalized' in page
    page=action(teacher,200,'scores',{'item_id':'manual-1',f'scores[{fixture["student"]}]':'99','reason':'Try locked edit'}); assert 'Grades are locked' in page
    action(teacher,200,'unlock',{'reason':'HTTP correction test'})
    page=action(teacher,200,'scores',{'item_id':'manual-1',f'scores[{fixture["student"]}]':'90'}); assert 'Correction reason' in page
    action(teacher,200,'scores',{'item_id':'manual-1',f'scores[{fixture["student"]}]':'90','reason':'Correct scoring'})
    page=action(teacher,200,'publish',{'confirm':'yes'}); assert 'Review the current' in page
    page=request(student,'grades.php?classroom_id=200'); assert '80.00' in page and '>Pending<' in page
    action(teacher,200,'review'); action(teacher,200,'publish',{'confirm':'yes'})
    request(teacher,'gradebook.php?classroom_id=200&export=csv')
    participant='participants.php?classroom_id=200&quiz_id=9'
    page=request(teacher,participant); assert 'Overdue / Missing' in page
    request(teacher,participant,{'csrf':field(page,'csrf'),'student_id':fixture['student'],'body':'PRIVATE-ACADEMIC-REMINDER'})
    assert 'PRIVATE-ACADEMIC-REMINDER' in request(student,'classroom.php?id=200')
    assert 'PRIVATE-ACADEMIC-REMINDER' not in request(peer,'classroom.php?id=200')
    request(other,participant,expected=403); assert '<h1>Quiz participants</h1>' not in request(student,participant)
    for attempt_number in [1,2]:
        play=request(student,'play.php?classroom_id=200&quiz_id=9')
        token=re.search(r'data-run-token="([^"]+)"',play).group(1); csrf=re.search(r'data-csrf="([^"]+)"',play).group(1)
        public_quiz=json.loads(html.unescape(re.search(r"data-quiz='([^']+)'",play).group(1)))
        correct=public_quiz['questions'][0]['options'].index('4')
        request(student,'quiz_progress.php',{'csrf':csrf,'run_token':token},expected=409)
        request(student,'quiz_integrity.php',{'csrf':csrf,'run_token':token,'classroom_id':200,'quiz_id':9,'action':'start','event_id':uuid.uuid4().hex})
        request(student,'quiz_progress.php',{'csrf':csrf,'run_token':token})
        assert 'Taking Quiz' in request(teacher,participant)
        request(student,'submit_game.php',{'csrf':csrf,'run_token':token,'classroom_id':200,'quiz_id':9,'answers':json.dumps([correct]),'elapsed_seconds':10})
    page=request(teacher,participant); assert 'Completed' in page and '10 / 10' in page and '>2</td>' in page
    request(teacher,'classroom_settings.php?classroom_id=200'); request(other,'classroom_settings.php?classroom_id=200',expected=403)
    request(admin,'academic_settings.php'); assert '<h1>Academic settings</h1>' not in request(teacher,'academic_settings.php')
    second=request(student,'grades.php?year=2026%E2%80%932027&semester=Second%20Semester'); second_table=re.search(r'<table class="academic-table">(.*?)</table>',second,re.S).group(1); assert 'Subject 202' in second_table and 'Subject 200' not in second_table
    print('Live MySQL/HTTP teacher views, student privacy, semester grouping/mean, review/publication/locks/corrections, private reminders, quiz progress/submission/retakes, and role permissions passed.')
finally:
    if server: server.terminate(); server.wait(timeout=10); log.close()
    if created: subprocess.run([PHP,'tests/academic_http_fixture.php','cleanup'],cwd=ROOT,env=env,check=True)
