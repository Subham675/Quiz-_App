-- =============================================
-- Quiz App Database Schema
-- Import this into phpMyAdmin
-- =============================================

CREATE DATABASE IF NOT EXISTS quiz_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quiz_app;

-- -----------------------------------------------
-- 1. users
-- -----------------------------------------------
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password        VARCHAR(255) NOT NULL,             -- bcrypt hash
    role            ENUM('user', 'admin') DEFAULT 'user',
    otp_code        INT(6) DEFAULT NULL,
    otp_expires_at  DATETIME DEFAULT NULL,
    otp_attempts    INT DEFAULT 0,
    last_otp_request DATETIME DEFAULT NULL,
    is_verified     TINYINT(1) DEFAULT 0,
    is_banned       TINYINT(1) DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------
-- 2. categories
-- -----------------------------------------------
CREATE TABLE categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(100) NOT NULL,
    slug        VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- -----------------------------------------------
-- 3. quizzes
-- -----------------------------------------------
CREATE TABLE quizzes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    category_id         INT NOT NULL,
    title               VARCHAR(200) NOT NULL,
    description         TEXT DEFAULT NULL,
    time_limit_seconds  INT DEFAULT 600,               -- default 10 mins
    total_marks         INT DEFAULT 0,
    is_ai_generated     TINYINT(1) DEFAULT 0,
    is_active           TINYINT(1) DEFAULT 1,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- 4. questions
-- -----------------------------------------------
CREATE TABLE questions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id         INT NOT NULL,
    question_text   TEXT NOT NULL,
    marks           INT DEFAULT 1,
    order_index     INT DEFAULT 0,                     -- for randomization control
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- 5. options  (4 per question, one is correct)
-- -----------------------------------------------
CREATE TABLE options (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    question_id     INT NOT NULL,
    option_text     TEXT NOT NULL,
    is_correct      TINYINT(1) DEFAULT 0,
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- 6. attempts
-- -----------------------------------------------
CREATE TABLE attempts (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    quiz_id             INT NOT NULL,
    score               INT DEFAULT 0,
    total_marks         INT DEFAULT 0,
    time_taken_seconds  INT DEFAULT 0,
    is_completed        TINYINT(1) DEFAULT 0,
    email_sent          TINYINT(1) DEFAULT 0,
    started_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at        DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- 7. attempt_answers
-- -----------------------------------------------
CREATE TABLE attempt_answers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    attempt_id          INT NOT NULL,
    question_id         INT NOT NULL,
    selected_option_id  INT DEFAULT NULL,              -- NULL = skipped
    is_correct          TINYINT(1) DEFAULT 0,
    FOREIGN KEY (attempt_id)         REFERENCES attempts(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id)        REFERENCES questions(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_option_id) REFERENCES options(id) ON DELETE SET NULL
);

-- -----------------------------------------------
-- 8. certificates
-- -----------------------------------------------
CREATE TABLE certificates (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    attempt_id  INT NOT NULL UNIQUE,
    cert_path   VARCHAR(300) NOT NULL,                 -- e.g. uploads/certificates/cert_42.pdf
    unique_code VARCHAR(64) NOT NULL UNIQUE,           -- for verification URL
    issued_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)    REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (attempt_id) REFERENCES attempts(id) ON DELETE CASCADE
);

-- -----------------------------------------------
-- Seed: default admin account
-- Email: admin@quizapp.com
-- Password: Admin@1234
-- Change this immediately after import!
-- -----------------------------------------------
INSERT INTO users (name, email, password, role, is_verified)
VALUES (
    'Admin',
    'admin@quizapp.com',
    '$2y$12$bN0vqtqV016qHMkix1OpZecgf8c/8zlFbZ/Nnwqjrt5fJV0G7DKZm',
    'admin',
    1
);

-- Seed: sample categories
INSERT INTO categories (name, slug, description) VALUES
    ('General Knowledge', 'general-knowledge', 'Test your everyday knowledge'),
    ('Science',           'science',           'Physics, Chemistry, Biology'),
    ('Mathematics',       'mathematics',       'Numbers, equations, logic'),
    ('Technology',        'technology',        'Computers, software, internet'),
    ('History',           'history',           'World and Indian history');
