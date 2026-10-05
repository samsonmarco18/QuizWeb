# QuizWeb verification — 2026-10-03

## Result

All 10 automated suites pass. PHP syntax checks pass for 65 files; JavaScript syntax checks pass for 7 asset scripts. `git diff --check` reports no whitespace errors.

The initial rerun found a builder defect: a multiple-choice question with Option C selected converted to a text-answer question using Option A. Fixed the answer initialization and mode conversion to use the currently selected correct option. Added regression coverage for edited options and correct-answer selection, and for preserving explicitly entered text answers across mode changes. Reran the complete JavaScript suites and the expanded builder suite successfully after the fix.

## Passing suites

| Suite | Checks |
| --- | --- |
| `tests/activity_test.php` | Activity validation, answer grading/review, matching, crossword layout |
| `tests/admin_test.php` | Suspension/reactivation, roles, protected accounts, audit writes, transactional rollback |
| `tests/chat_test.php` | Chat validation, classroom permissions, voting, privacy |
| `tests/grading_test.php` | Weighted totals, earned/possible ratios, blank versus zero, missing rules, all attempt policies, preview/practice exclusion, legacy attempts, quiz score scaling, overrides/restoration, release privacy, ownership, archiving, persistence, stale revisions, audit rollback |
| `tests/profile_test.php` | Profile validation and compatibility |
| `tests/quiz_version_test.php` | Historical snapshots, reordered-question feedback, quiz ownership/ID allocation, atomic save rollback |
| `tests/security_test.php` | Role boundaries, private result access, admin navigation, legacy account status, pagination |
| `tests/builder_workflow_test.cjs` | Steps, editing, duplicate/reorder, mode conversion, draft preservation, grading settings, validation, unsaved guards, all 11 preview renderers, no preview writes, save validation |
| `tests/grading_workflow_test.cjs` | Wizard steps, realtime weight totals, invalid totals, category rename/reorder, scale customization, review, unsaved guards, zero input, duplicate-save prevention |
| `tests/theme_test.cjs` | Light/dark theme token contrast |

PHP persistence tests use isolated in-memory SQLite fixtures. JavaScript UI tests use jsdom and exercise the actual application scripts. They do not modify classroom data in the live database.

## Live checks still blocked

- `http://localhost/QuizWeb/` is unreachable; the HTTP request failed to connect.
- The available PHP runtime has PDO/MySQL/SQLite support but lacks `pdo_pgsql`, which this application requires.
- Browser connection and documented discovery returned no available browser.

Consequently, this run does **not** establish live PostgreSQL integration, actual sign-in-to-publication flows over HTTP, or rendered desktop/tablet/mobile layouts. Those checks require the running application, PostgreSQL-enabled PHP, and an available browser. Theme contrast and DOM behavior passed, but responsive visual correctness remains unverified.


## October 5, 2026: activity workflow and game experience

This section supersedes the earlier browser-availability limitation above. The in-app browser was unavailable, but the project's headless Chrome runner was available and used against a temporary PHP fixture server. Authenticated PostgreSQL flows are still blocked.

### Changes

- The builder separates Game, Details, Questions, Settings, Grading, and Review. Saving remains an explicit server-validated action. One question is expanded at a time, with duplicate/reorder actions, local draft recovery, selected game cards, and mechanics previews.
- Unsaved previews use the actual game renderers in a sandbox that blocks forms and network writes. Desktop, Tablet, and Mobile controls resize the iframe; a viewport meta tag makes responsive rendering work inside it.
- Shared instructions, compact answer feedback, countdown states, visible health bars, a mastery ladder, and rocket/dive journeys improve feedback without changing points or grade calculations. Completion precedes the final View Results submission action.
- Flip Match blocks extra selections during mismatches, resets after 900 ms, retains matched cards, and displays moves. Written activities retain answer review/edit actions. Crossword adds clue tabs, highlighted words, directional typing, filled-word progress, and a teacher-only key toggle.
- Student crossword data now contains shape and word lengths instead of answer keys. Preview retains keys; server scoring and historical snapshots retain the original quiz. Focus Practice no longer activates fullscreen disqualification.
- Results provide answer-review navigation, relevant mode statistics, and a grade-policy explanation for categorized activities.
- Layouts wrap controls and long text, keep game scrolling in the page rather than nested fixed-height panels, bound crossword scrolling, provide visible focus, and honor reduced motion. Theme colors continue using the existing contrast-tested tokens.

### Files changed

| Area | Files |
| --- | --- |
| Builder | app/pages/quizzes/quiz_builder.php; assets/js/builder-workflow.js; assets/js/builder-preview.js |
| Games | app/pages/quizzes/play.php; assets/js/game.js; assets/js/activity-game.js; new assets/js/game-experience.js; new assets/css/game-experience.css |
| Results/practice | app/pages/learning/results.php; app/pages/learning/practice.php |
| Shared services | includes/activity.php; includes/layout.php; includes/app.php; includes/admin.php; includes/grading.php |
| Database/admin setup | database/schema.sql; scripts/create_admin.php; new scripts/create_admin.ps1 |
| Tests | tests/builder_workflow_test.cjs; tests/browser_check.ps1; new tests/game_experience_test.cjs; new tests/crossword_privacy_test.php; new tests/schema_test.php; new tests/experience_fixture.cjs |
| Documentation | README.md; docs/SYSTEM_WORKFLOW.md; this report |

### Verification

Nine PHP suites pass: activity, profile, grading, security, admin, quiz-version, chat, crossword privacy, and main-schema checks. Four JavaScript suites pass: builder workflow, enhanced game interactions, grading workflow, and theme contrast. Changed PHP files pass lint; all asset JavaScript files pass syntax checks; git diff --check passes.

The enhanced game suite exercises locked responses, unchanged configured points despite visual progression, completion before submission, duplicate prevention, countdown warning/urgent/timeout states, mastery unlock/failure, written review/edit, matching selection locks and automatic resets, crossword key isolation and progress during timer ticks, and Focus Practice focus exemption. Builder tests exercise all six steps, recovery/discard, selected cards, original editing/conversion/version behavior, preview isolation, and duplicate saves.

The Chrome matrix passes 266 cases with reduced motion and 266 with motion enabled: seven viewports ? two themes ? nineteen views/states. Sizes are 360?800, 390?844, 430?932, 768?1024, 1024?768, 1366?768, and 1440?900. Views include picker, each builder step, preview, all eleven game modes, and representative results. Checks detect page overflow and controls outside the viewport, while allowing the question navigator and crossword to scroll within controlled containers. Screenshots were inspected at phone, tablet, and desktop sizes; this found and corrected nested scrolling, a blank preview caused by script initialization, and an old feedback badge overlapping long questions.

The three existing written-answer, matching, and isolated-preview browser fixtures also pass at 390?844. QA HTML, screenshots, and JSON reports stay under ignored data/qa. These fixtures run actual JavaScript and CSS; they are not authenticated classroom database sessions. Persistence/concurrency tests use SQLite fixtures, not a live PostgreSQL server.

### Database and administrator access

There are no new tables for these game enhancements. database/schema.sql is now the single source of PostgreSQL table/index definitions and compatible profile/chat/account-status column upgrades. Startup reads only the schema section; the former admin and grading setup functions delegate to it.

Explicit import also creates a sample administrator if the email is unused. The bcrypt hash is verified using PHP password_verify. Import does not reset an existing account. A users-table lock protects the manually allocated ID, and the account and its audit entry are inserted in one transaction.

1. Create/select the quizweb database in pgAdmin.
2. Open Query Tool, load database/schema.sql, and execute it.
3. Configure the PostgreSQL connection variables and enable pdo_pgsql in Apache's PHP configuration.
4. Open http://localhost/QuizWeb/login.php.
5. Sign in with admin@chalk.local and ChalkAdmin!2026; the admin area is /QuizWeb/admin.php.

A custom administrator can also be provisioned with powershell -File scripts/create_admin.ps1, which prompts for a password and never resets an existing email.

### Unverified work and remaining limits

The local PostgreSQL connection to 127.0.0.1:5432 was refused and the normal localhost application was unreachable. The installed PHP CLI does have a loadable pdo_pgsql DLL, which was enabled for the provisioning attempt, but the server was still absent. Consequently, no live admin account was created in this session, and importing the SQL, authenticated sign-in, classroom save/play/reload, uploads, and grade publication must still be verified against the intended PostgreSQL deployment.

The sample credentials become usable after importing the SQL into that deployment. Existing deadlines remain reminders, retakes remain unlimited, and no unsupported settings or draft publication state were introduced. Multiple-choice answer indexes and browser-based focus monitoring retain their existing limitations; this work does not add complete proctoring or a server-enforced mastery progression engine. Local draft recovery depends on browser storage and is retained until discarded, including after successful saves. The main SQL file cannot start or install a PostgreSQL server.
