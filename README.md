# 💻 Codeface — practice · learn · pair · compete · go pro

> A real-time coding practice and collaboration platform for beginner & intermediate
> developers — built with **vanilla HTML/CSS/JS + PHP + SQL**. No frameworks, no build
> step, no Composer. Runs on XAMPP as-is.

**ESE Major Project · PHP & MySQL Web Development**

---

## 📑 Table of contents

1. [Tech stack](#-tech-stack-at-a-glance)
2. [The five pillars](#-the-five-pillars)
3. [Quick start](#-quick-start)
4. [Deploy (take it online)](#-deploy-take-it-online)
5. [Feature tour](#-feature-tour)
6. [Architecture & folder layout](#-architecture--folder-layout)
7. [Data model](#-data-model-both-engines)
8. [Real-time design](#-real-time-design)
9. [Verification & tests](#-verification--tests)
10. [Documentation index](#-documentation-index)
11. [Assumptions & flagged deviations](#-assumptions--flagged-deviations)
12. [Security notes](#-security-notes)
13. [Troubleshooting](#-troubleshooting)
14. [Roadmap](#-roadmap)

---

## 🧰 Tech stack at a glance

| Layer | Technology |
|---|---|
| **Frontend** | hand-written HTML/CSS/JS (zero frameworks), Monaco editor via CDN (offline textarea fallback), Web Workers + Pyodide WASM for in-browser judging |
| **Backend** | PHP 8 + PDO only (`backend/`), JSON API (`backend/api/`), Server-Sent Events for real-time, plain-socket SMTP client for email |
| **Database** | MySQL/MariaDB **or** SQLite — one codebase, both drivers, auto-created + seeded on first hit |
| **Tooling** | bash + PHP + Node dev harnesses in `tools/` (dev-only; the app itself needs none) |
| **Docs** | problem statement, architecture selection, viva Q&A, DB design + ER diagrams, deployment guide, PPT in `docs/` |

---

## 🏛 The five pillars

1. **Practice** — **526 problems across 12 topics**, each solvable in **12 languages**,
   with an in-browser editor (Monaco via CDN) and instant test feedback, executed in a
   sandboxed Web Worker (JavaScript/TypeScript) or a WASM runtime (Python) in *your* browser.
2. **Learn** — **15 languages × 16 practical lessons (240 total)**, each language split into
   four levels — **🌱 Beginner → 🌿 Intermediate → 🌳 Advance → 🏆 Pro** (4 lessons each) —
   the 12 practice languages plus **SQL, Bash (shell) and HTML & CSS** — all taught through
   real-world scenarios (carts, logs, expense reports — never foo/bar). **Levels unlock
   sequentially per user**: finish all 4 lessons of a level to open the next (enforced
   server-side on the track, level, lesson *and* API layers). JavaScript & Python lessons
   are runnable right in the page; every lesson links a matching practice problem.
3. **Live rooms** — real-time shared pads in **all 12 languages**, chat, and presence over
   **Server-Sent Events** (with an automatic polling fallback).
4. **Compete** — a points leaderboard, and skill-based matchmaking that pairs you into a
   private room with the closest-rated available partner.
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

## 🚀 Quick start

### Option A — zero setup (SQLite, recommended for a first look)

Any PHP 8+ install works (including XAMPP's `php.exe`):

```bash
cd codeface
PHP_CLI_SERVER_WORKERS=10 php -S localhost:8000      # macOS/Linux
# Windows (XAMPP):  set PHP_CLI_SERVER_WORKERS=10 && C:\xampp\php\php.exe -S localhost:8000
```

Open http://localhost:8000 (redirects to `frontend/`). The SQLite database
(`database/data/codeface.sqlite`) is created and seeded automatically on the first request.

> ⚠️ `PHP_CLI_SERVER_WORKERS=10` matters: the built-in server single-threads by default,
> and a long-lived SSE stream would block every other request. (Not needed on Apache.)

### Option B — XAMPP / Apache (MySQL)

1. Copy the `codeface` folder into `xampp/htdocs/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Set the DB driver so the app uses MySQL instead of SQLite. Easiest: edit
   `backend/config/config.php` line `'driver' => ...` to `'mysql'` (or set env
   `CODEFACE_DB_DRIVER=mysql`). Default XAMPP credentials are `root` / empty password —
   change `backend/config/config.php` if yours differ.
4. Browse to `http://localhost/codeface/`.

The `codeface` MySQL database, all 14 tables, and seed data are created **automatically**
on first request (no phpMyAdmin import needed). If you prefer manual setup, import
`database/schema.mysql.sql` and let the seed run on first hit.

### 👤 Demo accounts

| username | password | seeded profile | practice gate (10 solves) |
|---|---|---|---|
| `alice` | `password123` | top of the leaderboard | 12 ✓ Labs & Refactor unlocked |
| `bob` | `password123` | her pair-programming partner | 7/10 🔒 — three solves away |
| `carol` | `password123` | labs & refactor enthusiast | 10 ✓ unlocked (boundary) |
| `dev_mike` | `password123` | beginner | 3/10 🔒 — demo the lock wall |

Try it live: open `frontend/room.php?code=DEMO42` in two browsers as alice and bob and type.

**🤖 Demo the AI generator in 10 seconds:** mark everything as solved for alice, then
reload the Practice/Labs/Refactor pages and watch fresh sets appear (run twice for set 2):

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

## 🌐 Deploy (take it online)

Full guide: [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md). The summary:

| Option | Cost | Best for | Effort |
|---|---|---|---|
| **Laptop + Cloudflare/ngrok tunnel** | ₹0 | viva & demo day — laptop stays the server | 5 min |
| **InfinityFree / AwardSpace / HelioHost** (shared PHP+MySQL) | ₹0 | always-on public link for your resume | 20 min |
| **Cloud VPS** via GitHub Student Pack (DigitalOcean/Azure credits) | ₹0 w/ student card | best fidelity — SSE live rooms first-class | 45 min |
| **Docker** (Render / Railway / any Docker host) | free tier | reproducible containers | 15 min |

**Requirements (any option):** PHP 8.0+, `pdo_mysql` or `pdo_sqlite`, Apache-style
`.htaccess` handling. Schema + demo data self-create on first request — no import step.

### Tunnel demo (share your local XAMPP in 5 minutes)

```bash
cd codeface
PHP_CLI_SERVER_WORKERS=10 php -S 127.0.0.1:8000
cloudflared tunnel --url http://localhost:8000   # …or: ngrok http 8000
```

→ you get a public `https://*.trycloudflare.com` (or `.ngrok-free.app`) link. The root URL
redirects into `frontend/` automatically. Open it on a second device for a two-screen
live-room demo.

### Free shared hosting (InfinityFree recipe)

1. Sign up → create hosting account → create a **MySQL database**; note host (⚠️ not
   `localhost` — e.g. `sql123.infinityfree.com`), db name, user, password.
2. Upload & extract `codeface-deploy.zip` into `htdocs/` — then **verify the hidden files
   transferred** (FTP often skips them): `/.htaccess`, `backend/lib/.htaccess`,
   `backend/config/.htaccess`, `backend/partials/.htaccess`, `database/.htaccess`,
   `database/data/.htaccess`. Without them your code/config/SQLite file are downloadable.
3. Edit `backend/config/config.php`: `'driver' => 'mysql'` + host/name/user/pass from step 1.
4. Visit your subdomain → 14 tables + seed data are created automatically on first hit.
   **Email features:** set env `CODEFACE_SMTP_USER` / `CODEFACE_SMTP_PASS` (a Gmail *App
   Password*) if the panel allows env vars, else edit `backend/config/config.php` → `'smtp'`.
   With no SMTP configured, OTPs are written to `database/data/outbox.log` (offline demo mode).

Shared-host note: if the host buffers long requests, live rooms silently fall back to
2.5 s polling — already built into `frontend/assets/js/room.js`.

### Docker

A `Dockerfile` ships at the repo root (PHP 8.2 + Apache + `pdo_mysql`; the SQLite driver is
built into the image):

```bash
docker build -t codeface .
docker run -p 8080:80 codeface                                    # zero-config SQLite demo
docker run -p 8080:80 -e CODEFACE_DB_DRIVER=mysql \
  -e CODEFACE_DB_HOST=... -e CODEFACE_DB_PORT=3306 \
  -e CODEFACE_DB_USER=... -e CODEFACE_DB_PASS=... \
  -e CODEFACE_DB_NAME=codeface codeface                          # external MySQL
```

`backend/config/config.php` reads every `CODEFACE_DB_*` env var — no code edits inside
containers. The image also flips `AllowOverride All` so the `.htaccess` deny guards work
identically to shared hosting. On Render: *New → Web Service → Git repo → Docker runtime*.

### ✅ Post-deploy checklist

1. Home loads and redirects to `frontend/index.php`.
2. Register a fresh account (proves DB writes).
3. Solve **Two Sum** in JavaScript → Submit → leaderboard points increase.
4. Open `frontend/room.php?code=DEMO42` in **two browsers** (alice + bob) → typing syncs.
5. `frontend/profile.php` → upload a photo (proves upload + passthrough).
6. As `dev_mike`, Labs/Refactor show the 🔒 practice-gate wall.
7. `GET /database/data/codeface.sqlite` over HTTP must be **403**.
8. With SMTP configured: register with a throwaway-but-real mailbox → 6-digit code arrives
   → account only exists after the code is entered.

---

## 🗺 Feature tour

### Practice (`frontend/problems.php`)

**526 seeded problems** across 12 categories — arrays (62), strings (56), DP (51),
math (50), hash maps (50), real-world mini-apps (41), search (40), greedy (40), stack (38),
bits (37), two pointers (37), matrix (25) — filterable by difficulty and topic, with
deterministic JSON test suites (≥2 visible + hidden edge cases each; every reference
solution is machine-verified against every test). Every problem ships **idiomatic starter
code in all 12 languages** (JavaScript, TypeScript, Python, Java, C, C++, C#, Go, Ruby, PHP,
Kotlin, Rust), auto-drafted per language in `localStorage`. `Ctrl/⌘+Enter` runs tests.

### Instant feedback, safely

JavaScript runs in a Web Worker (infinite loops get killed), TypeScript after a client-side
type-strip, Python via **Pyodide** (WASM, downloaded once from CDN). `console.log`/`print`
output is captured; results are compared with canonical JSON deep-equality. The other 9
languages run in **reference mode**: full starters, tests and a reference solution to work
against locally. See [deviation 1 & 2](#-assumptions--flagged-deviations).

### Learn (`frontend/learn.php`)

15 tracks × 16 lessons in four sequential levels — 🌱 Beginner (values → functions →
collections → pipelines), 🌿 Intermediate (mini-projects, applied topics), 🌳 Advance (OOP,
I/O, libraries, idioms), 🏆 Pro (testing, tooling, error design + a capstone) — each
anchored in a real scenario (coffee carts, expense reports, server logs, backup scripts) and
most ending with a "make it muscle memory" link into a related practice problem. Tracks
cover the 12 practice languages **plus SQL, Bash (shell) and HTML & CSS** as learn-only
tracks. JS and Python lessons embed an editable, runnable code block. Every runnable-track
example is machine-verified against a real interpreter (Node, CPython, PHP, SQLite, GNU
bash). Progress is tracked per lesson, per level and per track; **levels hard-lock
sequentially** (track grid, level page, lesson page and the completion API all enforce it).

### Live rooms (`frontend/rooms.php`, `frontend/room.php`)

Shared pads for **all 12 languages** with versioned sync; 6-letter join codes; chat;
presence with heartbeat; SSE push with automatic reconnect and a polling fallback.

### Matchmaking

Pick language + difficulty; matched with the waiting user whose rating is closest; both
land in a fresh room with a random problem of that difficulty.

### Leaderboard (`frontend/leaderboard.php`)

Points from first-time solves (easy 10 / medium 20 / hard 35), tie-broken by who solved
first. Rating = 1200 + points earned.

### Pro Labs (`frontend/labs.php`)

Gated: unlocks after your first **10 solved practice problems** (a progress wall shows X/10
until then). 6 multi-file environments with per-tab Monaco editors: readonly files act as
published APIs you cannot touch; every task check runs inside the sandboxed Worker in
project mode (files concatenated in order, checks evaluated in scope). Completion is stored
per user, and each lab is *provably solvable* (originals fail ≥1 check, a known-good fix
passes all of them — verified by the Node harness).

### Refactor Gym (`frontend/refactor.php`)

Same 10-solve practice gate as Labs. The metrics engine wires complexity, duplication %,
nesting depth, long-function and cryptic-name counts into a baseline-vs-yours table; submit
is accepted only when all safety tests pass. Every original repo is machine-verified to be
green before you touch it.

### 🤖 AI content treadmill

Solve **every** practice problem and the on-server generator **mints 10 fresh problems just
for you**; finish that set and the next one appears — same rule after the 6 Pro Labs (→ 10
new multi-file environments per set) and the 6 Refactor repos (→ 10 new messes per set).
Sets are private to their owner (`problems.ai_user_id`; lab/refactor sets regenerate
deterministically from your slug), never leak into rooms, matchmaking or public lists, and
batch unlocks are enforced server-side in every page and API. One offline engine powers all
three sections (`backend/lib/aibank.php`: 12 problem, 6 lab and 4 refactor template families
seeded by `user × batch × slot`), and every generated item is validated by the **same Node
harness** as the canonical banks — reference solutions pass every generated test, lab
originals genuinely fail ≥1 task, refactor messes are green-but-ugly with measured
baselines, and staff fixes all score ≥90/100.

### Profile (`frontend/profile.php`)

Your public report card and settings page in one: ✏️-badge on the avatar **uploads a
profile photo** (JPG/PNG/GIF/WebP ≤ 2 MB, stored in `database/data/avatars/` and streamed
via a passthrough since `database/data/` is web-denied — initials circle stays the
fallback), ✏️ **edit name & bio** inline, and a **📍 journey card** showing every section
with live status — Practice (X/526 + AI-made), Pro Labs and Refactor Gym (X/6, or the 🔒
practice-gate state), and **Learn broken down per language** with not-started / ongoing /
complete ✓ chips and progress bars. Friends' profiles show the same journey read-only.

### 📧 Forgot password → real email OTP (`frontend/forgot.php` / `reset.php`)

Enter the email on your account and a **6-digit code lands in your actual inbox** (Gmail
included): a hand-rolled SMTP client (`backend/lib/mailer.php`, plain PHP sockets — no
Composer) talks to Gmail over STARTTLS with an App Password. Codes are bcrypt-hashed,
expire in 10 minutes, allow 5 attempts, resend-throttled to 1/minute, and the form never
reveals whether an email exists. No SMTP configured? The code lands in
`database/data/outbox.log` so offline demos still work.

### 🛡 Email existence guard (login + register)

Every typed email is sanity-checked live: RFC format, then **strict MX mail-server records**
for its domain via a tiny `backend/api/auth/check-email.php` endpoint (rate-limited,
DNS-offline-safe). A non-deliverable address paints a **red warning under the field** —
*"gmial.com has no mail server — this mailbox can't exist"* — with a clickable
*"did you mean gmail.com?"* fix, and registration is refused server-side with the same
red alert.

### ✅ Verified signup — the mailbox must actually exist

With SMTP configured, "Create account" becomes two-step: your details are parked (password
pre-hashed in the session, plaintext never stored) and a 6-digit code is emailed. The row
only enters `users` after the correct code comes back — so **a Gmail that doesn't exist (a
typo'd or invented one) can never finish registering.** 10-minute expiry, 5-attempt cap,
60 s resend throttle, codes are single-use. Without SMTP (offline XAMPP demo) registration
stays classic one-step.

### 👨‍💻 Senior-engineer review

Rule-based static analysis (eval/innerHTML dangers, loose equality, empty catches, dead
code, magic numbers, long functions, pyramid nesting, TODOs, Python mutable defaults &
bare excepts…) producing titled findings with a "why it hurts" and a concrete fix, plus a
score and verdict.

---

## 🏗 Architecture & folder layout

The repo is organized by responsibility — `frontend/` (everything the browser can open),
`backend/` (PHP engine + JSON API), `database/` (schemas + live data), `docs/` and
`tools/`; the root `index.php` simply redirects into `frontend/`.

> 📐 Why this shape? See [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) — the 3-tier
> architecture selection, justification, rejected alternatives and implementation status
> (the ESE classroom deliverable).

```
codeface/
├── index.php                   entry shim → redirects to frontend/index.php
├── README.md                   ← you are here
├── Dockerfile · .dockerignore  one-container deploy (PHP 8.2 + Apache + pdo_mysql)
├── .htaccess                   root deny guards
├── frontend/                   everything the browser can open (pages + assets)
│   ├── index.php · login.php · register.php · logout.php      # public + auth pages
│   ├── forgot.php · reset.php                                 # email-OTP password reset
│   ├── problems.php · problem.php                             # practice (list + workspace)
│   ├── learn.php · learn-track.php · learn-level.php · learn-lesson.php
│   ├── labs.php · lab.php                                     # Pro Labs (multi-file envs)
│   ├── refactor.php · refactor-challenge.php                  # Refactor Gym
│   ├── rooms.php · room.php                                   # lobby + live room
│   ├── leaderboard.php · profile.php · avatar.php (photo passthrough)
│   └── assets/
│       ├── css/app.css     hand-written design system (no framework)
│       └── js/  util · editor (Monaco loader + offline fallback)
│                · runner (+ JS worker with judge/snippet/project + Pyodide modes)
│                · problem · room (SSE client) · rooms · learn-lesson · email-check
│                · multiedit (tabbed multi-file editor) · metrics · reviewer · lab · refactor
├── backend/                    the PHP engine; browser only ever reaches api/ endpoints
│   ├── config/config.php   the one file to edit (DB driver switch + SMTP credentials)
│   ├── lib/
│   │   ├── bootstrap.php   session, config, autoloads everything below
│   │   ├── db.php          PDO factory — auto-creates schema+seed (SQLite & MySQL)
│   │   ├── content_seed.php  versions + upserts problems & lessons (bank → DB)
│   │   ├── langs.php       the 12-language registry (names, runners, Monaco ids)
│   │   ├── emitters.php    generates starters/tests per language from one signature
│   │   ├── pbank/*.php     the 526-problem bank (12 topic files)
│   │   ├── learnbank/*.php the 240-lesson bank (15 tracks)
│   │   ├── labsbank.php    the 6 Pro Lab environments (multi-file debug/API repos)
│   │   ├── refactorbank.php  the 6 Refactor Gym repos (+ measured baselines)
│   │   ├── aibank.php      offline AI engine: deterministic per-user generator
│   │   ├── mailer.php      plain-socket SMTP client (EHLO→STARTTLS→AUTH LOGIN→DATA)
│   │   ├── password_reset.php  forgot-password OTP library
│   │   ├── emailcheck.php  MX-record email existence checks
│   │   └── seed.php        demo users, solves, demo room
│   ├── partials/           head / header / footer (+ 404 / practice-gate / learn-locked walls)
│   └── api/                  JSON endpoints (frontend JS calls ../backend/api/…)
│       ├── rooms/      create · state · push · chat · heartbeat · leave · stream (SSE)
│       ├── matchmaking/ join · status · cancel
│       ├── learn/      complete (lesson progress toggle)
│       ├── labs/       complete (lab completion)
│       ├── refactor/   submit (score guard + history)
│       ├── profile/    update (name & bio) · avatar (photo upload)
│       ├── auth/       check-email (live MX existence probe)
│       └── submissions.php                                  # records run results
├── database/
│   ├── schema.sqlite.sql · schema.mysql.sql            # applied automatically (v10)
│   └── data/               SQLite home + outbox.log + avatars (server-writable; .htaccess-protected)
├── docs/                   problem statement · architecture · viva Q&A · database design ·
│                            deploy guide · PPT · architecture.svg / er-diagram.svg / db-design.svg
└── tools/                dev verification harnesses: verify-ai-dump.php + verify-ai.js
                          (AI-content prover) · fake-smtp.js (capture SMTP server) ·
                          integration-test.sh (115 HTTP assertions, driver-switchable)
```

---

## 🗄 Data model (both engines)

| table | purpose |
|---|---|
| `users` | accounts, points/rating, name & bio, `avatar` for uploaded photos |
| `problems` | `tests_json`, `starters_json`, `category`, `ai_user_id` marking per-user 🤖 AI sets |
| `submissions` | every recorded run result |
| `rooms` · `room_pads` · `room_members` | live rooms; versioned pads per language; presence via `last_seen` |
| `matchmaking_queue` | waiting users (unique per user) |
| `learn_lessons` · `learn_progress` | lesson bank mirror + per-user completion |
| `lab_progress` | lab completion (composite PK per user+lab) |
| `refactor_runs` | score history; best = MAX |
| `password_resets` | hashed 6-digit OTPs, 10-minute TTL, attempt-capped |
| `meta` | schema version — content upgrades re-seed idempotently (current **v10**) |

**14 tables, 16 FKs.** Full write-up: [docs/DATABASE_DESIGN.md](docs/DATABASE_DESIGN.md)
(+ `docs/er-diagram.svg`, `docs/db-design.svg`).

---

## ⚡ Real-time design

- Room page opens an `EventSource` to `backend/api/rooms/stream.php`. The stream sends a
  full **snapshot**, then diffs (**code** per language pad, **chat**, **presence**) every
  600 ms, and rotates the connection every ~50 s (EventSource auto-reconnects transparently).
- `stream.php` calls `session_write_close()` immediately after auth — otherwise the
  long-lived request would block all other requests from the same user.
- Edits go **up** via `POST /backend/api/rooms/push.php` with optimistic version checking
  (`base_version` mismatch → `409` + latest content; client adopts it). Pad rows for new
  languages are lazily created on first push.
- If SSE errors 8 times (corporate proxy, aggressive shared host), the UI silently switches
  to 2.5 s polling of `state.php` — rooms stay live everywhere.

---

## 🧪 Verification & tests

Everything ships machine-verified; the harnesses are dev tools in `tools/` (the app itself
needs none of them):

| Suite | What it proves | Result |
|---|---|---|
| `php tools/verify-ai-dump.php` + `node tools/verify-ai.js` | every canonical AND AI-generated item is real: reference solutions pass all sampled tests (same `canon()` deep-equality as the browser Worker), lab originals fail ≥1 task & staff fixes pass all, refactor messes score 55 with measured baselines, staff fixes score ≥90, PHP↔JS metrics parity | **1084/1084 ✅** |
| `bash tools/integration-test.sh` | 115 HTTP assertions over real curl sessions as all 4 demo users: practice gate walls (page + API), AI treadmills for all 3 sections, per-user ownership 404s, anti-tamper guards, guest behavior, profile avatar/name/journey, **live rooms (create/pads/409-sync/chat/SSE/heartbeat), matchmaking, the full forgot-password email-OTP flow, the email-existence guard, and the two-step verified-signup gate** (wrong/expired/single-use codes, nothing enters the DB unverified) — email flows run against the bundled fake-SMTP harness (`node tools/fake-smtp.js` + `ITEST_SMTPBOX`) | **115/115 ✅ on SQLite and 115/115 ✅ on MySQL** |
| `php -l` + `node --check` sweeps | every PHP and JS file parses | clean |

```bash
# integration suite against a MySQL-backed instance:
ITEST_BASE=http://127.0.0.1:8094 \
ITEST_DSN="mysql:host=127.0.0.1;port=3306;dbname=codeface" \
ITEST_DSN_USER=root ITEST_DSN_PASS= bash tools/integration-test.sh

# with the email sections (H/I/J) pointed at a capture SMTP server:
node tools/fake-smtp.js /tmp/smtpbox.log 2525 &
ITEST_SMTPBOX=/tmp/smtpbox.log bash tools/integration-test.sh
```

---

## 📚 Documentation index

| File | Contents |
|---|---|
| [docs/PROBLEM_STATEMENT.md](docs/PROBLEM_STATEMENT.md) | what the project sets out to solve |
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | project-name + **3-tier architecture selection** (justification, alternatives, implementation status) |
| [docs/DATABASE_DESIGN.md](docs/DATABASE_DESIGN.md) | all 14 tables, keys, FKs, ER rationale |
| [docs/VIVA-QA.md](docs/VIVA-QA.md) | viva questions & answers (incl. AI engine, gates, email flows, testing) |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | full deploy guide: tunnels, free PHP hosts, VPS, Docker, SMTP setup |
| [docs/Codeface-Presentation.pptx](docs/Codeface-Presentation.pptx) | presentation deck |
| `docs/architecture.svg` · `docs/architecture-poster.svg/.png` | 3-tier architecture diagrams (presentation-ready) |
| `docs/er-diagram.svg` · `docs/db-design.svg` (+ `.mmd` source) | schema diagrams |

---

## ⚠️ Assumptions & flagged deviations

The original build prompt was truncated mid-sentence, so anything past the tech-stack
section was my call. Specifically:

1. **Code execution is client-side.** Running untrusted user code with PHP's
   `exec()`/proc APIs is unsafe without OS-level sandboxing (and most shared hosts disable
   those functions). Executing in a browser Worker / WASM is instant, free, and cannot harm
   the server. *Trade-off:* pass/fail is reported by the client, so leaderboard points are
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
   adopt the server copy), not full OT/CRDT. In pair-programming etiquette (one driver)
   this is indistinguishable; true CRDT would need a much bigger client.
5. **"100 to 500 practice problems" → shipped 526.** The bank is data-driven
   (`backend/lib/pbank/*.php`): added by appending `pdef(...)` entries — starters for all 12
   languages, tests, descriptions and points are generated automatically, and the whole bank
   is validated by running every reference solution against every test in CI-style Node
   before shipping.
6. **Practice + submissions require login;** leaderboards, profiles and the problem list
   are public. Demo data is seeded exactly once, at DB creation.
7. **The "AI" is an offline deterministic generator, not a cloud LLM.** The brief asked for
   an AI that creates new content automatically, but the no-frameworks/no-APIs stack (and
   XAMPP-offline demos) rules out calling a model — so `backend/lib/aibank.php` composes new
   problems, labs and refactor repos from parameterized template families seeded per
   `user × batch × slot`: two users get different sets, the same user regenerating batch 3
   gets it byte-identical, and the PHP oracle that builds each item also bakes its tests —
   which is exactly what lets the CI-style harness *prove* every generated item is solvable
   before a user ever sees it. Plausible, themed, endless — and, like deviation 1, honest
   about what it is.

---

## 🔒 Security notes

- Passwords: `password_hash()` (bcrypt); sessions regenerate on login; cookies
  `HttpOnly`/`SameSite=Lax`.
- All SQL is prepared statements (PDO). Output is escaped (`e()`); problem descriptions
  pass a tag whitelist (`allow_html`); chat is stored raw and escaped on render.
- CSRF: session token required on every state-changing request (header or form field).
- Server never evals user code (see deviation 1). Pad size caps: 200 KB; chat 500 chars.
- Avatar uploads: mime sniffed via `finfo` + `getimagesize`, ≤ 2 MB, JPG/PNG/GIF/WebP only;
  stored under web-denied `database/data/avatars/` and streamed via `frontend/avatar.php`.
- Password resets & signup codes: OTPs stored as `password_hash` digests only, 10-min
  expiry, 5-attempt cap, 60 s resend throttle, session flood-guard, one-time use, no
  account enumeration.
- `.htaccess` deny guards on `backend/lib/`, `backend/config/`, `backend/partials/`,
  `database/` and `database/data/` (keep them when uploading — see Deploy).
- Per-user AI content is ownership-checked server-side (other users get a real 404), and
  the refactor submit API recomputes the expected test count to reject tampered payloads.

---

## 🩺 Troubleshooting

| symptom | fix |
|---|---|
| Blank page on Apache | check `php_error_log`; ensure PHP ≥ 8.0 |
| Room stuck on "connecting…" | it will fall back to polling in a few seconds; SSE may be blocked by a proxy |
| Everything freezes with `php -S` | you forgot `PHP_CLI_SERVER_WORKERS=10` |
| Rooms "not working" after an update | stale tab pointing at an old URL (root `/room.php` → replaced by `frontend/room.php`) or single-threaded server; hard-refresh (Ctrl+F5) |
| MySQL errors on XAMPP | create an empty DB yourself or verify `root`/blank password in `backend/config/config.php`; driver must be `'mysql'` |
| MySQL errors on shared hosting | host is **not** `localhost` — copy the exact host from the panel |
| Styles/scripts missing after an update | hard-refresh (Ctrl+F5); confirm `frontend/assets/` uploaded fully |
| `403` from Apache everywhere | host `.htaccess` conflict — comment out `Options -Indexes` in `/.htaccess` |
| Monaco didn't load (offline) | expected — the fallback textarea editor engages automatically |
| Python run says "downloading…" forever | Pyodide comes from jsDelivr — check the network tab / offline mode |
| Reset email never arrives | Gmail needs an **App Password** (2-Step Verification ON → myaccount.google.com/apppasswords), not your login password; also check Spam. With no SMTP configured the OTP is in `database/data/outbox.log` |
| "Connection refused" in PHP log on send | outbound 587 blocked (some free hosts) — try `'secure' => 'ssl'` + port 465, or a local relay; the dev fallback always logs to `outbox.log` |
| Red "no mail server" warning on a real email | almost always a domain typo — click the inline *did-you-mean* fix; if it's your own custom domain, its MX records may just not have propagated yet |
| Signup verification code never arrives | that's the feature working — the mailbox may not exist; check Spam, give it a couple of minutes, or resend. SMTP unset → registration is one-step by design (offline mode) |
| Register blocked while fully offline | DNS is unreachable → MX checks are skipped automatically (they never block offline demos); the *server-side* gate also yields when DNS can't be consulted |

---

## 🛣 Roadmap

- Server-side judging service (isolated microVM/container per run) for verified
  submissions — the single change that would make Java, C, Go, Rust & friends truly
  runnable in-app
- OT/CRDT text sync + follow-the-driver cursor presence
- WebSocket transport via a tiny standalone PHP CLI daemon (opt-in, no extensions)
- Grow the problem bank further (entry format is one `pdef()` per problem; 526 shipped)

---

<p align="center"><b>Codeface</b> — hand-built with vanilla HTML/CSS/JS · PHP 8 · SQL.<br>
Demo: <code>alice / password123</code> · room <code>DEMO42</code></p>
