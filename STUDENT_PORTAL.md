# Student learning and academic portal

The existing classroom workflow remains the source of subjects, materials, announcements, quizzes, and grading configuration. Student dashboard links redirect to `student_portal.php`. Attendance is outside this implementation.

## Teacher workflow

1. Create a classroom, organize students, and distribute materials using the existing classroom tools.
2. In Gradebook → Activities, add manual activities with instructions and an optional deadline. Enable **Accept student text responses** when an online response is required. Existing score-only activities do not become missing student submissions.
3. In Gradebook → Assign terms, filter by component/current term, select activities, choose a destination, review the preview, and save. Reassignments preserve scores and published snapshots. Recorded work requires a reason; finalized releases must be reopened first. Quiz Builder term settings are synchronized with the assignment.
4. In Enter Scores or Edit Scores, expand submitted responses, record grades, and enter feedback. Feedback is included in the reviewed/published snapshot.
5. Review and publish grades using the existing release workflow. Students see released results; draft score corrections remain private.
6. In Quiz Builder, enable **Allow these multiple-choice questions in adaptive practice** and tag questions with difficulty, an optional topic, and an optional explanation. At least two difficulty levels are needed to start adaptive practice. Text/matching/crossword questions are excluded from this multiple-choice practice bank.

## Student workflow

- Home combines pending work, upcoming deadlines, announcements, published grade updates, and practice links.
- Activities filters by subject, component, term, status, academic year, and semester. Text responses can be submitted/resubmitted until recorded grading or release locking prevents changes. Teacher-recorded activities show **Awaiting grading** rather than missing work.
- Academic Records retains the existing published subject/semester calculations and component views. Grade Report supports CSV download and browser printing/Save as PDF. Published/finalized status is visible; averages require eligible finalized subjects.
- Practice & Progress links existing weak-item practice and new adaptive practice, while retaining the existing academic learning profile. Recent performance displays include saved training results; training is separate from classroom grades.
- Achievements uses saved attempts to calculate persistent XP and badge eligibility. Classroom rankings retain the existing best-score leaderboard, with an additional weekly classroom XP view.

## Adaptive practice

Server-held sessions select unanswered questions nearest the target difficulty. Start at Medium when available. Two consecutive correct answers raise the target one level; two incorrect answers lower it. The nearest remaining level is used when the target bank is exhausted. Rounds stop after ten questions or bank exhaustion.

Each submitted answer is checked on the server with CSRF and expected-step validation. Feedback is shown immediately after a POST/redirect. Saved question snapshots, responses, and difficulty history allow practice review after the session ends. Practice attempts use the existing `focus_training` history and do not affect academic grades or classroom score rankings. Teacher analytics show only questions originating from their classroom.

## Rewards

- Classroom quiz: 20 completion XP plus `floor(best accuracy / 10)`, maximum 30 XP per quiz.
- Practice: 10 XP per distinct completion day.
- First Assessment: complete one classroom assessment.
- Quiz Master: reach 90% on three different classroom quizzes.
- Growing Strong: improve a quiz by 15 percentage points from the first eligible result.
- Practice Habit: complete training on five distinct days.

Rewards are derived from stored attempts; retries cannot duplicate completion XP. Disqualified attempts receive no reward. XP is separate from grades. Weekly classroom XP uses eligible classroom attempts since Monday in the application's Asia/Manila timezone.

Generated badge artwork: `assets/images/badges/achievement-atlas.png`. See its adjacent README for the exact built-in image-generation prompt.

## Storage and accessibility

Both database schema files add the idempotent `activity_submissions` table. Existing startup schema initialization applies it without importing demo accounts. Activity settings, teacher feedback, and practice-enabled flags use existing JSON records.

The portal inherits CHALK's theme with compact type and responsive tables/cards. Adaptive practice works without fullscreen. Existing quiz games use fullscreen when supported and focus monitoring otherwise; unsupported fullscreen does not generate false violations. Desktop permission denial still blocks fullscreen-required start.

## Validation

Use the existing PHP executable or `php`:

```text
php tests/student_portal_test.php
php tests/student_portal_database_test.php
node tests/quiz_accessibility_test.js
php tests/academic_test.php
php tests/grading_test.php
php tests/mysql_test.php --database
```

The database test creates and removes a randomly named isolated MySQL database. It never modifies the normal QuizWeb database. Browser/device visual validation must also be performed when a browser connection is available.
