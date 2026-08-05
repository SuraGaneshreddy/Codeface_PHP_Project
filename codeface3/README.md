# Codeface — practice · learn · pair · compete

A real-time coding practice and collaboration platform for beginner/intermediate
developers. **Vanilla HTML/CSS/JS frontend · PHP + SQL backend · no frameworks,
no build step, no Composer.**

Four pillars:

1. **Practice** — **526 problems across 12 topics**, each solvable in **12 languages**,
   with an in-browser editor (Monaco via CDN) and instant test feedback, executed in a
   sandboxed Web Worker (JavaScript/TypeScript) or a WASM runtime (Python) in *your* browser.
2. **Learn** — **15 tracks × 8 practical lessons (120 total)** — the 12 languages above plus
   **SQL, Bash (shell) and HTML & CSS** — all taught through real-world
   scenarios — carts, logs, expense reports, never foo/bar. JavaScript & Python lessons are
   runnable right in the page; every lesson links a matching practice problem.
3. **Live rooms** — real-time shared pads in **all 12 languages**, chat, and presence over
   **Server-Sent Events** (with an automatic polling fallback).
4. **Compete** — hackathons, a points leaderboard, and skill-based matchmaking that pairs you
   into a private room with the closest-rated available partner.

---

## Quick start

### Option A — zero setup (SQLite, recommended for a first look)

Any PHP 8+ install works (including XAMPP's `php.exe`):

```bash
cd codeface
PHP_CLI_SERVER_WORKERS=10 php -S localhost:8000      # macOS/Linux
# Windows (XAMPP):  set PHP_CLI_SERVER_WORKERS=10 && C:\xampp\php\php.exe -S localhost:8000
```

Open http://localhost:8000. The SQLite database (`data/codeface.sqlite`) is created and
seeded automatically on the first request.

> `PHP_CLI_SERVER_WORKERS=10` matters: the built-in server single-threads by default, and a
> long-lived SSE stream would block every other request. (Not needed on Apache.)

### Option B — XAMPP / Apache (MySQL)

1. Copy the `codeface` folder into `xampp/htdocs/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Set the DB driver so the app uses MySQL instead of SQLite. Easiest: edit
   `config/config.php` line `'driver' => ...` to `'mysql'`
   (or set env `CODEFACE_DB_DRIVER=mysql`). Default credentials are `root` / empty
   password — change `config/config.php` if yours differ.
4. Browse to `http://localhost/codeface/`.

The `codeface` MySQL database, all tables, and seed data are created **automatically** on
first request (no phpMyAdmin import needed). If you prefer manual setup, import
`sql/schema.mysql.sql` and let the seed run on first hit.

### Demo accounts

| username | password | seeded profile |
|---|---|---|
| `alice` | `password123` | top of the leaderboard |
| `bob` | `password123` | her pair-programming partner |
| `carol` | `password123` | hackathon regular |
| `dev_mike` | `password123` | beginner |

Try it live: open `room.php?code=DEMO42` in two browsers as alice and bob and type.

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
- **Learn (`learn.php`)** — 15 tracks × 8 lessons: values → functions → collections →
  text pipelines → errors → mini projects, each anchored in a real scenario (coffee carts,
  expense reports, server logs, backup scripts) and most ending with a "make it muscle
  memory" link into a related practice problem. Tracks cover the 12 practice languages
  **plus SQL, Bash (shell) and HTML & CSS** as learn-only tracks. JS and Python lessons
  embed an editable, runnable code block (same runner as practice). Every runnable-track
  example is machine-verified against a real interpreter (Node, CPython, PHP, SQLite, GNU
  bash). Progress is tracked per lesson and per track.
- **Rooms (`rooms.php`, `room.php`)** — shared pads for **all 12 languages** with versioned
  sync; 6-letter join codes; chat; presence with heartbeat; SSE push with automatic
  reconnect and a polling fallback if SSE is blocked.
- **Matchmaking** — pick language + difficulty; matched with the waiting user whose rating
  is closest; both land in a fresh room with a random problem of that difficulty.
- **Leaderboard** — points from first-time solves (easy 10 / medium 20 / hard 35),
  tie-broken by who solved first. Rating = 1200 + points earned.
- **Hackathons** — join/leave, live countdowns, featured problem sets per event.

## Architecture

```
codeface/
├── index.php · login.php · register.php · logout.php      # public + auth pages
├── problems.php · problem.php                             # practice (list + workspace)
├── learn.php · learn-track.php · learn-lesson.php         # Learn section
├── rooms.php · room.php                                   # lobby + live room
├── leaderboard.php · hackathons.php · profile.php
├── api/
│   ├── rooms/      create · state · push · chat · heartbeat · leave · **stream** (SSE)
│   ├── matchmaking/ join · status · cancel
│   ├── hackathons/ join
│   ├── learn/      complete (lesson progress toggle)
│   └── submissions.php                                    # records run results
├── lib/
│   ├── bootstrap.php   session, config, autoloads everything below
│   ├── config/         the one file to edit (DB driver switch)
│   ├── db.php          PDO factory — auto-creates schema+seed (SQLite & MySQL)
│   ├── content_seed.php versions + upserts problems & lessons (bank → DB)
│   ├── langs.php       the 12-language registry (names, runners, Monaco ids)
│   ├── emitters.php    generates starters/tests per language from one signature
│   ├── pbank/*.php     the 526-problem bank (12 topic files)
│   ├── learnbank/*.php the 120-lesson bank (15 tracks: 12 languages + SQL, Bash, HTML&CSS)
│   ├── seed.php        demo users, solves, hackathons, demo room
├── assets/
│   ├── css/app.css     hand-written design system (no framework)
│   └── js/  util · editor (Monaco loader + offline fallback)
│            · runner (+ JS worker + Pyodide worker) · problem · room (SSE client)
│            · rooms · hackathons · learn-lesson
├── sql/schema.sqlite.sql · schema.mysql.sql               # applied automatically
├── partials/           head / header / footer
└── data/               SQLite home (server-writable; .htaccess-protected)
```

### Data model (both engines)

`users`, `problems` (`tests_json`, `starters_json`, `category`), `submissions`, `rooms`,
`room_pads` (versioned content per language), `room_members` (presence via `last_seen`),
`chat_messages`, `hackathons`, `hackathon_participants`, `hackathon_problems`,
`matchmaking_queue` (unique per user), `learn_lessons`, `learn_progress`, `meta`
(schema version — content upgrades re-seed idempotently).

### Real-time design

- Room page opens an `EventSource` to `api/rooms/stream.php`. The stream sends a full
  **snapshot**, then diffs (**code** per language pad, **chat**, **presence**) every 600 ms,
  and rotates the connection every ~50 s (EventSource auto-reconnects transparently).
- `stream.php` calls `session_write_close()` immediately after auth — otherwise the
  long-lived request would block all other requests from the same user.
- Edits go **up** via `POST /api/rooms/push.php` with optimistic version checking
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
| MySQL errors on XAMPP | create an empty DB yourself or verify `root`/blank password in `config/config.php`; driver must be `'mysql'` |
| Monaco didn't load (offline) | expected — the fallback textarea editor engages automatically |
| Python run says "downloading…" forever | Pyodide comes from jsDelivr — check the network tab / offline mode |

## Roadmap

- Server-side judging service (isolated microVM/container per run) for verified submissions —
  the single change that would make Java, C, Go, Rust & friends truly runnable in-app
- OT/CRDT text sync + follow-the-driver cursor presence
- WebSocket transport via a tiny standalone PHP CLI daemon (opt-in, no extensions)
- Grow the problem bank further (entry format is one `pdef()` per problem; 526 shipped)
