-- Esquema de referencia para instalaciones MySQL nuevas.
-- La aplicacion aplica y registra automaticamente database/migrations/*.php.

CREATE TABLE IF NOT EXISTS schema_migrations (
    version VARCHAR(255) PRIMARY KEY,
    applied_at VARCHAR(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    code VARCHAR(8) PRIMARY KEY,
    mode VARCHAR(20) NOT NULL,
    answer_mode VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    players_json TEXT NOT NULL,
    state_json TEXT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    created_at VARCHAR(32) NOT NULL,
    updated_at VARCHAR(32) NOT NULL,
    creator_user_id BIGINT NULL,
    pack_id BIGINT NULL,
    pack_revision_id BIGINT NULL,
    pack_snapshot_json TEXT NULL,
    controller_token_hash VARCHAR(64) NULL,
    started_at VARCHAR(32) NULL,
    finished_at VARCHAR(32) NULL,
    winner_participant_id BIGINT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(40) NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct INT NOT NULL,
    created_at VARCHAR(32) NOT NULL,
    pack_revision_id BIGINT NULL,
    pack_category_id BIGINT NULL,
    INDEX idx_questions_category (category),
    INDEX idx_questions_revision (pack_revision_id, pack_category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(254) NOT NULL UNIQUE,
    display_name VARCHAR(40) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    email_verified_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL,
    updated_at VARCHAR(32) NOT NULL,
    deleted_at VARCHAR(32) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_sessions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    csrf_token VARCHAR(128) NOT NULL,
    last_activity_at VARCHAR(32) NOT NULL,
    expires_at VARCHAR(32) NOT NULL,
    created_at VARCHAR(32) NOT NULL,
    INDEX idx_auth_sessions_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS account_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,
    purpose VARCHAR(30) NOT NULL,
    token_hash VARCHAR(64) NOT NULL UNIQUE,
    expires_at VARCHAR(32) NOT NULL,
    used_at VARCHAR(32) NULL,
    created_at VARCHAR(32) NOT NULL,
    INDEX idx_account_tokens_user (user_id, purpose)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(40) NOT NULL,
    identifier_hash VARCHAR(64) NOT NULL,
    attempts INT NOT NULL,
    window_started_at VARCHAR(32) NOT NULL,
    blocked_until VARCHAR(32) NULL,
    UNIQUE KEY uq_auth_attempt (action, identifier_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS question_packs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    owner_user_id BIGINT NULL,
    name VARCHAR(120) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'user',
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    current_revision_id BIGINT NULL,
    created_at VARCHAR(32) NOT NULL,
    updated_at VARCHAR(32) NOT NULL,
    deleted_at VARCHAR(32) NULL,
    INDEX idx_question_packs_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pack_revisions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    pack_id BIGINT NOT NULL,
    revision_number INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    created_at VARCHAR(32) NOT NULL,
    activated_at VARCHAR(32) NULL,
    UNIQUE KEY uq_pack_revision (pack_id, revision_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pack_categories (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    revision_id BIGINT NOT NULL,
    slot INT NOT NULL,
    category_key VARCHAR(60) NOT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(16) NOT NULL,
    UNIQUE KEY uq_pack_category_slot (revision_id, slot),
    UNIQUE KEY uq_pack_category_key (revision_id, category_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS color_schemes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'system',
    owner_user_id BIGINT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at VARCHAR(32) NOT NULL,
    updated_at VARCHAR(32) NOT NULL,
    deleted_at VARCHAR(32) NULL,
    KEY idx_color_schemes_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS color_scheme_slots (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    color_scheme_id BIGINT NOT NULL,
    slot INT NOT NULL,
    color VARCHAR(16) NOT NULL,
    UNIQUE KEY uq_color_scheme_slot (color_scheme_id, slot)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS room_participants (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(8) NOT NULL,
    slot INT NOT NULL,
    user_id BIGINT NULL,
    name VARCHAR(100) NOT NULL,
    color VARCHAR(16) NOT NULL,
    token_hash VARCHAR(64) NULL,
    created_at VARCHAR(32) NOT NULL,
    UNIQUE KEY uq_room_participant_slot (room_code, slot),
    INDEX idx_room_participants_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS answer_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    room_code VARCHAR(8) NOT NULL,
    participant_id BIGINT NOT NULL,
    category_slot INT NOT NULL,
    question_id BIGINT NULL,
    correct INT NOT NULL,
    answer_mode VARCHAR(20) NOT NULL,
    sequence_no INT NOT NULL,
    answered_at VARCHAR(32) NOT NULL,
    UNIQUE KEY uq_answer_event_sequence (room_code, sequence_no),
    INDEX idx_answer_events_participant (participant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
