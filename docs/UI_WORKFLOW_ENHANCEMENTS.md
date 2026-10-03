# UI and workflow enhancements

## Audit and compatibility

QuizWeb uses plain PHP, PostgreSQL, JSONB classroom quizzes, and shared CSS/JavaScript.
Root PHP entry points retain existing public URLs. The existing modes, question
formats, grading, classroom ownership checks, chat, profile forms, and theme tokens
are reused. No quiz data migration is needed.

The audit found a vertical question editor, modal-only unsaved preview, missing
administrator workflows, result access based only on class membership, several
forms without CSRF protection, and unbounded list rendering. Students could read
another member's detailed result by changing its ID. That access is now denied.

## Implemented workflows

- Builder: Basics → Questions → Settings → Review & Publish, with local state
  retained between steps. One question is visible at a time; the navigator shows
  completion, with add, confirmed remove, duplicate, and move up/down actions.
- Existing quiz edits preload the same builder. Missing edit IDs are rejected.
  Backend validation and failed saves retain the posted draft. Client and server
  checks identify invalid items; mode-specific rules remain authoritative on PHP.
  Editing locks the classroom and commits only that class. Existing attempts
  receive their original quiz version inside answer metadata before an edit commits;
  new runs capture their version on entry and retain it when saved. Reordering or
  changing modes therefore preserves historical grading/feedback and in-progress
  gameplay. Historical edits made before version capture cannot be reconstructed.
- Live preview reflects unsaved content on every step. It can collapse on desktop;
  tablet/mobile open it as a separate panel. Full Play Test runs the existing game
  renderer with preview enabled, inside an iframe allowing scripts only. Forms,
  same-origin access, popups, and network writes are blocked by sandbox/CSP.
  Preview POST requests validate content and return JSON before persistence.
- Settings expose the existing Mastery Ladder target (50–100%), persisted using
  the existing `mastery_threshold` field. Other gameplay rules are described rather
  than represented by nonfunctional switches. Per-question points/levels remain
  in the editor; deadlines remain in Basics. Saving publishes as before; there is
  no draft/publish lifecycle or server autosave.
- Students have “What's next” and a separate “Progress & study plan” view. Deadline
  status links start the right quiz or open the student's result. Detailed results
  and the classroom attempt list show only the current student's own records.
  Existing shared rankings remain visible. Lists of classes, activities, quizzes,
  and result history are paginated; class IDs and section parameters are retained.
- Teachers retain classroom materials, chat, roster, and analytics. The classroom
  scoreboard links to detailed results. Dashboard mode descriptions are collapsed,
  with a short classroom list linking to the complete directory.
- Administration includes overview, database-paginated user search/role filters,
  expandable account details, class summaries, failed sign-ins, and audit logs.
  Suspension/reactivation require deliberate checkbox confirmation plus CSRF.
  Backend role checks protect both the page and mutation function. Administrator
  accounts cannot be suspended here. A status change and its audit record commit
  together or roll back together. Suspended users lose access on their next request.
- Login rotates the session ID. Login, class creation/join, classroom posting,
  builder saves, and all game submissions have CSRF checks. Live student runs carry
  a session-bound token; repeated submission of that run redirects to its existing
  result instead of adding another attempt. Up to 30 run tokens are retained in
  the session; very old tabs can expire and must restart. Scoring rules are unchanged.

## Database and administrator setup

The app applies idempotent additions during database initialization, consistent
with its existing schema setup: `users.account_status` defaults to `active`, and
`audit_logs` stores actor/target IDs, action, and timestamp. `database/schema.sql`
also contains these additions. Logs contain no passwords, password hashes, tokens,
or attempted credentials. Historical failed sign-ins cannot be recovered.

To provision an administrator, configure `QUIZWEB_ADMIN_EMAIL` and
`QUIZWEB_ADMIN_PASSWORD` in a trusted CLI environment, then run:

```text
php scripts/create_admin.php
```

The password must have at least 12 characters. The CLI-only command creates a new
account, refuses an existing email, and does not expose administrative signup on
the public registration page. Remove the provisioning variables afterward.
Existing admin accounts can use the normal login page.

Account deletion, password-reset requests, OAuth mail, notification systems, and
global settings have no existing backend and are intentionally omitted.

## Verification performed

```text
php tests/activity_test.php
php tests/profile_test.php
php tests/chat_test.php
php tests/security_test.php
php tests/admin_test.php
php tests/quiz_version_test.php
npm install --prefix data/qa --cache data/qa-cache jsdom --no-save --no-package-lock --ignore-scripts
node tests/builder_workflow_test.cjs
node tests/theme_test.cjs
```

The DOM test exercises step/state preservation, question actions, switching modes,
validation routing, unsaved guards, preview sandbox/CSP configuration, save POST
behavior, and the actual game renderers completing previews in all eleven modes.
Crossword uses the actual PHP layout preparation. Set `PHP_BINARY` for a PHP binary
outside PATH; the test also recognizes the local XAMPP PHP installation.

The admin test uses an in-memory SQLite fixture to check persistence, role denial,
protected administrators, successful audit writes, and rollback when logging fails.
It strips PostgreSQL's `FOR UPDATE` solely in that fixture. It does not verify
PostgreSQL locking, schema migration, or live sessions. PHP lint, JavaScript syntax,
and whitespace checks were run on modified code.
Theme checks verify light/dark text and semantic token contrast against both
card surfaces, plus primary-button text contrast (at least 4.5:1).
The quiz-version test checks legacy attempt preservation during reordering,
subsequent edits, ownership denial, unique new quiz IDs, and atomic save rollback.
Student attempt insertion now writes one record under a database lock instead of
rewriting the attempt table, preserving concurrently saved and versioned records.
Registration and classroom creation also insert only their new record, with ID
allocation protected by a table lock; ordinary classroom saves update one row.

## Live QA still required

At implementation time no browser was available, localhost was unreachable, and
the installed PHP lacked `pdo_pgsql`. Live login, database-backed publishing/editing,
actual HTTP permission checks, and visual/responsive verification could not run.
Automated DOM tests are not a substitute for these checks.

On a working PostgreSQL/PHP server and browser:

1. Teacher: log in; create/open a class; select each mode; fill the four builder
   steps; add, duplicate, delete, reorder, preview, and publish; edit/re-save; review
   a student result. Verify the classroom and quiz IDs through redirects.
2. Student: log in; join/open a class; use a deadline action; complete each mode;
   review its result; inspect progress. Retry a captured submit to confirm only one
   attempt is stored for that run. Check integrity/focus rules on real student runs.
3. Admin: provision/sign in; search/filter/page users; view an account; suspend and
   reactivate it; verify its existing session loses access; review security/audit
   logs. Check atomic audit behavior against PostgreSQL.
4. Direct URL/POST checks: student cannot create quizzes, see peers' results, or use
   admin tools; teachers cannot open unrelated classes/results or admin tools;
   admins have no implicit student/teacher academic access. Reject missing/invalid
   CSRF and run tokens without writes.
5. Compare attempts, scores, and classroom analytics before and after unsaved/saved
   teacher previews. Neither preview path may add any record. Inspect network and
   console output to ensure sandboxed assets load and blocked writes stay blocked.
6. At 1440, 1280, 768, and 390 pixels, check light/dark contrast, overflowing controls,
   keyboard focus, native dialog focus/close behavior, sticky actions, and mobile
   preview layout. Exercise browser back, refresh, and canceled leave warnings.

Do not label the deployment production-verified until this checklist passes.
