CREATE TABLE IF NOT EXISTS rooms (
    code VARCHAR(8) PRIMARY KEY,
    mode VARCHAR(20) NOT NULL,
    answer_mode VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    players_json TEXT NOT NULL,
    state_json TEXT NOT NULL,
    version INT NOT NULL DEFAULT 1,
    created_at VARCHAR(32) NOT NULL,
    updated_at VARCHAR(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(40) NOT NULL,
    question TEXT NOT NULL,
    option_a TEXT NOT NULL,
    option_b TEXT NOT NULL,
    option_c TEXT NOT NULL,
    option_d TEXT NOT NULL,
    correct INT NOT NULL,
    created_at VARCHAR(32) NOT NULL,
    INDEX idx_questions_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
