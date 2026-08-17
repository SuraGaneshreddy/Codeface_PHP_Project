-- Codeface — MySQL/MariaDB schema (auto-applied on first run by lib/db.php,
-- or import manually via phpMyAdmin)
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(20)  NOT NULL UNIQUE,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name  VARCHAR(50)  NOT NULL DEFAULT '',
  bio           TEXT         NULL,
  avatar        VARCHAR(40)  NULL COMMENT 'uploaded profile photo filename (data/avatars/), NULL = initials',
  avatar_color  VARCHAR(7)   NOT NULL DEFAULT '#6366f1',
  rating        INT          NOT NULL DEFAULT 1200,
  is_admin      TINYINT(1)   NOT NULL DEFAULT 0,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen     DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  otp_hash    VARCHAR(255) NOT NULL,
  expires_at  DATETIME NOT NULL,
  attempts    INT NOT NULL DEFAULT 0,
  used_at     DATETIME NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_resets_user (user_id),
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE problems (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(80) NOT NULL UNIQUE,
  title         VARCHAR(160) NOT NULL,
  difficulty    ENUM('easy','medium','hard') NOT NULL,
  category      VARCHAR(40) NOT NULL DEFAULT '',
  description   MEDIUMTEXT NOT NULL,
  tags          VARCHAR(255) NOT NULL DEFAULT '',
  function_name VARCHAR(60) NOT NULL,
  starter_js    MEDIUMTEXT NOT NULL,
  solution_js   MEDIUMTEXT NOT NULL,
  tests_json    MEDIUMTEXT NOT NULL,
  starters_json MEDIUMTEXT NULL,
  points        INT NOT NULL DEFAULT 10,
  ai_user_id    INT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_user (ai_user_id),
  CONSTRAINT fk_prob_aiuser FOREIGN KEY (ai_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta (
  `key`   VARCHAR(40) PRIMARY KEY,
  `value` VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learn_lessons (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  track          VARCHAR(20) NOT NULL,
  position       INT NOT NULL,
  slug           VARCHAR(60) NOT NULL,
  title          VARCHAR(160) NOT NULL,
  concept        MEDIUMTEXT NOT NULL,
  example_code   MEDIUMTEXT NOT NULL,
  example_output MEDIUMTEXT NOT NULL,
  try_code       MEDIUMTEXT NOT NULL,
  problem_slug   VARCHAR(80) NOT NULL DEFAULT '',
  minutes        INT NOT NULL DEFAULT 5,
  UNIQUE KEY uq_lesson (track, slug),
  INDEX idx_lessons_track (track, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE learn_progress (
  user_id      INT NOT NULL,
  lesson_id    INT NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lesson_id),
  CONSTRAINT fk_lp_user   FOREIGN KEY (user_id)   REFERENCES users(id)          ON DELETE CASCADE,
  CONSTRAINT fk_lp_lesson FOREIGN KEY (lesson_id) REFERENCES learn_lessons(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submissions (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  problem_id INT NOT NULL,
  status     VARCHAR(10) NOT NULL,
  code       MEDIUMTEXT NOT NULL,
  passed     INT NOT NULL DEFAULT 0,
  total      INT NOT NULL DEFAULT 0,
  runtime_ms FLOAT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sub_user (user_id),
  INDEX idx_sub_problem (problem_id),
  CONSTRAINT fk_sub_user    FOREIGN KEY (user_id)    REFERENCES users(id)    ON DELETE CASCADE,
  CONSTRAINT fk_sub_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rooms (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  code       VARCHAR(12) NOT NULL UNIQUE,
  name       VARCHAR(80) NOT NULL,
  owner_id   INT NULL,
  problem_id INT NULL,
  language   VARCHAR(20) NOT NULL DEFAULT 'javascript',
  is_live    TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_room_owner   FOREIGN KEY (owner_id)   REFERENCES users(id)    ON DELETE SET NULL,
  CONSTRAINT fk_room_problem FOREIGN KEY (problem_id) REFERENCES problems(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_pads (
  room_id        INT NOT NULL,
  language       VARCHAR(20) NOT NULL,
  content        MEDIUMTEXT NOT NULL,
  version        INT NOT NULL DEFAULT 0,
  last_editor_id INT NULL,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (room_id, language),
  CONSTRAINT fk_pad_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE room_members (
  room_id   INT NOT NULL,
  user_id   INT NOT NULL,
  role      VARCHAR(20) NOT NULL DEFAULT 'participant',
  joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen DATETIME NULL,
  left_at   DATETIME NULL,
  PRIMARY KEY (room_id, user_id),
  CONSTRAINT fk_member_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_member_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE chat_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  room_id    INT NOT NULL,
  user_id    INT NULL,
  body       VARCHAR(500) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_chat_room (room_id, id),
  CONSTRAINT fk_chat_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matchmaking_queue (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL UNIQUE,
  language   VARCHAR(20) NOT NULL,
  difficulty VARCHAR(10) NOT NULL,
  status     VARCHAR(10) NOT NULL DEFAULT 'waiting',
  room_code  VARCHAR(12) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_mm_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lab_progress (
  user_id      INT NOT NULL,
  lab_slug     VARCHAR(60) NOT NULL,
  completed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, lab_slug),
  CONSTRAINT fk_labp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE refactor_runs (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  challenge_slug VARCHAR(60) NOT NULL,
  score          INT NOT NULL DEFAULT 0,
  tests_passed   INT NOT NULL DEFAULT 0,
  tests_total    INT NOT NULL DEFAULT 0,
  metrics        MEDIUMTEXT NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_rr_user (user_id),
  INDEX idx_rr_chal (challenge_slug),
  CONSTRAINT fk_rr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
