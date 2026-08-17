-- Codeface — SQLite schema (auto-applied on first run)
CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE,
  email         TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  display_name  TEXT NOT NULL DEFAULT '',
  bio           TEXT NOT NULL DEFAULT '',
  avatar        TEXT NULL,             -- uploaded profile photo filename (data/avatars/), NULL = initials
  avatar_color  TEXT NOT NULL DEFAULT '#6366f1',
  rating        INTEGER NOT NULL DEFAULT 1200,
  is_admin      INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT NOT NULL DEFAULT (datetime('now')),
  last_seen     TEXT
);

CREATE TABLE password_resets (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id     INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  otp_hash    TEXT NOT NULL,
  expires_at  TEXT NOT NULL,
  attempts    INTEGER NOT NULL DEFAULT 0,
  used_at     TEXT,
  created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_resets_user ON password_resets(user_id);

CREATE TABLE problems (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  slug          TEXT NOT NULL UNIQUE,
  title         TEXT NOT NULL,
  difficulty    TEXT NOT NULL CHECK (difficulty IN ('easy','medium','hard')),
  category      TEXT NOT NULL DEFAULT '',
  description   TEXT NOT NULL,
  tags          TEXT NOT NULL DEFAULT '',
  function_name TEXT NOT NULL,
  starter_js    TEXT NOT NULL,
  solution_js   TEXT NOT NULL DEFAULT '',
  tests_json    TEXT NOT NULL,
  starters_json TEXT,
  points        INTEGER NOT NULL DEFAULT 10,
  ai_user_id    INTEGER NULL REFERENCES users(id) ON DELETE CASCADE,
  created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_ai_user ON problems(ai_user_id);

CREATE TABLE meta (
  key   TEXT PRIMARY KEY,
  value TEXT
);

CREATE TABLE learn_lessons (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  track          TEXT NOT NULL,
  position       INTEGER NOT NULL,
  slug           TEXT NOT NULL,
  title          TEXT NOT NULL,
  concept        TEXT NOT NULL,
  example_code   TEXT NOT NULL DEFAULT '',
  example_output TEXT NOT NULL DEFAULT '',
  try_code       TEXT NOT NULL DEFAULT '',
  problem_slug   TEXT NOT NULL DEFAULT '',
  minutes        INTEGER NOT NULL DEFAULT 5,
  UNIQUE(track, slug)
);
CREATE INDEX idx_lessons_track ON learn_lessons(track, position);

CREATE TABLE learn_progress (
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lesson_id    INTEGER NOT NULL REFERENCES learn_lessons(id) ON DELETE CASCADE,
  completed_at TEXT NOT NULL DEFAULT (datetime('now')),
  PRIMARY KEY (user_id, lesson_id)
);

CREATE TABLE submissions (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  problem_id INTEGER NOT NULL REFERENCES problems(id) ON DELETE CASCADE,
  status     TEXT NOT NULL,
  code       TEXT NOT NULL,
  passed     INTEGER NOT NULL DEFAULT 0,
  total      INTEGER NOT NULL DEFAULT 0,
  runtime_ms REAL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_sub_user    ON submissions(user_id);
CREATE INDEX idx_sub_problem ON submissions(problem_id);

CREATE TABLE rooms (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  code       TEXT NOT NULL UNIQUE,
  name       TEXT NOT NULL,
  owner_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  problem_id INTEGER REFERENCES problems(id) ON DELETE SET NULL,
  language   TEXT NOT NULL DEFAULT 'javascript',
  is_live    INTEGER NOT NULL DEFAULT 1,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE room_pads (
  room_id        INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  language       TEXT NOT NULL,
  content        TEXT NOT NULL DEFAULT '',
  version        INTEGER NOT NULL DEFAULT 0,
  last_editor_id INTEGER,
  updated_at     TEXT NOT NULL DEFAULT (datetime('now')),
  PRIMARY KEY (room_id, language)
);

CREATE TABLE room_members (
  room_id   INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  user_id   INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  role      TEXT NOT NULL DEFAULT 'participant',
  joined_at TEXT NOT NULL DEFAULT (datetime('now')),
  last_seen TEXT,
  left_at   TEXT,
  PRIMARY KEY (room_id, user_id)
);

CREATE TABLE chat_messages (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  room_id    INTEGER NOT NULL REFERENCES rooms(id) ON DELETE CASCADE,
  user_id    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  body       TEXT NOT NULL,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_chat_room ON chat_messages(room_id, id);

CREATE TABLE matchmaking_queue (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id    INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  language   TEXT NOT NULL,
  difficulty TEXT NOT NULL,
  status     TEXT NOT NULL DEFAULT 'waiting',
  room_code  TEXT,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE lab_progress (
  user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lab_slug     TEXT NOT NULL,
  completed_at TEXT NOT NULL DEFAULT (datetime('now')),
  PRIMARY KEY (user_id, lab_slug)
);

CREATE TABLE refactor_runs (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  challenge_slug TEXT NOT NULL,
  score          INTEGER NOT NULL DEFAULT 0,
  tests_passed   INTEGER NOT NULL DEFAULT 0,
  tests_total    INTEGER NOT NULL DEFAULT 0,
  metrics        TEXT,
  created_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_rr_user ON refactor_runs(user_id);
CREATE INDEX idx_rr_chal ON refactor_runs(challenge_slug);
