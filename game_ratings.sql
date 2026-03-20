-- ============================================
--  Game Ratings System — Database Schema
--  Database: game_ratings
-- ============================================

CREATE DATABASE IF NOT EXISTS game_ratings
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE game_ratings;

-- ===== ACCOUNTS =====
CREATE TABLE IF NOT EXISTS account (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(100)  NOT NULL,
    email      VARCHAR(255)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===== GAMES =====
CREATE TABLE IF NOT EXISTS games (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(255) NOT NULL,
    platform       VARCHAR(100),
    genre          VARCHAR(100),
    release_year   INT,
    cover_image    VARCHAR(255),
    rating         DECIMAL(3,1) DEFAULT 0,
    enjoyability   DECIMAL(3,1) DEFAULT 0,
    replayability  DECIMAL(3,1) DEFAULT 0,
    replay_status  ENUM('not_replayed','replayed','will_replay','wont_replay') DEFAULT 'not_replayed',
    wishlist_rank  INT          DEFAULT 0,
    rank_order     INT          DEFAULT 0,
    status         ENUM('played','playing','backlog','wishlist') DEFAULT 'played',
    review         TEXT,
    playtime       INT          DEFAULT 0,
    created_at     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
);

-- ===== FAVORIETE KARAKTERS =====
CREATE TABLE IF NOT EXISTS characters (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    game_id     INT          NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    image       VARCHAR(255),
    is_top_pick TINYINT(1)  DEFAULT 0,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);
