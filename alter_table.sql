-- ============================================================
--  GameVault — Database update script
--  Run dit als je de database al hebt aangemaakt via game_ratings.sql
--  en de nieuwe kolommen wilt toevoegen
-- ============================================================

USE game_ratings;

ALTER TABLE games
    ADD COLUMN IF NOT EXISTS user_id        INT          DEFAULT 1            AFTER id,
    ADD COLUMN IF NOT EXISTS game_type      ENUM('game','dlc') DEFAULT 'game' AFTER status,
    ADD COLUMN IF NOT EXISTS parent_game_id INT DEFAULT NULL AFTER game_type,
    ADD COLUMN IF NOT EXISTS enjoyability   DECIMAL(3,1) DEFAULT 0    AFTER rating,
    ADD COLUMN IF NOT EXISTS replayability  DECIMAL(3,1) DEFAULT 0    AFTER enjoyability,
    ADD COLUMN IF NOT EXISTS replay_status  ENUM('not_replayed','replayed','will_replay','wont_replay')
                              DEFAULT 'not_replayed'    AFTER replayability,
    ADD COLUMN IF NOT EXISTS wishlist_rank  INT          DEFAULT 0    AFTER replay_status,
    ADD COLUMN IF NOT EXISTS rank_order INT DEFAULT 0 AFTER wishlist_rank,
    MODIFY COLUMN status ENUM('played','playing','backlog','wishlist') DEFAULT 'played';

-- Profiel uitbreidingen
ALTER TABLE account ADD COLUMN IF NOT EXISTS avatar VARCHAR(255) DEFAULT NULL;
ALTER TABLE account ADD COLUMN IF NOT EXISTS bio    VARCHAR(500) DEFAULT '';

-- Tags systeem
CREATE TABLE IF NOT EXISTS tags (
    id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT          NOT NULL DEFAULT 1,
    name    VARCHAR(100) NOT NULL,
    color   VARCHAR(7)   DEFAULT '#7c3aed',
    UNIQUE KEY unique_user_tag (user_id, name)
);
ALTER TABLE tags ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE tags DROP INDEX IF EXISTS name;
-- (unique constraint is now per user, not globally)

CREATE TABLE IF NOT EXISTS game_tags (
    game_id INT NOT NULL,
    tag_id  INT NOT NULL,
    PRIMARY KEY (game_id, tag_id),
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE,
    FOREIGN KEY (tag_id)  REFERENCES tags(id)  ON DELETE CASCADE
);

-- Speelsessie log
CREATE TABLE IF NOT EXISTS play_sessions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    game_id    INT          NOT NULL,
    played_date DATE        NOT NULL,
    note       VARCHAR(500) DEFAULT '',
    created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);

-- Franchise / Series tracker
CREATE TABLE IF NOT EXISTS series (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT          NOT NULL DEFAULT 1,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE series ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 1 AFTER id;

CREATE TABLE IF NOT EXISTS game_series (
    game_id      INT NOT NULL,
    series_id    INT NOT NULL,
    series_order INT DEFAULT 0,
    PRIMARY KEY (game_id),
    FOREIGN KEY (game_id)   REFERENCES games(id)  ON DELETE CASCADE,
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE CASCADE
);

-- Friends / volgers systeem
CREATE TABLE IF NOT EXISTS followers (
    follower_id  INT NOT NULL,
    following_id INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id),
    FOREIGN KEY (follower_id)  REFERENCES account(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES account(id) ON DELETE CASCADE
);

-- Activiteiten log
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    action     ENUM('game_added','status_changed','rating_changed','game_removed') NOT NULL,
    game_id    INT DEFAULT NULL,
    game_title VARCHAR(255) DEFAULT NULL,
    meta       VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES account(id) ON DELETE CASCADE
);

-- Favoriete karakters tabel
CREATE TABLE IF NOT EXISTS characters (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    game_id     INT          NOT NULL,
    name        VARCHAR(255) NOT NULL,
    description TEXT,
    image       VARCHAR(255),
    is_top_pick TINYINT(1)  DEFAULT 0,
    FOREIGN KEY (game_id) REFERENCES games(id) ON DELETE CASCADE
);
