# Classroom artwork

Created with the built-in `image_gen.imagegen` tool and copied into this project.
All five PNG assets retain their generated transparent backgrounds. The existing
CHALK logo remains `../chalklogo.png`.

| File | Used for |
| --- | --- |
| `hero.png` | Classroom banner |
| `standard.png` | Standard and written/general activity cards |
| `time_attack.png` | Time Attack cards |
| `flip_match.png` | Flip Match and Memory Flip cards |
| `crossword.png` | Crossword cards |

## Generation prompts

Hero prompt:

> Use case: stylized-concept. Asset type: transparent classroom dashboard hero artwork for a dark teal educational web app. Create a polished soft 3D illustration of a small stack of pale mint green schoolbooks with a small globe floating above encircled by two thin cream atomic orbits, a mint glass chemistry flask beside the books, a few sage green leaves and tiny four-point sparkles. Isometric front three-quarter view. Friendly sculpted clay materials, subtle paper grain, pale mint and forest green palette, restrained yellow details. Composition: one compact horizontal arrangement centered, all objects fully in view with generous transparent padding; intended to sit at the right half of a wide classroom banner. No lettering, no text, no logo, no labels, no watermark, no card, no panel, no backdrop. Actual alpha transparent background.

The four card images each used the following template, replacing `{subject}`
with the corresponding subject below. Each asset was generated separately.

> Use case: stylized-concept. Asset type: illustrated quiz-mode artwork for the CHALK classroom dashboard, displayed at 140px. Create {subject}. Premium friendly soft 3D clay illustration with subtly textured paper, rounded forms, mild isometric perspective, tasteful tiny four-point sparkles around the object. Fully visible isolated object group centered with generous padding. Soft studio light, pastel colors, gentle subtle shadows. Palette must match a dark teal mint educational dashboard. No interface, no surrounding card, no backdrop, no logo, no watermark, no text except the requested crossword letters if any. Actual transparent alpha background.

- `standard.png`: a pale mint green checklist sheet with rounded corners, three neat check marks and a yellow pencil leaning diagonally beside it
- `time_attack.png`: a coral pink and red stopwatch tilted at a playful angle, a small ivory paper slip and a few mint speed lines
- `flip_match.png`: two overlapping playful mint green and butter yellow picture cards with a simple mountain and sun on the front card
- `crossword.png`: a compact cluster of six lavender and lilac crossword letter tiles intersecting horizontally and vertically, with A, B, and C on three visible tiles
