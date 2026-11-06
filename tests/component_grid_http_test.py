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
    log=open(ROOT/'data/qa/component-grid-http.log','w',encoding='utf-8')
    server=subprocess.Popen([PHP,'-S',f'127.0.0.1:{port}','tests/academic_http_router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
    teacher,student,other=[client() for _ in range(3)]
    for _ in range(40):
        try: request(teacher,'login.php'); break
        except urllib.error.URLError: time.sleep(.1)
    for c,name in [(teacher,'teacher'),(student,'student'),(other,'other')]: login(c,name)
    sid=fixture['student']; peer=fixture['peer']
    def grid(period='prelim',data=None):
        path=f'gradebook.php?classroom_id=200&period={period}'
        page=request(teacher,path)
        if data is None: return page
        return request(teacher,path,dict({'csrf':field(page,'csrf'),'revision':field(page,'revision')},**data))
    page=grid()
    assert 'Class grading configuration' not in page and 'Class statistics' not in page
    table=re.search(r'<table class="academic-table academic-component-entry-table">(.*?)</table>',page,re.S).group(1)
    assert 'Prelim grade' in table and 'Final grade' not in table and '>Midterm<' not in table
    page=grid(data={'action':'add_component','name':'Class Participation','weight':'10','component_weights[quiz]':'30','component_weights[work]':'20','component_weights[exam]':'40','reason':'Add participation as its own weighted component.'})
    table=re.search(r'<table class="academic-table academic-component-entry-table">(.*?)</table>',page,re.S).group(1)
    assert 'Class Participation' in table and '10.00% weight' in table
    category=re.search(r'name="component_scores\['+str(sid)+r'\]\[(component-[^]]+)\]"',table).group(1)
    def save(period,scores):
        data={'action':'component_scores','period_id':period,'reason':'Grade encoding and correction.'}
        for who,components in scores.items():
            for key,value in components.items(): data[f'component_scores[{who}][{key}]']=value
        return grid(period,data)
    for period in ['prelim','midterm','finals']:
        scores={sid:{category:'80'},peer:{category:'80'}}
        if period=='prelim': scores[sid].update(quiz='90',work='85',exam='95',**{category:'100'})
        page=save(period,scores)
        assert 'Gradebook changes saved' in page
    page=grid()
    assert 'value="90"' in page and 'value="100"' in page and '>92.00<' in page
    midterm=grid('midterm'); assert 'value="90"' not in midterm and 'value="100"' not in midterm
    (ROOT/'data/qa/component-grid-preview.html').write_text(page.replace('/QuizWeb/assets/','../../assets/'),encoding='utf-8')
    (ROOT/'data/qa/component-grid-midterm-preview.html').write_text(midterm.replace('/QuizWeb/assets/','../../assets/'),encoding='utf-8')
    assert 'name="action" value="publish"' not in page and 'data-release-warnings' not in page
    summary=request(teacher,'gradebook.php?classroom_id=200&view=semester&period=midterm')
    assert 'Whole semester overview' in summary and 'Semester final grade' in summary and 'name="action" value="publish"' in summary
    summary_table=re.search(r'<table class="academic-table academic-semester-table">(.*?)</table>',summary,re.S).group(1)
    assert all(name in summary_table for name in ['Prelim','Midterm','Finals']) and 'component_scores[' not in summary
    assert 'Back to Midterm grade entry' in summary
    (ROOT/'data/qa/semester-summary-preview.html').write_text(summary.replace('/QuizWeb/assets/','../../assets/'),encoding='utf-8')
    activities=request(teacher,'gradebook.php?classroom_id=200&view=items&period=midterm')
    assert 'Midterm activities' in activities and 'Prelim Quizzes' not in activities and 'Finals Quizzes' not in activities
    student_details=request(teacher,f'gradebook.php?classroom_id=200&view=student&student_id={sid}&period=midterm')
    assert 'Midterm grade' in student_details and 'Final grade <strong>' not in student_details and 'name="manual_scores[manual-1]"' not in student_details
    monitor=request(teacher,'gradebook.php?classroom_id=200&view=monitor&period=midterm')
    monitor_terms=re.findall(r'<section class="academic-monitor-term">(.*?)</section>',monitor,re.S)
    assert len(monitor_terms)==1 and 'Midterm' in monitor_terms[0] and 'Prelim' not in monitor_terms[0]
    page=save('prelim',{sid:{'quiz':'99',category:'101'}})
    assert 'between 0 and 100' in page and 'value="90"' in grid()
    published=request(student,'grades.php?classroom_id=200&period=prelim')
    detail=re.search(r'<dialog[^>]*id="subject-200".*?</dialog>',published,re.S).group(0)
    assert 'Class Participation' not in detail
    release_path='gradebook.php?classroom_id=200&view=semester&period=prelim'
    def release_action(action_name,extra=None):
        release=request(teacher,release_path)
        data={'csrf':field(release,'csrf'),'revision':field(release,'revision'),'action':action_name}; data.update(extra or {})
        return request(teacher,release_path,data)
    release_action('review'); page=release_action('publish',{'confirm':'yes','final_confirm':'yes'})
    assert 'published to enrolled students' in page
    published=request(student,'grades.php?classroom_id=200&period=prelim')
    detail=re.search(r'<dialog[^>]*id="subject-200".*?</dialog>',published,re.S).group(0)
    assert 'Class Participation' in detail and 'Manual component grade' in detail and '92.00' in detail
    assert 'component_scores[' not in published
    request(student,'grades.php',{'action':'component_scores'},expected=403)
    request(other,'gradebook.php?classroom_id=200',expected=403)
    action(teacher,200,'lock',{'confirm':'yes'})
    assert 'Grades are locked' in save('prelim',{sid:{category:'90'}})
    print('Period-only grid, dynamic participation column, per-student save, validation rollback, publication synchronization and read-only/locked access passed.')
finally:
    if server: server.terminate(); server.wait(timeout=10); log.close()
    if created: subprocess.run([PHP,'tests/academic_http_fixture.php','cleanup'],cwd=ROOT,env=env,check=True)
