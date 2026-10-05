# Activity builder

Teachers: open a classroom, select **Create Quiz Game**, and choose a template.
The main picker includes Standard Quiz, Crossword, Flip Match, Fill in the Blank,
Emoji Quiz, and the existing Mastery Ladder. Older themed multiple-choice games
are under **More game styles**. Mastery Ladder retains its existing rule-based
level progression; it is not a new adaptive engine.

## Preview before saving

Enter a title and question content, then select **Preview Activity** in the
question toolbar. This validates the current form on the server without saving a
quiz or recording an attempt. Close the preview to keep editing the same form.

Crossword previews use the same deterministic generator as saving. They show the
grid, Across/Down clues, and a toggleable answer key. Cells can be typed into.
Automatic direction helps connect words; teachers can also choose Horizontal or
Vertical for individual entries. Use at least three distinct 3–15-letter words
with shared letters. Unplaceable grids return validation errors before saving.

Other unsaved previews show prompts, choices or answer inputs, hints, and optional
answer keys. Flip Match shows the pair content for review. After saving, the
classroom's **Preview** action launches the playable teacher version without
recording a score.

## New activity types

- Fill in the Blank and Emoji Quiz use teacher-defined text answers, optional
  alternatives, capitalization settings, hints, and explanations. Students review
  their responses before submission; the backend grades their answers.
- Flip Match uses 2–12 distinct term/definition pairs with shuffled cards,
  pair matching, a move counter, elapsed time, and final submission.
- New activity results use the existing PostgreSQL attempts storage, results,
  learning analytics, and class leaderboard paths. No duplicate tables were added.

Classroom Overview, Quizzes, Materials, and Results are separate URL-addressable
views using the tab query parameter. All Classes opens the dedicated classes.php
directory. Saving a quiz returns to the classroom's Quizzes view.

## Checks and current limits

Run php tests/activity_test.php and php tests/profile_test.php for backend checks.
The browser fixtures test activity interactions, teacher preview behavior, and
representative card layouts without database access. tests/browser_check.ps1 can
run a fixture with an exact viewport in a disposable Chrome profile.

The local PostgreSQL server was unavailable during this change, so full database
save/play/reload testing remains outstanding. Earlier browser fixtures passed;
the final exact-phone-viewport rerun was blocked by tool approval availability.

This change covers activity types, previews, navigation, and shared layout. It
does not complete the entire broader brief: administration, password-reset
approval, OAuth mail, notifications, and additional assessment settings still
require their own implementations and verification.

## Matching images and student quiz security

In Flip Match, open Questions and use First card image and Matching card image.
You can put an image on one side or both. Keep descriptive text labels in the
prompt and answer fields. Preview the cards, then Save Activity. PNG, JPEG, and
WebP files up to 5 MB are resized and compressed in the browser; saved faces must
be at most 96 KB each, with at most 1 MB of embedded image data for the activity.
Remove image clears only that card face. Images survive draft recovery,
duplication, saved edits, and Render redeployments because they live in PostgreSQL.

All live student activities now require fullscreen before starting and share
three warnings. The fourth violation saves zero. Teacher previews are exempt.
Screenshot shortcuts count when the browser receives them; operating-system
captures cannot be reliably detected. Questions and multiple-choice options are
shuffled for each student run, with server grading tied to the saved shuffled
version. Mastery difficulty progression and crossword grid positions stay intact.
