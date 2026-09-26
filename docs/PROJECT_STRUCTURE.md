# Project structure

CHALK uses plain PHP and PostgreSQL. Public URLs retain their existing filenames
under /QuizWeb/. Each root page loads its implementation from app/pages/.

| Location | Responsibility |
| --- | --- |
| app/pages/auth/ | Login, registration, logout |
| app/pages/account/ | Profile settings |
| app/pages/dashboard/ | Shared role-aware dashboard |
| app/pages/classrooms/ | Classroom management, joining, protected announcement downloads |
| app/pages/quizzes/ | Builder, game selection, play, submission |
| app/pages/learning/ | Practice, results, saved and archived activities |
| includes/app.php | Bootstrap, database access, authentication, shared domain helpers |
| includes/layout.php | Shared page shell and navigation |
| includes/profile.php | Profile validation and form fields |
| assets/css/ | Shared styles |
| assets/js/ | Browser behavior and quiz interface |
| database/ | PostgreSQL schema |
| data/ | Runtime storage and protected uploads |
| scripts/ | Maintenance and sample-data commands |
| tests/ | Automated checks |
| docker/ | Apache configuration and container startup |

## Adding or changing a page

Edit the implementation in its feature folder. Link to the root entry point,
not app/pages/. Internal pages are blocked from direct Apache HTTP access by
app/.htaccess; PHP can still include them. Apache must honor .htaccess files
(AllowOverride All, as configured in the Docker virtual host).

Resolve shared PHP includes from dirname(__DIR__, 3) within a feature folder.
Keep authentication and authorization checks in each implementation. Moving a
file does not replace those checks. Assets and redirect URLs remain rooted at
/QuizWeb/.

## Verification

Run php tests/profile_test.php for the existing profile checks. The optional
--database flag exercises PostgreSQL persistence inside a rolled-back transaction.
Lint changed PHP files using php -l before deployment. Browser and database
workflow checks still require a running PostgreSQL application environment.

## Remaining extension work

This organization keeps the existing application behavior and database format.
The broader requirements for administrator tools, security auditing, password
reset approval, OAuth mail, notifications, and expanded activity settings require
separate implementation and testing. Existing classroom quizzes and announcements
are stored in JSONB columns; inspect that structure before introducing new tables.
