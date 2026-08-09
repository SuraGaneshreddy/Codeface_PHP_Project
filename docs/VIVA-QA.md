# Codeface — Viva / Q&A Preparation (based on the demo PPT)

Grouped by topic. Each answer is short enough to say aloud in 15–30 seconds.

---

## A. Project & Problem Statement

**Q1. What is Codeface, in one line?**
A self-hostable coding practice and collaboration platform — learn a language, solve problems,
and pair-program live — built with vanilla PHP and MySQL, no frameworks.

**Q2. What problem does it solve?**
Three gaps: learning is passive (tutorials never build fluency), practice is fragmented (one site
for problems, another for language help, a third for partners), and collaboration is absent
(no easy shared-editor-with-a-friend). Codeface puts all three in one platform.

**Q3. Why is your solution different from LeetCode / HackerRank?**
Those are closed, heavy services aimed at interview prep. Codeface is (1) learn + practice +
collaboration in one loop, (2) open and tiny — a student can read the entire codebase, and
(3) self-hostable on any shared LAMP host with zero build tooling.

**Q4. Who are the users?**
Students learning their first language, developers preparing for interviews, and small groups
or classes who want to solve problems together in real time.

---

## B. PHP / Backend Questions

**Q5. Why vanilla PHP — why not Laravel / Django / Node?**
Two reasons: deployment reality (a ZIP uploaded to shared hosting just runs — no Composer, no
build step, no daemons) and the project's goal — the code itself is a teaching artifact, so it
must be readable end-to-end by a student. Frameworks add magic that hides exactly the parts
we want visible.

**Q6. How do pages and APIs coexist in your app?**
Pages are server-rendered PHP files that output HTML directly. State-changing actions go to
JSON endpoints under `api/` (submissions, learn progress, room sync, matchmaking), which
return JSON and proper HTTP status codes.

**Q7. How do you connect PHP to MySQL?**
Through **PDO**, with a dual-driver wrapper: MySQL via `CODEFACE_DB_DRIVER=mysql` (host, user,
password env vars) and a zero-setup SQLite file as the default. The same code runs on both;
only SQL dialect branches (e.g. upsert syntax) differ.

**Q8. How do you keep the database seed up to date as content grows?**
A `meta` table stores `schema_version`. On bootstrap the app compares it with
`CF_SCHEMA_VERSION` in code; when code is newer, it upserts problems/lessons (content never
duplicates) and prunes stale rows. Fresh databases seed themselves on first request.

**Q9. How does user authentication work?**
Session-based auth: login verifies with `password_verify()` and stores the user id in
`$_SESSION`. Every state-changing request also carries a session-bound CSRF token, verified
with `hash_equals()`.

---

## C. MySQL / Database Questions

**Q10. How many tables, and how is the schema organized?**
13 tables in four clusters: identity (`users`, `matchmaking_queue`), practice (`problems`
— including per-user 🤖 AI-generated rows via `ai_user_id` — and `submissions`), learn
(`learn_lessons`, `learn_progress`), social (`rooms`, `room_pads`, `room_members`,
`chat_messages`), pro-mode (`lab_progress`, `refactor_runs`), plus a standalone `meta`.

**Q11. Explain one many-to-many relationship in your schema.**
Room membership: `room_members(room_id, user_id)` with a **composite primary key** on the pair
and FKs cascading to `rooms` and `users`. The same composite-key pattern is reused by
`learn_progress(user_id, lesson_id)` and `lab_progress(user_id, lab_slug)`.

**Q12. Why ON DELETE CASCADE vs SET NULL — give an example of each.**
CASCADE where the child is meaningless alone: delete a room → its pads, members, chat go too.
SET NULL where the child should survive: delete a user → their rooms and chat messages stay,
`owner_id`/`user_id` become NULL, so history isn't destroyed.

**Q13. Is `learn_lessons.problem_slug` a foreign key? Why / why not?**
No — it's a deliberate **logical** reference (dashed line in the ER diagram): lessons are
content, reseeded independently of problems, so the link is validated by tooling instead of an
FK. All linking slugs are machine-checked against the problem bank (0 dangling).

**Q14. Why utf8mb4 charset?**
`utf8` in MySQL is 3-byte only — it can't store emoji or some scripts. `utf8mb4` is real UTF-8;
lesson text and chat need it.

**Q15. What indexes exist beyond primary keys?**
Unique keys (`users.username`, `users.email`, `problems.slug`, `rooms.code`, lesson
`track+slug` composite, queue `user_id`) for both integrity and lookup speed, plus secondary
indexes on hot paths (`submissions.user_id`, `learn_lessons(track, position)`, chat by room).

---

## D. Architecture & Design Decisions

**Q16. Your boldest design decision?**
**No server-side code execution** — ever. JavaScript/TypeScript run in a browser Web Worker,
Python runs in Pyodide (real CPython compiled to WASM). The server never touches untrusted
code, which removes the whole class of sandbox-escape risks and lets us run on shared hosting.

**Q17. If code never runs on the server, how are the other 9 languages judged?**
They aren't executed in-browser; instead every problem ships per-language starter code, public
test cases, and a full reference solution, so learners can work locally with an honest harness.
This is a documented, deliberate limitation.

**Q18. How do 12 language versions of every problem stay consistent?**
A single source of truth: each problem defines one signature (name, params, return type) in a
neutral form, and a generator emits idiomatic starters per language. We machine-audited all
526 × 12 starters — zero defects.

**Q19. How does room sync work without websockets?**
Short-polling: each pad row carries a monotonically increasing `version`; clients poll the room
state endpoint, send edits with their last version, and the server accepts/orchestrates updates.
Latency is ~1–2 seconds — a pragmatic vanilla-PHP trade-off.

**Q20. How are demo resets handled?**
Content (problems/lessons) is versioned seed data. Deleting the SQLite file (or recreating the
MySQL database) re-seeds everything automatically on next request — that's how demos always
start clean.

---

## E. Security Questions

**Q21. How do you prevent SQL injection?**
Exclusively PDO **prepared statements** with bound parameters — no user input is ever
concatenated into SQL. Integer ids are additionally cast before use.

**Q22. How do you prevent XSS?**
Two layers: every user value is escaped with `e()` (`htmlspecialchars` with ENT_QUOTES) at
output, and authored rich content passes through `allow_html()`, which whitelists a small set
of formatting tags and strips everything else.

**Q23. How does CSRF protection work?**
A random token is stored in the session and embedded in forms / sent as the `X-CSRF-Token`
header for fetch calls. State-changing endpoints require it and compare using `hash_equals()`
to avoid timing attacks.

**Q24. How are passwords stored?**
With `password_hash()` (bcrypt) and verified with `password_verify()`. Plaintext never touches
the database; the seeded demo users are hashed at seed time too.

**Q25. The biggest remaining security concern?**
Polling-based sync means room codes are the only access control for rooms — they're
unguessable (6 chars from a 31-symbol alphabet) but revocable membership/expiry would harden
this further.

---

## F. Feature Questions

**Q26. Walk me through solving a problem.**
Pick a problem → Monaco editor loads the starter for your language → run tests → for JS/TS/Python
the verdict appears instantly from the in-browser judge → a passing submission is stored and,
if it's your first pass on that problem, its points are added to your rating.

**Q26b. What does the profile page show?**
Three things: (1) identity you control — the avatar carries an ✏️ badge that uploads a
profile photo (validated by `finfo` mime + `getimagesize`, ≤ 2 MB, stored in `database/data/avatars/`
and served through `frontend/avatar.php` because `database/data/` is `.htaccess`-denied for the sqlite file;
the initials-plus-hue circle remains the fallback), and an inline ✏️ edit for display name
& bio; (2) a **journey card** — every section with live status: Practice (solved/526, plus
any AI-made problems), Pro Labs and Refactor Gym (done/6, or the 🔒 10-solve gate state),
and **Learn broken down per language** with not-started / ongoing / complete ✓ chips and
progress bars — so "where am I in this course?" is one glance; (3) the classic solved-list,
recent submissions and rating stats. Someone else's profile renders the same journey
read-only — no edit affordances.

**Q27. How does the rating system work?**
Start at 1200. First-time solve awards the problem's points (easy 10 / medium 20 / hard 35) —
a SQL check ensures re-solves never double-award. Leaderboard sorts by rating, tie-broken by
earliest solve.

**Q28. What's special about the Learn section?**
15 tracks × 16 scenario-driven lessons (the 12 languages plus SQL, Bash, HTML & CSS — 240
lessons), split into four levels of four: 🌱 Beginner → 🌿 Intermediate → 🌳 Advance → 🏆 Pro.
**Levels hard-lock sequentially**: Intermediate stays locked until that user finishes all 4
Beginner lessons, Advance until Intermediate, Pro until Pro's previous level — enforced
server-side on the track page, level page, lesson page *and* the completion API. Every
example is real-world (no foo/bar), JS/Python lessons are editable and runnable in-page, every
lesson links to a matching practice problem, and the runnable examples are machine-verified
against real interpreters/compilers (Node, CPython, and strict `tsc` for TypeScript).

**Q28b. Why are Pro Labs and the Refactor Gym locked behind 10 practice solves?**
Same prerequisite philosophy, one level up the ladder: multi-file legacy repos and
behavior-preserving refactors assume you can already solve small problems. Until a user has
**10 distinct passed problems** in Practice, every Labs/Refactor page (and each individual
environment/repo URL) renders a styled 403 lock wall that spells out the rule, shows live
progress (X/10 with a bar and "N to go") and deep-links into Practice — and both completion
APIs return 403 for hand-crafted requests. The check is one shared helper
(`cf_practice_gate()` / `cf_solved_problems_count()`), so the 10th solve unlocks instantly,
no admin action.

**Q29. How does matchmaking work?**
A queue table holds one row per waiting user (unique constraint) with language + difficulty.
Pollers in the queue get paired by compatible rows; a fresh room is created and both users are
dropped into it.

**Q30. What happens when a user finishes everything?**
The AI practice generator steps in: clearing all 526 problems, all 6 labs or all 6 refactor
challenges automatically generates a fresh personalized set of 10 more for that user —
new problems become real rows they solve through the exact same solver/submission/rating
machinery (marked by `problems.ai_user_id`), while labs and refactor challenges are
regenerated deterministically from their parameterized templates. It repeats indefinitely,
so the board never runs dry. It's an *offline* engine (see README deviation 7): every
generated item is machine-proven before it can be seen — reference solutions must pass all
16 generated tests per problem, AI lab repos must fail ≥1 task as shipped and pass fully
after the staff fix, and AI refactor messes must be green-but-ugly with the staff cleanup
scoring ≥90/100 — enforced by a Node harness over two full batches (1,084 assertions),
plus the shipped end-to-end suite `tools/integration-test.sh` — 59 HTTP assertions per
database driver (practice gate → spawn → idempotency → batch 2 → ownership 404s → unlock
gates → tamper checks).

---

## G. Testing & Process

**Q31. How did you verify 526 problems aren't broken?**
Every reference solution was executed in Node against every declared test case — 2001/2001
passed; duplicate-slug scans, database re-seeding on both SQLite and MySQL, and a
starter-generation audit across 12 languages all ran clean. The AI generator has its own
rung (`tools/verify-ai-dump.php` + `tools/verify-ai.js`): 1,084 assertions over two batches;
and `tools/integration-test.sh` re-proves the whole treadmill + practice gate + profile
feature over HTTP — 59/59 on SQLite, 59/59 on MySQL.

**Q32. What would you improve given more time?**
Isolated server-side judging (a microVM/container per run) so all 12 languages get instant
verdicts, OT/CRDT or WebSocket room sync, a 600+ problem bank, timed contest mode, and i18n.

**Q33. Biggest technical challenge faced?**
Keeping content and code provably consistent at scale — solved by generating content from
single sources of truth and writing machine validators for every claim, rather than
hand-checking 600+ units of content.

**Q34. What did you learn building this?**
How far the request/response + prepared-statement + session model goes without any framework;
where polling is genuinely "good enough"; and that careful constraints (no build tools, no
server exec) produce simpler, more auditable systems.

---

*Files referenced in the demo: `PROBLEM_STATEMENT.md`, `docs/er-diagram.svg` / `.mmd`,
`docs/Codeface-Presentation.pptx`. Demo logins: alice / bob / carol / dev_mike — password `password123`, room `DEMO42`.*

---

## H. Template-Specific Questions (Common Project Presentation Template)

**Q35. Your template mentions Bootstrap/AdminLTE — where are they?**
We deliberately used a custom vanilla CSS design system instead of Bootstrap, and built admin
features into the schema (`users.is_admin`, versioned content seed) instead of wrapping the app
in AdminLTE — the project's constraint is zero frameworks, so the whole UI stays auditable.

**Q36. Explain your DFD.**
Context level: the User exchanges credentials, solutions, and edits with the Codeface system and
receives verdicts, pages, ratings, and progress. Level 1: four processes — 1.0 Auth & Profile,
2.0 Practice & Judge, 3.0 Learn Tracks, 4.0 Rooms & Chat — each backed by its data store
(D1 users, D2 problems/submissions, D3 lessons/progress, D4 rooms/pads/chat).

**Q37. Defend your normalization. Why is this 3NF?**
Every non-key attribute depends on the key, the whole key, and nothing but the key: users,
problems, and lessons each hold their own attributes once; repeated relationships (room membership) are factored into junction tables carrying only the two keys plus relationship
attributes like `role` or `joined_at`.

**Q38. Why composite primary keys on junction tables?**
They express the relationship's identity exactly — a room-user pair is either a member or isn't —
and give us free uniqueness plus fast lookups from either side, without a meaningless surrogate id.

**Q39. Explain your Gantt/PERT.**
Six weeks: requirements → database & seed → practice+judge → learn content → rooms+chat →
testing & docs. The critical path runs through the database schema — every module depends on it —
while content validation ran in parallel with interface work.

**Q40. What are your server requirements, exactly?**
PHP 8.x with PDO, MySQL or MariaDB, Apache — i.e., stock XAMPP works on any laptop; deployment
is file upload plus opening the URL, since the database self-seeds on first request.

**Q41. Where is the "reporting" in your system?**
The leaderboard (rating, tie-broken by first solve), profile reports (solve history, streaks,
per-category progress, learn-track completion), — all computed from
`submissions` and `learn_progress` with plain SQL aggregates.

**Q42. If you had to add true AdminLTE-style admin panel, how?**
A `backend/partials/admin` guard checking `is_admin`, reusing the same PDO layer — CRUD screens for
problems/lessons that write through the existing upsert logic, so auditability stays intact.
