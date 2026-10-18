# CHALK / QuizWeb manual test cases

Use this checklist to test the application yourself in a browser. These are test instructions, not recorded passes. All cases start **Not run**. Record actual results in the log at the end.

## Before testing

1. Follow the [README setup instructions](../README.md) for your database and start the application. For XAMPP, normally open `http://localhost/QuizWeb/`; for Docker, use `http://localhost:8080/QuizWeb/`.
2. Use a test database/classroom: these tests save attempts, change scores, publish grades, and change test account status.
3. Register Teacher A, Teacher B, Student A, and Student B with distinct email addresses. Use a configured administrator account for admin cases. The README describes administrator setup; registration does not create administrators.
4. Use separate browser profiles or a normal and private window to keep teacher and student sessions separate. Log out before switching accounts within one window.
5. Have a small valid PNG/JPG, a PDF, a TXT lesson file, and an unsupported file such as `.exe` ready. For size checks, prepare a profile image larger than 2 MB.
6. Record the URL, database type, browser/version, date, and tested Git revision if known. Run the core path first: AUTH-01, AUTH-03, CLS-01, CLS-02, BLD-01, PLAY-01, PLAY-02, GRD-01 through GRD-05.

### Reusable test data

| Item | Test value |
| --- | --- |
| Classroom | `QA Mathematics`, owned by Teacher A |
| Separate classroom | `QA Private`, owned by Teacher B; do not enroll Student A |
| Standard quiz | `QA Addition`, four multiple-choice questions, 10 points each |
| Questions and correct answers | `2 + 2 = ?` → `4`; `3 + 2 = ?` → `5`; `6 - 3 = ?` → `3`; `4 + 4 = ?` → `8` |
| Other options | Three different incorrect numeric choices per question |
| Student A first attempt | Three correct answers, one wrong: 30/40 = 75% |
| Student A second attempt | Four correct answers: 40/40 = 100% |
| Student B attempt | One correct answer: 10/40 = 25% |
| Crossword entries | `CAT` / feline; `CAR` / road vehicle; `ART` / creative work; use automatic directions |
| Matching pairs | Sun/day; Moon/night; Rain/water |

Question and option order may be shuffled. Identify answers by their text, not their position. Follow the configured quiz instructions and keep fullscreen active during normal student attempts. Save copied classroom, quiz, result, and grade URLs for the access tests.

## Accounts and permissions

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| AUTH-01 | Register roles | Register Teacher A and Student A with valid details; sign in to each. | Accounts are created and each sees the appropriate teacher/student workflow. |
| AUTH-02 | Registration validation | Try an existing email, invalid email, missing required fields, and mismatched passwords where confirmation is shown. | Invalid submissions show validation; no duplicate account is created. |
| AUTH-03 | Login and logout | Try a wrong password, then correct credentials. Log out and open a protected dashboard URL directly. | Wrong credentials fail; correct credentials work; protected content requires sign-in after logout. |
| AUTH-04 | Role restrictions | As Student A, open `admin.php` and a copied Teacher A quiz-edit/gradebook URL. | Access is rejected or redirected; student cannot change quizzes, users, or grades. |
| AUTH-05 | Classroom ownership | As Teacher B, open Teacher A's copied quiz-edit and gradebook URLs. | Teacher B cannot edit Teacher A's quiz or gradebook. |
| AUTH-06 | Cross-student privacy | As Student B, open Student A's result and grade URLs. If a student ID appears in the URL, try Student A's ID. | Student A's private results/grade breakdown are not disclosed. |

## Classrooms and navigation

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| CLS-01 | Create classroom | Teacher A creates QA Mathematics. Open it and copy its join code. Reload. | Classroom and code persist; teacher controls are available. |
| CLS-02 | Join classroom | Students A and B join with the copied code. Try joining again, then try an invalid code. | Valid membership appears once in the roster; repeat join does not duplicate it; invalid code is rejected. |
| CLS-03 | Classroom privacy | Student A opens QA Private using a copied URL without joining. | Private classroom content and attachments are inaccessible. |
| CLS-04 | Tabs and directory | Open All Classes; open classroom Quizzes, Overview, Materials, and Results views; reload each. | Correct classroom/view remains selected; links and browser Back work. |
| CLS-05 | Search and filters | After creating multiple quizzes, search by title, change game/completion filters, sort, and paginate if available. Include Student B's zero-score attempt from PLAY-03. | Results match filters/sort across pages; zero-score attempts count as completed; student scores belong to the signed-in student. |
| CLS-06 | Due-date reminder | Set a test quiz due date in the past and attempt it as a student. | Date is displayed as a reminder; the due date alone does not block submission. |

## Activity builder and optional AI

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| BLD-01 | Save standard quiz | Teacher A creates QA Addition, adds the four sample questions and choices, reviews, and saves. Reopen Edit. | Saved title, correct answers, points, and settings persist; classroom lists the activity. |
| BLD-02 | Reject invalid questions | Try saving a blank title, empty question, missing choices, and missing correct answer. Correct each before continuing. | Invalid data is rejected with useful feedback; no broken activity is saved. |
| BLD-03 | Question controls | Duplicate a question, change the copy, reorder it, then remove it. Save and reopen. | Only intended questions remain in the saved order with the correct answer attached to each. |
| BLD-04 | Recover local draft | Edit an unsaved test activity, reload, and choose draft recovery. Repeat and discard the draft. | Recovery restores content; discard removes the recoverable draft. A recovered draft still requires explicit saving. |
| BLD-05 | Unsaved preview | Preview an unsaved activity; use desktop/tablet/mobile preview controls; close and continue editing. | Preview renders current content and preserves editor input; it creates neither a classroom activity nor a student score. |
| BLD-06 | Saved teacher preview | Note attempt count/leaderboard, play the saved teacher Preview, then check both again. | Play works; preview adds no student attempt, leaderboard score, or grade. |
| BLD-07 | Text-answer activities | Create Fill in the Blank and Emoji Quiz activities with an answer, alternative, hint, and explanation. Play each using the alternative; repeat with capitalization rules changed. | Answers follow configured alternatives/case rules; hints/explanations display where applicable; responses can be reviewed and edited before submission. |
| BLD-08 | Crossword creation | Create the sample crossword with automatic directions; preview grid/clues and teacher answer key; save. Try invalid short/duplicate answers separately. | Valid grid previews and saves; invalid inputs are rejected. Student play has no teacher answer-key toggle. |
| BLD-09 | Matching images | Create the sample Flip Match; add a valid image to one card face, preview, save, and reopen. Remove that image and save again. | Image appears on its intended face and persists; removal clears only that face while retaining pair text. |
| AI-01 | Generate and review draft (optional) | With AI enabled/configured by an administrator, open Generate with AI, use a nonpersonal lesson TXT/PDF or lesson text, generate, review, and add questions to the editor. Save explicitly. | Progress ends in a usable draft or readable error; generation alone saves nothing. Imported questions match the selected activity type and remain editable. Live calls consume provider quota. |
| AI-02 | AI error handling (optional) | Try empty source/unsupported input. If AI is disabled, open its teacher panel. | Validation or unavailable-state guidance appears; existing editor questions remain intact; no key or raw provider error is exposed. |

## Playing, scores, and learning

For each game-mode case, create a separate small activity as Teacher A, then play as Student A. Avoid switching windows during a live attempt except in PLAY-04.

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| PLAY-01 | Complete known attempt | Start QA Addition, answer three correctly and one incorrectly, finish, select View Results/submit, then reload results. | Exactly one completed attempt persists with 30/40 and 75% accuracy. Review identifies the missed question. |
| PLAY-02 | Retake and isolation | Student A retakes for 40/40. Student B completes for 10/40. Reopen classroom and results under each account. | Attempts remain associated with the correct student; best-score ranking reflects eligible best results; Student A's first result remains available. |
| PLAY-03 | Zero and duplicate submission | Student B completes a separate quiz with all wrong answers. On completion, double-click the final submit control and reload the result. | Zero is a saved completed attempt. Duplicate clicks/reload do not create additional copies of that same submission. |
| PLAY-04 | Fullscreen/focus violations | Use a disposable student quiz. Start fullscreen, leave fullscreen or switch tabs, return and acknowledge/re-enter as prompted. Repeat until the fourth counted violation. | First three counted violations warn; fourth disqualifies and saves zero. Teacher preview and practice are exempt. Unsupported fullscreen must not create false violations; desktop fullscreen denial blocks a required start. |
| PLAY-05 | Time Attack | Answer an item correctly; let another item's timer expire. | Timer counts down; expired item is wrong; final score follows correct responses and configured points. |
| PLAY-06 | Rocket Rush | Answer one item by mouse and another with the displayed number-key shortcut. | Both input methods select the intended answer; progression and final scoring work. |
| PLAY-07 | Memory Flip and Treasure Dive | Play one activity in each mode, including correct and wrong answers. | Themed controls work; feedback/progression are visible; configured scoring is retained. |
| PLAY-08 | Boss Battle | Complete a short Boss Battle with correct and wrong answers. | Boss/shield feedback responds to play; completion reaches results without a stuck screen. |
| PLAY-09 | Mastery Ladder | Configure questions across Easy, Medium, Hard, and Master. Meet the displayed target at one level; fail it in a separate run. | Meeting the target unlocks the next level; failure does not incorrectly unlock it. |
| PLAY-10 | Flip Match | Select a mismatched pair and try a third card immediately. Then match all pairs, including the image pair. | Extra selection is blocked during mismatch feedback; cards reset; matches remain visible; moves/time update and submission works. |
| PLAY-11 | Crossword | Type answers, use arrow keys and Across/Down clues, then submit a mix of correct and wrong words. | Cell navigation/highlighting works; progress updates; server results grade submitted words correctly. |
| PLAY-12 | Quiz version history | Complete a quiz; Teacher A edits an answer or points and saves. Reopen the old result, then take a new attempt. | Old review retains its original quiz/scoring snapshot; new attempt uses the edited version. |
| LEARN-01 | Focus Practice | After an incorrect quiz answer, open learning review/Practice & Progress and run available weak-item practice. Check grades and classroom ranking before/after. | Review/practice relates to recorded weaknesses; training appears in activity history without changing classroom grades or score rankings. |
| LEARN-02 | Adaptive practice | Enable teacher practice inclusion for a multiple-choice quiz with at least two difficulty levels. Start adaptive practice; answer two consecutively correct, then two wrong. Complete the round. | Target difficulty rises/lowers as described in the feedback; nearest available questions may be used; round ends by ten questions or bank exhaustion. Training does not change academic grades. |
| LEARN-03 | XP and achievements | Check Achievements before/after a first eligible assessment; retake the same quiz. | Completion XP/badges reflect stored eligible attempts; repeating a quiz does not duplicate completion XP. XP remains separate from academic grades. |

## Gradebook and academic records

Use a separate simple gradebook for GRD-01 through GRD-05. Keep period grading disabled for the first calculation; test periods separately in GRD-08.

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| GRD-01 | Structure validation | Create two categories: Quizzes 50%, Coursework 50%. Try saving weights totaling 90%, then correct to 100%. | Invalid total is rejected; valid structure persists after reload. |
| GRD-02 | Calculate known grade | Categorize QA Addition as Quizzes, grade maximum 40, highest attempt policy. Add Coursework manual activity maximum 100; give Student A 80. Use Student A's 100% best quiz attempt. | Quizzes = 100%, Coursework = 80%; overall = (100 × 0.5) + (80 × 0.5) = **90.00%**. |
| GRD-03 | Attempt policies | With Student A's 75% first and 100% second attempt only, change the quiz policy to first, latest, average, then highest. | Quiz percentages are 75%, 100%, 87.5%, and 100%. With Coursework 80%, overall grades are **77.50%, 90.00%, 83.75%, 90.00%**. |
| GRD-04 | Publish and protect draft | Publish Student A's 90% result; inspect My Grades as Student A. Teacher changes Coursework to 60 without republishing. Check student view; republish and check again. | Student initially sees 90%; draft edit remains private; republishing changes the visible grade to **80%**. |
| GRD-05 | Hide published grades | Teacher hides the current release; Student A reloads My Grades. | Withdrawn grade is no longer shown as a current published result; teacher/audit history remains available. |
| GRD-06 | Blank versus zero | Add a second Coursework activity maximum 100. Leave it blank with missing scores excluded; then enter 0. Also try -1 and 101. | Blank is ungraded/excluded; explicit zero contributes 0/100; negative/above-maximum values are rejected. Recheck using the explicit missing-as-zero policy separately. |
| GRD-07 | Override, restore, stale saves | Apply a score/overall correction with a reason and inspect breakdown/history; restore automatic calculation. Open the same gradebook in two tabs; save a change in the first, then attempt a stale change in the second. | Override and restoration are explained/audited; underlying attempt remains intact; stale save is rejected instead of silently overwriting the newer change. |
| GRD-08 | Academic periods and lock | Configure academic year/semester and period weights 30/30/40. Populate every required weighted category so period grades are 80, 90, 100. Publish and finalize. Try editing, then reopen/unlock with a reason. | Final subject grade is **92.00%**; incomplete work cannot falsely finalize; locked edits are blocked; reasoned unlock permits corrections, which require review/publication. |
| GRD-09 | Assign terms | Create an unassigned manual activity; use Assign terms, preview a destination, and save. Repeat with scored work and supply the required reason. | Destination changes without losing scores; finalized releases require reopening before reassignment. |
| GRD-10 | Student report and export | Publish/finalize two eligible subjects in the same semester with final grades 80 and 90. Student opens Academic Records/Grade Report, downloads CSV, and opens print preview. | Default unweighted Semester Average is **85** when no required subject is unresolved; only that student's released records appear; CSV and print agree with the displayed records. |
| GRD-11 | Archive and category protection | Try removing a category used by an active activity. Archive a test activity and inspect draft and previous publication. | Used category removal is blocked; archiving excludes the activity from current draft calculations while preserving historical scores/releases. |

## Student submissions and teacher monitoring

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| SUB-01 | Submit and review text | Teacher creates an activity with Accept student text responses enabled. Student A submits text, reloads, then resubmits before grading. Teacher reviews, scores, adds feedback, and publishes. | Saved response survives reload; permitted resubmission updates it; teacher sees correct student response; released feedback/score appears to Student A. |
| SUB-02 | Submission locking | After SUB-01 is graded/release-locked, try changing Student A's response. Create a separate score-only activity and inspect it as Student A. | Locked response cannot be changed; score-only work shows Awaiting grading rather than incorrectly requiring a text submission. |
| SUB-03 | Activity filters | In the student portal filter Activities by subject, component, term, status, year, and semester; clear filters. | Only matching activities appear; clearing restores the list; pending work/deadlines link to the correct activity. |
| SUB-04 | Participants and private reminder | Keep Student A in a live quiz while Teacher A checks Participants. Send a reminder to non-submitting Student B, then attempt an immediate repeat. | Progress/submission states reflect recorded activity; reminder reaches only Student B; repeat reminders are rate limited. |

## Communication, uploads, and profile

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| COM-01 | Announcement and attachment | Teacher posts a test announcement with a PDF. Students open/download it. Try the copied attachment URL as a nonmember. | Announcement/file persists after reload; members can access it; nonmember cannot. |
| COM-02 | Chat and draft | Send chat with Enter, multiline text using Shift+Enter, an HTTPS link, and emoji. Temporarily disconnect the browser network, attempt sending, reconnect, and retry. | Messages reach classroom members without reloading; failed send retains the draft; retry does not duplicate the same send request. |
| COM-03 | Chat files and poll | Attach a valid image/file, inspect image preview/download, and try an unsupported file. Create a poll with 2–6 distinct options; vote and change the vote. | Valid files work; unsupported upload is rejected; one current vote per member is counted. Nonmembers cannot download copied file URLs. |
| COM-04 | Conversation members | Open Members in classroom chat and compare against the roster. | Correct names, roles, and photos appear; private profile fields/email addresses are not exposed by the member list. |
| PRO-01 | Profile photo and details | Save profile details; upload a JPG/PNG/WebP under 2 MB; change details, replace photo, then remove it. Reload between actions. | Details and photo persist independently; updated photo appears in relevant menus/chat; removal works. |
| PRO-02 | Invalid profile upload | Try a nonimage file and a profile image above 2 MB. | Validation rejects both; existing profile/photo remains intact. |

## Administration and display

| ID | Test | Steps | Expected result |
| --- | --- | --- | --- |
| ADM-01 | Account status | Administrator searches for a disposable student account, confirms suspension, and attempts sign-in with that account. Reactivate and retry. | Suspended account cannot continue authenticated use; reactivation restores access; status change is audited. |
| ADM-02 | Audit visibility | Inspect Security, Audit logs, and Grade audit after failed login, account status change, grade correction, and publication cases. | Relevant records show action/actor/time; grade audit remains read-only for administrators. |
| ADM-03 | AI configuration (optional) | Save a test Gemini key/configuration; revisit the form. Save another setting with key blank. Test saved connection; optionally test quiz generation. | Key field remains blank; blank subsequent save preserves the key; connection and generation tests give distinct results; generation test creates no saved classroom quiz. |
| UI-01 | Responsive pages | At desktop, tablet, and approximately 390 px phone width, inspect login, portal, classroom, builder, chat, game, results, and grade report. | Text/controls remain usable; main page has no accidental horizontal overflow; wide grids/tables use their intended scrolling. |
| UI-02 | Keyboard, theme, motion | Navigate forms/dialogs with Tab/Shift+Tab/Enter/Escape where supported. Test both themes and the OS reduced-motion preference. | Focus is visible; controls/dialogs remain usable; text is readable; reduced-motion preference is respected. |
| UI-03 | Refresh and persistence | Log out/in after saved classroom, quiz, response, photo, chat, attempt, and publication changes. Open the saved records again. | Saved records remain correct across sessions; no duplicate records or cross-account data appear. |

## Test run log

Copy this block for each testing session. Use **Pass**, **Fail**, **Blocked**, **Not run**, or **N/A**. Mark optional AI cases N/A when the feature is not configured; explain blocked cases. A pass requires observing every expected result in that case.

- Tester:
- Date/time (Asia/Manila):
- Application URL:
- Git revision/build:
- Database type:
- Browser/version and screen sizes:
- Test accounts/classroom IDs:

| Case ID | Status | Actual result / evidence | Bug reference |
| --- | --- | --- | --- |
| AUTH-01 | Not run | | |
| | | | |

### Bug report template

```text
Case ID / title:
Browser, URL, and account role:
Preconditions / test data:
Exact steps to reproduce:
Expected result:
Actual result:
Screenshot or visible error (omit passwords/API keys):
Reproducible every time? Yes / No:
Impact: blocks core workflow / wrong data or permissions / display issue
```

After testing, restore the status of disposable accounts and note any grades left published. Keep test data clearly labeled so it is not mistaken for real coursework.

Behavior references: [System workflow](SYSTEM_WORKFLOW.md), [Activity builder](ACTIVITY_BUILDER.md), [Grading](GRADING.md), [Academic grading](../ACADEMIC_GRADING.md), [Student portal](../STUDENT_PORTAL.md), and [AI setup](../AI_QUIZ_SETUP.md). This checklist does not claim the tests have been executed.
