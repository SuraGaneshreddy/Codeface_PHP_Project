# Codeface — practice · learn · pair · compete · go pro

A real-time coding practice and collaboration platform for beginner/intermediate
developers. **Vanilla HTML/CSS/JS frontend · PHP + SQL backend · no frameworks,
no build step, no Composer.**

Four pillars:

1. **Practice** — **526 problems across 12 topics**, each solvable in **12 languages**,
   with an in-browser editor (Monaco via CDN) and instant test feedback, executed in a
   sandboxed Web Worker (JavaScript/TypeScript) or a WASM runtime (Python) in *your* browser.
2. **Learn** — **15 languages × 16 practical lessons (240 total)**, each language split into four
   levels — **🌱 Beginner → 🌿 Intermediate → 🌳 Advance → 🏆 Pro** (4 lessons each) — the 12
   languages above plus **SQL, Bash (shell) and HTML & CSS** — all taught through real-world
   scenarios — carts, logs, expense reports, never foo/bar. **Levels unlock sequentially per
   user**: finish all 4 lessons of a level to open the next (enforced server-side on the track,
   level, lesson *and* API layers). JavaScript & Python lessons are runnable right in the page;
   every lesson links a matching practice problem.
3. **Live rooms** — real-time shared pads in **all 12 languages**, chat, and presence over
   **Server-Sent Events** (with an automatic polling fallback).
4. **Compete** — a points leaderboard, and skill-based matchmaking that pairs you
   into a private room with the closest-rated available partner.
5. **Pro Mode** — the "real job" layer course platforms skip:
   - **Pro Labs** — 6 multi-file engineering environments: debug planted bugs in legacy
     codebases (money units, timezone math, CSV parsing) or integrate against readonly
     vendor-style APIs (pagination, unit conversion), validated by behavioral task checks.
   - **Refactor Gym** — 6 messy-but-working repos where the goal is cleanup, not greenfield:
     keep every safety test green while cutting measured complexity/duplication vs the
     baseline. Score = tests × cleanup quality.
   - **Senior-engineer review** — an automated, rule-based code reviewer (15+ heuristics:
     security, correctness, readability, design) with findings, fixes and a 0–100 score —
     available on the solver, every lab and every refactor repo.

---

## Quick start

### Option A — zero setup (SQLite, recommended for a first look)

Any PHP 8+ install works (including XAMPP's `php.exe`):

```bash
cd codeface
PHP_CLI_SERVER_WORKERS=10 php -S localhost:8000      # macOS/Linux
# Windows (XAMPP):  set PHP_CLI_SERVER_WORKERS=10 && C:\xampp\php\php.exe -S localhost:8000
```

Open http://localhost:8000. The SQLite database (`database/data/codeface.sqlite`) is created and
seeded automatically on the first request.

> `PHP_CLI_SERVER_WORKERS=10` matters: the built-in server single-threads by default, and a
> long-lived SSE stream would block every other request. (Not needed on Apache.)

### Option B — XAMPP / Apache (MySQL)

1. Copy the `codeface` folder into `xampp/htdocs/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Set the DB driver so the app uses MySQL instead of SQLite. Easiest: edit
   `backend/config/config.php` line `'driver' => ...` to `'mysql'`
   (or set env `CODEFACE_DB_DRIVER=mysql`). Default credentials are `root` / empty
   password — change `backend/config/config.php` if yours differ.
4. Browse to `http://localhost/codeface/`.

The `codeface` MySQL database, all tables, and seed data are created **automatically** on
first request (no phpMyAdmin import needed). If you prefer manual setup, import
`database/schema.mysql.sql` and let the seed run on first hit.

### Demo accounts

| username | password | seeded profile | practice gate (10 solves) |
|---|---|---|---|
| `alice` | `password123` | top of the leaderboard | 12 ✓ Labs & Refactor unlocked |
| `bob` | `password123` | her pair-programming partner | 7/10 🔒 — three solves away |
| `carol` | `password123` | labs & refactor enthusiast | 10 ✓ unlocked (boundary) |
| `dev_mike` | `password123` | beginner | 3/10 🔒 — demo the lock wall |

Try it live: open `room.php?code=DEMO42` in two browsers as alice and bob and type.

**🤖 Demo the AI generator in 10 seconds:** mark everything as solved for alice, then just
reload the Practice/Labs/Refactor pages and watch new sets appear (run twice to get set 2):

```sql
-- sqlite3 database/data/codeface.sqlite   (or the `codeface` MySQL DB; user 1 = alice)
INSERT INTO submissions (user_id, problem_id, status, code, passed, total, created_at)
  SELECT 1, id, 'pass', '-- demo', 1, 1, CURRENT_TIMESTAMP FROM problems
  WHERE ai_user_id IS NULL;
INSERT INTO lab_progress (user_id, lab_slug, completed_at)
  VALUES (1,'legacy-cart-bug',CURRENT_TIMESTAMP),(1,'timezone-double-conversion',CURRENT_TIMESTAMP),
         (1,'api-integration-weather',CURRENT_TIMESTAMP),(1,'api-integration-pagination',CURRENT_TIMESTAMP),
         (1,'legacy-inventory-nan',CURRENT_TIMESTAMP),(1,'legacy-auth-token',CURRENT_TIMESTAMP);
INSERT INTO refactor_runs (user_id, challenge_slug, score, tests_passed, tests_total, metrics, created_at)
  VALUES (1,'god-function-checkout',92,5,5,'{}',CURRENT_TIMESTAMP),
         (1,'stringy-pricing',92,4,4,'{}',CURRENT_TIMESTAMP),
         (1,'nested-report-builder',92,4,4,'{}',CURRENT_TIMESTAMP),
         (1,'global-state-soup',92,5,5,'{}',CURRENT_TIMESTAMP),
         (1,'copy-paste-validators',92,4,4,'{}',CURRENT_TIMESTAMP),
         (1,'sync-spaghetti-stats',92,4,4,'{}',CURRENT_TIMESTAMP);
```


---

## Feature tour

- **Practice (`problems.php`)** — **526 seeded problems** across 12 categories — arrays (62),
  strings (56), DP (51), math (50), hash maps (50), real-world mini-apps (41), search (40),
  greedy (40), stack (38), bits (37), two pointers (37), matrix (25) — filterable by
  difficulty and topic, with deterministic JSON test suites (≥2 visible + hidden edge cases
  each; every reference solution is machine-verified against every test). Every problem ships **idiomatic starter
  code in all 12 languages** (JavaScript, TypeScript, Python, Java, C, C++, C#, Go, Ruby,
  PHP, Kotlin, Rust), auto-drafted per language in `localStorage`. `Ctrl/⌘+Enter` runs tests.
- **Instant feedback, safely** — JavaScript runs in a Web Worker (infinite loops get killed),
  TypeScript after a client-side type-strip, Python via **Pyodide** (WASM, downloaded once
  from CDN). `console.log`/`print` output is captured; results are compared with canonical
  JSON deep-equality. The other 9 languages run in **reference mode**: full starters,
  tests and a reference solution to work against locally (no safe way to `exec()` compilers
  on a vanilla-PHP shared host). See *Honest limitations* below.
- **Learn (`learn.php`)** — 15 tracks × 16 lessons in four sequential levels — 🌱 Beginner
  (values → functions → collections → pipelines), 🌿 Intermediate (mini-projects, applied
  topics), 🌳 Advance (OOP, I/O, libraries, idioms), 🏆 Pro (testing, tooling, error design +
  a capstone mini-project) — each anchored in a real scenario (coffee carts,
  expense reports, server logs, backup scripts) and most ending with a "make it muscle
  memory" link into a related practice problem. Tracks cover the 12 practice languages
  **plus SQL, Bash (shell) and HTML & CSS** as learn-only tracks. JS and Python lessons
  embed an editable, runnable code block (same runner as practice). Every runnable-track
  example is machine-verified against a real interpreter (Node, CPython, PHP, SQLite, GNU
  bash). Progress is tracked per lesson, per level and per track; **levels hard-lock
  sequentially** (server-side: track grid, level page, lesson page and the completion API all
  enforce it).
- **Rooms (`rooms.php`, `room.php`)** — shared pads for **all 12 languages** with versioned
  sync; 6-letter join codes; chat; presence with heartbeat; SSE push with automatic
  reconnect and a polling fallback if SSE is blocked.
- **Matchmaking** — pick language + difficulty; matched with the waiting user whose rating
  is closest; both land in a fresh room with a random problem of that difficulty.
- **Leaderboard** — points from first-time solves (easy 10 / medium 20 / hard 35),
  tie-broken by who solved first. Rating = 1200 + points earned.
- **Pro Labs (`labs.php`)** — gated: unlocks after your first **10 solved practice problems**
  (a progress wall shows X/10 until then). 6 multi-file environments with per-tab Monaco
  editors: readonly
  files act as published APIs you cannot touch; every task check runs inside the sandboxed
  Worker in project mode (files concatenated in order, checks evaluated in scope).
  Completion is stored per user, and each lab ended up *provably solvable* (originals fail
  ≥1 check, a known-good fix passes all of them — verified by the Node harness).
- **Refactor Gym (`refactor.php`)** — same 10-solve practice gate as Labs. Metrics engine
  wires complexity, duplication %,
  nesting depth, long-function and cryptic-name counts into a baseline-vs-yours table;
  submit is accepted only when all safety tests pass. Every original repo is
  machine-verified to be green before you touch it.
- **🤖 AI content treadmill** — solve **every** practice problem and the on-server generator
  **mints 10 fresh problems just for you**; finish that set and the next one appears — same
  rule after the 6 Pro Labs (→ 10 new multi-file environments per set) and the 6 Refactor
  repos (→ 10 new messes per set). Sets are private to their owner (`problems.ai_user_id`;
  lab/refactor sets regenerate deterministically from your slug), never leak into rooms,
  matchmaking or public lists, and batch unlocks are enforced server-side in every page and
  API. One offline engine powers all three sections (`backend/lib/aibank.php`: 12 problem, 6 lab and
  4 refactor template families seeded by `user × batch × slot`), and every generated item is
  validated by the **same Node harness** as the canonical banks — reference solutions pass
  every generated test, lab originals genuinely fail ≥1 task, refactor messes are
  green-but-ugly with measured baselines, and staff fixes all score ≥90/100.
- **Profile (`frontend/profile.php`)** — your public report card and your settings page in one:
  ✏️-badge on the avatar **uploads a profile photo** (JPG/PNG/GIF/WebP ≤ 2 MB, stored in
  `database/data/avatars/` and streamed via a passthrough since `database/data/` is web-denied — initials
  circle stays the fallback), ✏️ **edit name & bio** inline, and a **📍 journey card** that
  shows every section with live status — Practice (X/526 + AI-made), Pro Labs and Refactor
  Gym (X/6, or the 🔒 practice-gate state), and **Learn broken down per language** with
  not-started / ongoing / complete ✓ chips and progress bars. Friends' profiles show the
  same journey read-only.
- **Senior-engineer review (👨‍💻 button)** — rule-based static analysis (eval/innerHTML
  dangers, loose equality, empty catches, dead code, magic numbers, long functions,
  pyramid nesting, TODOs, Python mutable defaults & bare excepts…) producing titled
  findings with a "why it hurts" and a concrete fix, plus a score and verdict.

## Architecture

```
codeface/
├── index.php                 entry shim → redirects to frontend/index.php
├── frontend/                 everything the browser can open (pages + assets)
│   ├── index.php · login.php · register.php · logout.php      # public + auth pages
│   ├── problems.php · problem.php                             # practice (list + workspace)
│   ├── learn.php · learn-track.php · learn-level.php · learn-lesson.php
│   ├── labs.php · lab.php                                     # Pro Labs (multi-file envs)
│   ├── refactor.php · refactor-challenge.php                  # Refactor Gym
│   ├── rooms.php · room.php                                   # lobby + live room
│   ├── leaderboard.php · profile.php · avatar.php (photo passthrough)
│   └── assets/
│       ├── css/app.css     hand-written design system (no framework)
│       └── js/  util · editor (Monaco loader + offline fallback)
│                · runner (+ JS worker with judge/snippet/**project** + Pyodide modes)
│                · problem · room (SSE client) · rooms · learn-lesson
│                · multiedit (tabbed multi-file editor) · metrics · reviewer · lab · refactor
├── backend/                  the PHP engine; browser only ever reaches api/ endpoints
│   ├── config/config.php the one file to edit (DB driver switch)
│   ├── lib/
│   │   ├── bootstrap.php   session, config, autoloads everything below
│   │   ├── db.php          PDO factory — auto-creates schema+seed (SQLite & MySQL)
│   │   ├── content_seed.php versions + upserts problems & lessons (bank → DB)
│   │   ├── langs.php       the 12-language registry (names, runners, Monaco ids)
│   │   ├── emitters.php    generates starters/tests per language from one signature
│   │   ├── pbank/*.php     the 526-problem bank (12 topic files)
│   │   ├── learnbank/*.php the 120-lesson bank (15 tracks + SQL, Bash, HTML&CSS)
│   │   ├── labsbank.php    the 6 Pro Lab environments (multi-file debug/API repos)
│   │   ├── refactorbank.php the 6 Refactor Gym repos (+ measured baselines)
│   │   ├── aibank.php      offline AI engine: deterministic per-user problem/lab/repo generator
│   │   └── seed.php        demo users, solves, demo room
│   ├── partials/           head / header / footer (+ 404 / practice-gate / learn-locked walls)
│   └── api/                JSON endpoints (frontend JS calls ../backend/api/…)
│       ├── rooms/      create · state · push · chat · heartbeat · leave · **stream** (SSE)
│       ├── matchmaking/ join · status · cancel
│       ├── learn/      complete (lesson progress toggle)
│       ├── labs/       complete (lab completion)
│       ├── refactor/   submit (score guard + history)
│       ├── profile/    update (name & bio) · avatar (photo upload)
│       └── submissions.php                                    # records run results
├── database/
│   ├── schema.sqlite.sql · schema.mysql.sql          # applied automatically
│   └── data/               SQLite home (server-writable; .htaccess-protected)
├── docs/                 problem statement · viva Q&A · database design · PPT ·
│                          er-diagram.svg · db-design.svg (+ .mmd source)
└── tools/              dev verification harnesses: verify-ai-dump.php + verify-ai.js
                        (AI-content prover) · integration-test.sh (59 HTTP assertions,
                        driver-switchable via ITEST_* env vars)
```

### Data model (both engines)

`users`, `problems` (`tests_json`, `starters_json`, `category`), `submissions`, `rooms`,
`room_pads` (versioned content per language), `room_members` (presence via `last_seen`),
`matchmaking_queue` (unique per user), `learn_lessons`, `learn_progress`,
`lab_progress` (composite PK per user+lab), `refactor_runs` (score history; best = MAX),
`meta` (schema version — content upgrades re-seed idempotently). **13 tables**, 15 FKs
(problems includes `ai_user_id` for per-user 🤖 AI-generated problem sets).

### Real-time design

- Room page opens an `EventSource` to `backend/api/rooms/stream.php`. The stream sends a full
  **snapshot**, then diffs (**code** per language pad, **chat**, **presence**) every 600 ms,
  and rotates the connection every ~50 s (EventSource auto-reconnects transparently).
- `stream.php` calls `session_write_close()` immediately after auth — otherwise the
  long-lived request would block all other requests from the same user.
- Edits go **up** via `POST /backend/api/rooms/push.php` with optimistic version checking
  (`base_version` mismatch → `409` + latest content; client adopts it). Pad rows for new
  languages are lazily created on first push.
- If SSE errors 8 times (corporate proxy, aggressive shared host), the UI silently switches
  to 2.5 s polling of `state.php` — rooms stay live everywhere.

## Assumptions & flagged deviations

The original build prompt was truncated mid-sentence ("…Monaco Editor via its standalone`"),
so anything past the tech-stack section was my call. Specifically:

1. **Code execution is client-side.** Running untrusted user code with PHP's `exec()`/proc
   APIs is unsafe without OS-level sandboxing (and most shared hosts disable those
   functions). Executing in a browser Worker / WASM is instant, free, and cannot harm the
   server. *Trade-off:* pass/fail is reported by the client, so leaderboard points are
   honor-system — fine for a practice platform, not for proctored contests.
2. **Runnable languages = JavaScript, TypeScript, Python** (browser Worker; TS is
   type-stripped; Python via Pyodide WASM from CDN). The other 9 languages ship complete
   starters, tests and reference solutions, and the Learn tracks teach them fully — but the
   in-browser *run* button only exists for those three, and requires internet access for the
   first Python download. There is no honest way to compile C/Go/Rust/etc. from a
   vanilla-PHP shared host without server-side toolchains.
3. **"Real-time" = SSE + POST, not WebSockets.** Plain PHP has no WebSocket server without
   extensions (Ratchet/ReactPHP are Composer packages, which the no-dependencies rule bans).
   SSE is one-way (server→client) which is exactly what collab needs for broadcasts; edits
   travel client→server via ordinary POSTs. Feels live; runs on XAMPP as-is.
4. **Conflict model is last-writer-wins per pad** (optimistic versioning, diverging clients
   adopt the server copy), not full OT/CRDT. In pair-programming etiquette (one driver) this
   is indistinguishable; true CRDT would need a much bigger client.
5. **"100 to 500 practice problems" → shipped 526.** The bank is data-driven
   (`lib/pbank/*.php`): added by appending `pdef(...)` entries — starters for all 12
   languages, tests, descriptions and points are generated automatically, and the whole
   bank is validated by running every reference solution against every test in CI-style
   Node before shipping.
6. **Practice + submissions require login;** leaderboards, profiles and the problem list are
   public. Demo data is seeded exactly once, at DB creation.
7. **The "AI" is an offline deterministic generator, not a cloud LLM.** The brief asked for
   an AI that creates new content automatically, but the no-frameworks/no-APIs stack (and
   XAMPP-offline demos) rules out calling a model — so `lib/aibank.php` composes new
   problems, labs and refactor repos from parameterized template families seeded per
   `user × batch × slot`: two users get different sets, the same user regenerating batch 3
   gets it byte-identical, and the PHP oracle that builds each item also bakes its tests —
   which is exactly what lets the CI-style harness *prove* every generated item is solvable
   before a user ever sees it. Plausible, themed, endless — and, like deviation 1, honest
   about what it is.

## Security notes

- Passwords: `password_hash()` (bcrypt); sessions regenerate on login; cookies `HttpOnly`/`SameSite=Lax`.
- All SQL is prepared statements (PDO). Output is escaped (`e()`); problem descriptions pass a
  tag whitelist (`allow_html`); chat is stored raw and escaped on render.
- CSRF: session token required on every state-changing request (header or form field).
- Server never evals user code (see deviation 1). Pad size caps: 200 KB; chat 500 chars.

## Troubleshooting

| symptom | fix |
|---|---|
| Blank page on Apache | check `php_error_log`; ensure PHP ≥ 8.0 |
| Room stuck on "connecting…" | it will fall back to polling in a few seconds; SSE may be blocked by a proxy |
| Everything freezes with `php -S` | you forgot `PHP_CLI_SERVER_WORKERS=10` |
| MySQL errors on XAMPP | create an empty DB yourself or verify `root`/blank password in `backend/config/config.php`; driver must be `'mysql'` |
| Monaco didn't load (offline) | expected — the fallback textarea editor engages automatically |
| Python run says "downloading…" forever | Pyodide comes from jsDelivr — check the network tab / offline mode |

## Roadmap

- Server-side judging service (isolated microVM/container per run) for verified submissions —
  the single change that would make Java, C, Go, Rust & friends truly runnable in-app
- OT/CRDT text sync + follow-the-driver cursor presence
- WebSocket transport via a tiny standalone PHP CLI daemon (opt-in, no extensions)
- Grow the problem bank further (entry format is one `pdef()` per problem; 526 shipped)
