-- database/ruang_hukum.sql - skema awal RuangHukum (sudah termasuk kolom v2)
CREATE DATABASE IF NOT EXISTS ruang_hukum CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ruang_hukum;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications, activity_log, banned_words, likes, replies, posts, categories, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(40) NOT NULL,
    email VARCHAR(120) NULL,
    password_hash VARCHAR(255) NULL,
    is_guest TINYINT(1) NOT NULL DEFAULT 1,
    role ENUM('user','lawyer','admin') NOT NULL DEFAULT 'user',
    status ENUM('active','muted','suspended','banned') NOT NULL DEFAULT 'active',
    restricted_until DATETIME NULL,
    penalty_points TINYINT UNSIGNED NOT NULL DEFAULT 0,
    penalty_updated_at DATETIME NULL,
    last_ip_hash CHAR(64) NULL,
    avatar_color VARCHAR(9) NOT NULL DEFAULT '#0f766e',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    KEY idx_users_role (role),
    KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(60) NOT NULL,
    slug VARCHAR(60) NOT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE posts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    status ENUM('published','pending','hidden','removed') NOT NULL DEFAULT 'published',
    flag_reason VARCHAR(100) NULL,
    content_hash CHAR(64) NULL,
    moderated_by INT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_posts_status (status, created_at),
    KEY idx_posts_hash (content_hash),
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_posts_cat FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE RESTRICT,
    CONSTRAINT fk_posts_moderator FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE replies (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    status ENUM('published','pending','hidden','removed') NOT NULL DEFAULT 'published',
    flag_reason VARCHAR(100) NULL,
    content_hash CHAR(64) NULL,
    moderated_by INT UNSIGNED NULL,
    moderated_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_replies_post (post_id, created_at),
    KEY idx_replies_status (status, created_at),
    KEY idx_replies_hash (content_hash),
    CONSTRAINT fk_replies_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_replies_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_replies_moderator FOREIGN KEY (moderated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE likes (
    user_id INT UNSIGNED NOT NULL,
    post_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, post_id),
    CONSTRAINT fk_likes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_likes_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    type ENUM('reply','lawyer_reply','like','recategorized','content_hidden','content_removed','content_approved','penalty','lawyer_result') NOT NULL,
    actor_id INT UNSIGNED NULL,
    post_id INT UNSIGNED NULL,
    reply_id INT UNSIGNED NULL,
    message VARCHAR(255) NULL,
    group_count INT UNSIGNED NOT NULL DEFAULT 1,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id, is_read, updated_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_notif_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_notif_reply FOREIGN KEY (reply_id) REFERENCES replies (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE banned_words (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    word VARCHAR(60) NOT NULL,
    kind ENUM('profanity_mild','profanity_severe','hate','spam_strong','spam_weak') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_banned_word (word)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    ip_hash CHAR(64) NOT NULL,
    action VARCHAR(30) NOT NULL,
    detail VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_log_user (user_id, action, created_at),
    KEY idx_log_ip (ip_hash, action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (id, name, slug, is_system) VALUES
(1,'Hukum Pidana','hukum-pidana',0),(2,'Hukum Perdata','hukum-perdata',0),(3,'Hukum Keluarga','hukum-keluarga',0),
(4,'Ketenagakerjaan','ketenagakerjaan',0),(5,'Hukum Konsumen','hukum-konsumen',0),(6,'Pertanahan','pertanahan',0),
(7,'Belum Diketahui','belum-diketahui',1);

INSERT INTO banned_words (word, kind) VALUES
('anjing','profanity_severe'),('bangsat','profanity_severe'),('bajingan','profanity_severe'),('kontol','profanity_severe'),
('memek','profanity_severe'),('ngentot','profanity_severe'),('jancok','profanity_severe'),('jancuk','profanity_severe'),
('asu','profanity_severe'),('keparat','profanity_severe'),('babi','profanity_severe'),
('anjay','profanity_mild'),('anjir','profanity_mild'),('cok','profanity_mild'),('tolol','profanity_mild'),('goblok','profanity_mild'),
('bego','profanity_mild'),('idiot','profanity_mild'),('sinting','profanity_mild'),('brengsek','profanity_mild'),
('kampret','profanity_mild'),('sialan','profanity_mild'),('bacot','profanity_mild'),('tai','profanity_mild'),
('gacor','spam_strong'),('maxwin','spam_strong'),('togel','spam_strong'),('judol','spam_strong'),('sbobet','spam_strong'),
('link alternatif','spam_strong'),('rtp live','spam_strong'),('situs slot','spam_strong'),
('slot','spam_weak'),('deposit','spam_weak'),('bonus','spam_weak'),('jackpot','spam_weak'),('daftar sekarang','spam_weak'),
('klik link','spam_weak'),('wa.me','spam_weak'),('t.me','spam_weak'),('bit.ly','spam_weak');
