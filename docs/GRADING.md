# Classroom grading

Open a classroom's **Grades** tab. Teachers own their classroom gradebook; students get their own **My Grades** page. Administrators have a read-only **Grade audit** tab.

## Teacher workflow

1. Choose **Grading structure** and complete Categories → Weights → Grade Scale → Review. Create 1–8 named categories, reorder them, and assign weights totaling exactly 100%. Choose scale labels/minimums, passing percentage, and a missing-score policy.
2. In a quiz builder's Settings, select a category, an optional grade maximum, and highest/latest/first/average attempt policy. The default is **Not graded / Practice activity**. Grade maxima default to total question points when first saved. Stored game scores and results are unchanged.
3. Add manual activities in **Activities** with name, category, maximum score, and date. **Enter Scores** shows 20 students per page, supports Tab navigation, and saves only those submitted students. Blank is ungraded; 0 is a deliberate zero. Save before changing pages; unsaved changes prompt before navigation.
4. Open a student breakdown to inspect category calculations and attempts, adjust an individual score or overall percentage, or restore automatic calculation. Adjustments retain the actor, time, optional shared note, and original calculation. The underlying attempt/manual score remains intact.
5. **Publish Grades** releases a stored snapshot for all enrolled students, including partial grades if confirmed. Further edits affect only the draft. Republish to release updates, or **Hide Grades** to withdraw the release. Every earlier release remains in the teacher/admin audit history.

## Calculation rules

One shared service in `includes/grading.php` computes every draft and published grade. The method dispatcher currently supports `weighted_categories`, keeping future grading methods independent of controllers.

- Quiz percentages use stored earned/max points from eligible attempts. The selected percentage is multiplied by the item's grade maximum. Highest compares percentages, latest/first use played timestamp then attempt ID, and average averages eligible attempt percentages. Different historical quiz maxima are therefore comparable.
- Preview records, focus/practice modes, and attempts whose quiz snapshot explicitly marks the quiz ungraded are excluded. Legacy attempts without grading metadata remain compatible when their quiz is deliberately categorized by its teacher. A student's calculation uses only that student's attempts for the relevant quiz; controllers supply attempts from the current classroom.
- A category percentage is total earned points divided by total possible points, multiplied by 100. Larger items have more influence within the category. Category percentages are not an unweighted average of item percentages.
- Overall grade is the sum of category percentage × configured weight / 100, divided by the total available category weight, multiplied by 100. With all categories available, this is the ordinary weighted sum. Empty categories are excluded and available weights are normalized; the breakdown explains partial grades.
- By default, blanks contribute neither earned nor possible points. Under the teacher's explicit **zero** rule, assigned blank activities contribute their maximum to possible points and zero earned points. No assigned/scored activities means no grade. Zero-weight categories never influence the overall grade.
- Overall grades round to two decimals before passing/scale classification. Item/category calculations retain full precision; displayed values use two decimals. Weights and entered scores allow at most two decimals. Scores cannot exceed their maximum.
- Item overrides substitute only the item score; overall overrides substitute only the final percentage. Current automatic values and calculations when adjusted remain visible.

## Storage and integrity

`database/schema.sql` and the application's existing startup schema setup add `gradebooks` and `grade_changes`. Each gradebook stores classroom configuration, stable category IDs, manual/quiz items, scores, adjustments, revision, and current release as JSONB. Audit records store previous/new values with classroom, student/item when applicable, actor, action, and timestamp. Publish/unpublish audit records retain full releases.

Grade changes lock the classroom row and update the gradebook and audit entries in one transaction. A revision check rejects stale saves instead of overwriting another window's changes. Quiz saves synchronize their grade item in the same transaction as the existing quiz/version-preservation logic. Audit failures roll back academic changes.

Removing a category used by active activities is blocked until those activities are reassigned or archived. Activities are archived, preserving stored scores, adjustments, attempts, and published history. Archived activities are excluded from the current draft. Switching a graded quiz to practice archives its grade item. Shrinking an item's maximum below a saved/adjusted score is blocked. Bulk user/classroom persistence upserts records without deleting FK-linked academic/audit history.

## Access control

Teacher grade routes enforce the teacher role and classroom ownership. The central mutation service repeats those checks after acquiring the database lock, validates roster membership and item/category ownership, checks CSRF at the controller, and validates all numeric boundaries server-side. Administrators cannot make academic mutations.

The student route is GET-only, requires the student role and classroom enrollment, rejects a foreign `student_id`, and retrieves a release using the authenticated user's ID. It renders only that student's published row and the published configuration. Drafts, other students' rows, audit records, and unpublished releases are never included in the student HTML.

## Verification

Run `php tests/grading_test.php` for weighted/point calculations, blank versus zero, all attempt policies, preview/practice exclusion, legacy attempts, score scaling, adjustments/restoration, teacher/student/admin boundaries, releases, archiving, SQLite persistence, optimistic concurrency, and rollback on audit failure. Run `node tests/grading_workflow_test.cjs` for wizard steps, realtime totals, validation, rename/reorder, custom scale, review, unsaved guards, score changes, and duplicate saves. DOM tests require jsdom installed under `data/qa` as documented in the existing builder test.

Existing activity/profile/chat/security/admin/quiz-version/builder/theme suites remain applicable. PHP syntax and JavaScript syntax checks are also required.

This environment has no `pdo_pgsql` driver and no available in-app browser. PostgreSQL schema/query integration and visual QA at desktop/tablet/mobile sizes must still be verified in the configured XAMPP/PostgreSQL environment. DOM and theme-contrast tests do not substitute for live responsive visual checks.
