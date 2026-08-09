# Codeface — Database Design

> Source of truth: `database/schema.mysql.sql` (auto-applied on first run by `backend/lib/db.php`, or importable via phpMyAdmin). An SQLite port (`database/schema.sqlite.sql`) ships for development; both enforce the same structure.

---

## 1. Design Overview

| Property | Value |
|---|---|
| DBMS | MySQL 8.x / MariaDB 10.4+ (SQLite 3 for dev) |
| Engine | InnoDB (transactions + foreign keys) |
| Charset / Collation | `utf8mb4` / `utf8mb4_unicode_ci` (full Unicode, emoji-safe) |
| Tables | **16** |
| Primary keys | 16 (9 surrogate `AUTO_INCREMENT`, 6 composite, 1 natural) |
| Foreign keys | **18** (15 `ON DELETE CASCADE`, 3 `ON DELETE SET NULL`) |
| Secondary indexes | 7 explicit (+ indexes implied by UNIQUE/FK) |
| Access layer | PHP PDO (prepared statements only — SQL-injection safe) |

**Design principles**

- **Surrogate `INT` PKs** on 8 entity tables; **composite natural PKs** on 4 join/progress tables (`room_pads`, `room_members`, `learn_progress`, `lab_progress` — no redundant surrogate); one natural key (`meta.key`).
- **Referential integrity in the DB, not in PHP** — orphans are impossible because deletes cascade or null-out at the engine level.
- **Cascade vs. Set-NULL chosen by semantics**: content owned by a parent disappears with it (CASCADE); history/logs keep the row and lose only the actor link (SET NULL).
- **Denormalization only where justified**: JSON/starter columns in `problems` store content blobs, not relational facts.

---

## 2. Table-by-Table Design

### 2.1 `users` — registered accounts

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT AUTO_INCREMENT | NO | — | **PK** |
| `username` | VARCHAR(20) | NO | — | **UNIQUE** — login handle |
| `email` | VARCHAR(190) | NO | — | **UNIQUE** — 190 = max indexable utf8mb4 length |
| `password_hash` | VARCHAR(255) | NO | — | `password_hash()` bcrypt; plaintext never stored |
| `display_name` | VARCHAR(50) | NO | `''` | shown on profile/leaderboard |
| `bio` | TEXT | YES | NULL | |
| `avatar_color` | VARCHAR(7) | NO | `#6366f1` | hex color for generated avatar |
| `rating` | INT | NO | `1200` | Elo-style, drives leaderboard |
| `is_admin` | TINYINT(1) | NO | `0` | role flag (admin features) |
| `created_at` | DATETIME | NO | `CURRENT_TIMESTAMP` | |
| `last_seen` | DATETIME | YES | NULL | presence indicator |

**Constraints:** PK(`id`), UNIQUE(`username`), UNIQUE(`email`)

### 2.2 `problems` — practice question bank (526 rows)

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `slug` | VARCHAR(80) | **UNIQUE** — URL identity (`/problem.php?slug=…`) |
| `title` | VARCHAR(160) | |
| `difficulty` | ENUM('easy','medium','hard') | DB-level value whitelist |
| `category` | VARCHAR(40) | e.g. arrays, strings, dp |
| `description` | MEDIUMTEXT | HTML, sanitized on output |
| `tags` | VARCHAR(255) | comma list (search aid, not relational) |
| `function_name` | VARCHAR(60) | entry point the runner calls |
| `starter_js` / `solution_js` / `tests_json` | MEDIUMTEXT | content blobs |
| `starters_json` | MEDIUMTEXT | per-language starter map (12 languages) |
| `points` | INT | default 10 |
| `ai_user_id` | INT NULL | **NULL** = canonical (526). Set → 🤖 AI-generated problem owned by that user; FK → `users.id` **CASCADE**, indexed |
| `created_at` | DATETIME | default now |

**Constraints:** PK(`id`), UNIQUE(`slug`), CHECK-like restriction via ENUM for `difficulty`, FK(`ai_user_id` → `users.id`) CASCADE + `IDX(ai_user_id)`

### 2.3 `meta` — key/value store (schema version etc.)

| Column | Type | Notes |
|---|---|---|
| `key` | VARCHAR(40) | **PK** (natural key) |
| `value` | VARCHAR(255) | e.g. `schema_version = 5` drives re-seeding |

Standalone table — intentionally **no relationships** (drawn isolated in the ER diagram).

### 2.4 `learn_lessons` — tutorial content (240 rows: 15 tracks × 16 lessons, positions 1–16 mapping 1:1 to 🌱/🌿/🌳/🏆 levels)

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `track` | VARCHAR(20) | python, js, sql, bash, htmlcss, … |
| `position` | INT | order inside track (1–8) |
| `slug` | VARCHAR(60) | |
| `title` | VARCHAR(160) | |
| `concept` / `example_code` / `example_output` / `try_code` | MEDIUMTEXT | lesson body |
| `problem_slug` | VARCHAR(80) | optional link to a practice problem — **logical** reference (no FK; keeps content tables decoupled) |
| `minutes` | INT | estimate, default 5 |

**Constraints:** PK(`id`), **UNIQUE(`track`,`slug`)** — a slug can't repeat inside a track; **INDEX(`track`,`position`)** — one index serves both "list a track" and "sorted list" queries.

### 2.5 `learn_progress` — which user finished which lesson

| Column | Type | Notes |
|---|---|---|
| `user_id` | INT | PK part 1, FK → `users.id` **CASCADE** |
| `lesson_id` | INT | PK part 2, FK → `learn_lessons.id` **CASCADE** |
| `completed_at` | DATETIME | the row *existing* = completed; no boolean needed |

**Composite PK(`user_id`,`lesson_id`)** — guarantees one progress row per user/lesson pair; duplicate completion is a primary-key violation, impossible by design.

### 2.6 `submissions` — every code run

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `user_id` | INT | FK → `users.id` **CASCADE** |
| `problem_id` | INT | FK → `problems.id` **CASCADE** |
| `status` | VARCHAR(10) | `accepted` / `wrong_answer` / … |
| `code` | MEDIUMTEXT | full source kept for replay |
| `passed` / `total` | INT | test counters |
| `runtime_ms` | FLOAT | NULL allowed (reference-mode languages) |
| `created_at` | DATETIME | |

**Indexes:** (`user_id`) → "my submissions"; (`problem_id`) → per-problem stats. Both also serve their FKs.

### 2.7 `rooms` — live collaboration sessions

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `code` | VARCHAR(12) | **UNIQUE** — join code (e.g. `DEMO42`) |
| `name` | VARCHAR(80) | |
| `owner_id` | INT NULL | FK → `users.id` **SET NULL** — room survives owner deletion |
| `problem_id` | INT NULL | FK → `problems.id` **SET NULL** — room survives problem removal |
| `language` | VARCHAR(20) | active pad language |
| `is_live` | TINYINT(1) | open/closed flag |
| `created_at` | DATETIME | |

The two **SET NULL** FKs are deliberate: a room is a container whose value is its pads/chat, so losing its owner or problem must not destroy it.

### 2.8 `room_pads` — collaborative editor buffers

| Column | Type | Notes |
|---|---|---|
| `room_id` | INT | PK part 1, FK → `rooms.id` **CASCADE** |
| `language` | VARCHAR(20) | PK part 2 |
| `content` | MEDIUMTEXT | current buffer |
| `version` | INT | optimistic-concurrency counter (stale-write detection) |
| `last_editor_id` | INT NULL | audit (deliberately not an FK — editors may leave/delete) |
| `updated_at` | DATETIME | `ON UPDATE CURRENT_TIMESTAMP` — DB-maintained |

**Composite PK(`room_id`,`language`)** — exactly one pad per language per room.

### 2.9 `room_members` — room membership (M:N)

| Column | Type | Notes |
|---|---|---|
| `room_id` | INT | PK part 1, FK → `rooms.id` **CASCADE** |
| `user_id` | INT | PK part 2, FK → `users.id` **CASCADE** |
| `role` | VARCHAR(20) | `owner`/`participant` |
| `joined_at` / `last_seen` / `left_at` | DATETIME | presence + rejoin; leaving is a soft-update (`left_at`), not a delete, so history survives |

### 2.10 `chat_messages` — room chat log

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `room_id` | INT | FK → `rooms.id` **CASCADE** (chat dies with the room) |
| `user_id` | INT NULL | FK → `users.id` **SET NULL** (log keeps text when author is deleted) |
| `body` | VARCHAR(500) | length-capped at the DB |
| `created_at` | DATETIME | |

**INDEX(`room_id`,`id`)** — the hot query (`SELECT … WHERE room_id=? ORDER BY id DESC LIMIT 50`) uses one index for filter + sort.

### 2.11 `matchmaking_queue` — waiting list for auto-paired rooms

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `user_id` | INT | **UNIQUE** + FK → `users.id` **CASCADE** — a user can wait only once (double-queue prevented by the DB) |
| `language` / `difficulty` | VARCHAR | pairing criteria |
| `status` | VARCHAR(10) | `waiting` / `matched` |
| `room_code` | VARCHAR(12) NULL | filled on match |
| `created_at` | DATETIME | FIFO ordering |

### 2.15 `lab_progress` — Pro Lab completions (V4)

| Column | Type | Notes |
|---|---|---|
| `user_id` | INT | PK part 1, FK → `users.id` **CASCADE** |
| `lab_slug` | VARCHAR(60) | PK part 2 — labs are code-defined content (like lesson slugs pre-V3), so the key is the slug, not an FK |
| `completed_at` | DATETIME | row existence = completed |

**Composite PK(`user_id`,`lab_slug`)** — a lab can be completed only once per user; repeats are `INSERT IGNORE` no-ops.

### 2.16 `refactor_runs` — Refactor Gym score history (V4)

| Column | Type | Notes |
|---|---|---|
| `id` | INT AUTO_INCREMENT | **PK** |
| `user_id` | INT | FK → `users.id` **CASCADE** |
| `challenge_slug` | VARCHAR(60) | code-defined challenge identity |
| `score` | INT | 0–100 (server-clamped) |
| `tests_passed` / `tests_total` | INT | server rejects submits where passed < total |
| `metrics` | MEDIUMTEXT | JSON metrics snapshot at submit time |
| `created_at` | DATETIME | |

**Indexes:** (`user_id`) → "my best scores"; (`challenge_slug`) → per-challenge stats. "Best" is derived with `MAX(score) GROUP BY challenge_slug` — history is append-only, so every attempt is auditable.

---

## 3. Keys & Constraints — Summary

### 3.1 Primary keys (16)

| Table | Primary key | Type |
|---|---|---|
| users | `id` | surrogate |
| problems | `id` | surrogate |
| meta | `key` | natural |
| learn_lessons | `id` | surrogate |
| learn_progress | (`user_id`,`lesson_id`) | composite |
| submissions | `id` | surrogate |
| rooms | `id` | surrogate |
| room_pads | (`room_id`,`language`) | composite |
| room_members | (`room_id`,`user_id`) | composite |
| chat_messages | `id` | surrogate |
| matchmaking_queue | `id` | surrogate |
| lab_progress | (`user_id`,`lab_slug`) | composite |
| refactor_runs | `id` | surrogate |

### 3.2 Unique constraints (7 + 1)

Uniques: `users.username` · `users.email` · `problems.slug` · `learn_lessons(track,slug)` · `rooms.code` · `matchmaking_queue.user_id`. Additionally `lab_progress(user_id,lab_slug)` enforces once-per-user completion through its composite primary key.

### 3.3 Foreign keys (18)

| # | From → To | On delete | Why |
|---|---|---|---|
| 1 | learn_progress.user_id → users | CASCADE | progress is meaningless without the user |
| 2 | learn_progress.lesson_id → learn_lessons | CASCADE | progress is meaningless without the lesson |
| 3 | submissions.user_id → users | CASCADE | submissions belong to the account |
| 4 | submissions.problem_id → problems | CASCADE | runs belong to the problem |
| 5 | rooms.owner_id → users | **SET NULL** | a room must survive its owner leaving |
| 6 | rooms.problem_id → problems | **SET NULL** | a room must survive problem removal |
| 7 | room_pads.room_id → rooms | CASCADE | pads live inside the room |
| 8 | room_members.room_id → rooms | CASCADE | membership dies with the room |
| 9 | room_members.user_id → users | CASCADE | membership dies with the account |
| 10 | chat_messages.room_id → rooms | CASCADE | chat log dies with the room |
| 11 | chat_messages.user_id → users | **SET NULL** | keep the text, drop the author link |
| 12 | matchmaking_queue.user_id → users | CASCADE | queue entry tied to account |
| 13 | lab_progress.user_id → users | CASCADE | badge dies with the account |
| 14 | refactor_runs.user_id → users | CASCADE | score history dies with the account |
| 15 | problems.ai_user_id → users | CASCADE | 🤖 AI-generated set dies with the account |

**Logical (non-FK) reference:** `learn_lessons.problem_slug` → `problems.slug` — intentionally not enforced (content independence); shown as a dashed link in the ER diagram. Enforced instead at seed time (dangling-link check = 0).

### 3.4 Secondary indexes (5 explicit)

| Index | Columns | Serves |
|---|---|---|
| idx_lessons_track | learn_lessons(`track`,`position`) | track listing, already sorted |
| idx_sub_user | submissions(`user_id`) | profile history |
| idx_sub_problem | submissions(`problem_id`) | per-problem stats |
| idx_chat_room | chat_messages(`room_id`,`id`) | latest-N chat fetch (filter + sort) |
| uq_lesson | learn_lessons(`track`,`slug`) | uniqueness + track lookups |
| idx_rr_user | refactor_runs(`user_id`) | "my best scores" |
| idx_rr_chal | refactor_runs(`challenge_slug`) | per-challenge stats |

### 3.5 Other integrity rules

- `NOT NULL` everywhere except genuinely optional data (`bio`, `last_seen`, `runtime_ms`, owner/author links).
- `ENUM('easy','medium','hard')` rejects invalid difficulty at the engine.
- Sensible `DEFAULT`s (`rating=1200`, `avatar_color`, `is_live=1`, timestamps) make INSERTs minimal and consistent.

---

## 4. Normalization

| Normal form | Status | Evidence |
|---|---|---|
| **1NF** (atomic values) | ✅ | No multi-valued columns in relational facts. (`tags`, `tests_json`, `starters_json` hold *content blobs*, not facts other tables join on — a documented, intentional exception.) |
| **2NF** (no partial dependency) | ✅ | Every composite-PK table's non-key attributes depend on the **whole** key: `learn_progress.completed_at` needs user+lesson; `room_pads.content` needs room+language; `room_members.role/joined_at` need room+user. |
| **3NF** (no transitive dependency) | ✅ | Non-key attributes depend only on their key. Examples: `users.display_name/avatar_color/rating` depend on `id`, not on `username`; `rooms.name/language` depend on `id`, not on joinable `code`. No column derives from another non-key column. |

**Deliberate denormalizations (and why they're safe):**

1. `problems.tags` comma-list — display/search hint only, never a join key.
2. JSON content columns (`tests_json`, `starters_json`) — opaque payloads rendered by the app; normalizing them into child tables would add joins without any query benefit.
3. `matchmaking_queue.room_code` duplicates `rooms.code` textually — a transient pointer; the queue row is deleted after matching.

---

## 5. Relationship Cardinalities

| Relationship | Cardinality |
|---|---|
| users ↔ problems (via submissions) | M : N |
| users ↔ learn_lessons (via learn_progress) | M : N |
| users ↔ rooms (via room_members) | M : N |
| users → rooms (owner) | 1 : N (optional) |
| problems → rooms | 1 : N (optional) |
| rooms → room_pads | 1 : N (one per language) |
| rooms → chat_messages | 1 : N |
| users → chat_messages | 1 : N (optional author) |
| users → matchmaking_queue | 1 : 1 (UNIQUE user_id) |
| users → refactor_runs | 1 : N |
| users ↔ labs (via lab_progress) | M : N (labs code-defined, slug-keyed) |
| learn_lessons → problems | N : 1, **logical** (via slug, dashed in ER) |

---

## 6. Row Volumes (current seed)

| Table | Rows |
|---|---|
| problems | 526 |
| learn_lessons | 240 (15 tracks × 16) |
| users | 4 demo accounts (unbounded by design) |
| meta | 1 (`schema_version = 8`) |
| rooms / pads / members / chat / submissions / queue / lab_progress / refactor_runs | demo-seeded, grow at runtime |
| problems (`ai_user_id` rows) | 0 at seed — grow when users clear the board (🤖 AI sets) |

---

## 7. Representative Queries the Design Supports

| Feature page | Query pattern | Served by |
|---|---|---|
| Practice list + filters | `WHERE difficulty=? AND category=? ORDER BY id` | PK scan + predicate |
| Track listing | `WHERE track=? ORDER BY position` | `idx_lessons_track` (covering sort) |
| Learn progress ring | `JOIN learn_progress ON lesson_id … WHERE user_id=?` | composite PK lookup |
| Leaderboard | `ORDER BY rating DESC` on users | single-table sort |
| Room state load | rooms + pads + members + latest 50 chat | PKs + `idx_chat_room` |
| Practice list visibility | `WHERE ai_user_id IS NULL OR ai_user_id=?` (canonical + my 🤖 AI sets) | `idx_ai_user` |
| AI-set eligibility | `COUNT(DISTINCT problem_id) WHERE status='pass' AND ai_user_id IS NULL` | covering via `idx_sub_user` |
| Matchmaking | `WHERE language=? AND difficulty=? AND status='waiting' ORDER BY created_at` (FIFO) | queue scan |
| "My submissions" | `WHERE user_id=? ORDER BY id DESC` | `idx_sub_user` |
| Labs list ticks | `SELECT lab_slug FROM lab_progress WHERE user_id=?` | composite PK prefix |
| Refactor best scores | `MAX(score) … GROUP BY challenge_slug WHERE user_id=?` | `idx_rr_user` |

---

## 8. Security & Integrity Layering

| Layer | Mechanism |
|---|---|
| DB engine | FKs, UNIQUEs, ENUMs, NOT NULL — invalid states unrepresentable |
| PHP/PDO | 100% prepared statements — SQL injection prevented |
| App | bcrypt password hashing, CSRF tokens on every mutation, `e()` output escaping, HTML whitelist for rich content |
| Filesystem | `database/data/.htaccess` denies direct access to the SQLite file |
