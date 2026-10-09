# Academic grades

Teachers open a classroom's Grades page to configure grading, inspect student
breakdowns, enter assessment scores, and release grades. Students open My Grades
to see their own released grades grouped by academic year and semester.

## Setup

1. Set the subject code, section, academic year, and semester in Class Settings.
2. Choose Balanced, Coursework focused, Exam focused, or a custom grading system.
   Configure category weights and Prelim, Midterm, and Finals weights. Each set
   must total 100%. The example period weights are 30%, 30%, and 40%.
3. Assign graded quizzes and manual activities to a category and period. Practice
   quizzes do not contribute to academic grades.
4. Enter manual scores or use recorded quiz attempts. Teachers can correct an
   assessment with a reason; final subject grades are calculated automatically.
5. Review the calculation, publish the release, then finalize and lock it once
   all required periods and weighted categories are complete. Changes to a locked
   gradebook require unlocking with a reason and reviewing/publishing again.

Category results use earned points divided by possible points. Category weights
produce period grades; period weights produce the subject's final grade. Partial
drafts normalize the available weights and are clearly marked incomplete.
Students see published snapshots, so later draft edits do not silently change a
release. Corrections, configuration changes, publication, and locking are audited.

## Semester Average

The default result is the simple, unweighted mean of finalized final subject
grades. A final grade of 80 and another of 90 produce a Semester Average of 85.
An unresolved required subject keeps the semester result Pending. Administrators
can configure academic terms and incomplete/withdrawn subject policies. Optional
institutional GPA requires an explicitly configured scale and credit units.

## Existing installations

The database adds separate academic metadata, settings, quiz progress, and reminder
tables. Startup creates missing tables; the PostgreSQL and MySQL schemas also
include them. Existing classrooms and category-based gradebooks retain their
grades and publications. They initially display an Unassigned academic term.

Use Class Settings to assign a term. To enable period grading, open Grading
Structure, configure the three periods, explicitly assign each older activity to
a period, and provide a correction reason when existing grades are affected.
Existing activities are never silently assigned to arbitrary periods.

View Participants uses recorded attempts and active quiz heartbeats. Teachers can
send private in-app reminders to students without submissions. Recent reminders
are rate limited; students receive only reminders addressed to them.

## Verification

Core checks: `php tests/grading_test.php`, `php tests/academic_test.php`,
`node tests/grading_workflow_test.cjs`, and `node tests/academic_ui_test.cjs`.

Database checks: `php tests/mysql_test.php --database`.
Live request checks: `python tests/academic_http_test.py`.
Set connection environment variables for a local test database server; both live
suites create and remove isolated test databases instead of using application
data. `QUIZWEB_TEST_PHP` can select the PHP executable for the HTTP suite.

Live request tests cover teacher pages, student privacy, term grouping and mean,
review/release/finalization, correction reasons, locking, private reminders,
quiz progress/submissions/retakes, CSV export, and ownership/role restrictions.
The interactive browser was unavailable during this change, so a final visual
comparison with the reference screenshots remains a manual check.
