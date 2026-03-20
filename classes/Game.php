<?php

class Game {
    private $conn;
    private int $userId;

    public function __construct($conn, int $userId) {
        $this->conn   = $conn;
        $this->userId = $userId;
    }

    public function getAllGames(): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM games WHERE user_id = ? AND status != 'wishlist' AND (game_type = 'game' OR game_type IS NULL) ORDER BY rating DESC, title ASC"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getGamesByStatus(string $status): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM games WHERE user_id = ? AND status = ? AND (game_type = 'game' OR game_type IS NULL) ORDER BY IF(rank_order = 0, 999999, rank_order) ASC, rating DESC"
        );
        $stmt->bind_param("is", $this->userId, $status);
        $stmt->execute();
        $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $games;
    }

    public function getDLCsForGame(int $parentId): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM games WHERE user_id = ? AND parent_game_id = ? AND game_type = 'dlc' ORDER BY rating DESC, title ASC"
        );
        $stmt->bind_param("ii", $this->userId, $parentId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getPlayedByGenre(string $genre): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM games WHERE user_id = ? AND status = 'played' AND (game_type = 'game' OR game_type IS NULL) AND genre = ? ORDER BY rating DESC, title ASC"
        );
        $stmt->bind_param("is", $this->userId, $genre);
        $stmt->execute();
        $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $games;
    }

    public function getPlayedGenres(): array {
        $stmt = $this->conn->prepare(
            "SELECT DISTINCT genre FROM games WHERE user_id = ? AND status = 'played' AND (game_type = 'game' OR game_type IS NULL) AND genre IS NOT NULL AND genre != '' ORDER BY genre ASC"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_column($rows, 'genre');
    }

    public function getWishlist(): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM games WHERE user_id = ? AND status = 'wishlist' ORDER BY wishlist_rank ASC, created_at DESC"
        );
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getNextWishlistRank(): int {
        $stmt = $this->conn->prepare("SELECT MAX(wishlist_rank) AS max_rank FROM games WHERE user_id = ? AND status = 'wishlist'");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return ((int)($row['max_rank'] ?? 0)) + 1;
    }

    public function getGameById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM games WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $this->userId);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $game ?: null;
    }

    public function addGame(
        string  $title,
        string  $platform,
        string  $genre,
        ?int    $release_year,
        ?string $cover_image,
        float   $rating,
        float   $enjoyability,
        float   $replayability,
        string  $status,
        string  $review,
        int     $playtime,
        string  $replay_status,
        int     $wishlist_rank,
        string  $game_type = 'game',
        ?int    $parent_game_id = null
    ): bool {
        $stmt = $this->conn->prepare(
            "INSERT INTO games
             (user_id, title, platform, genre, release_year, cover_image, rating, enjoyability, replayability, status, review, playtime, replay_status, wishlist_rank, game_type, parent_game_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isssisdddssisisi",
            $this->userId,
            $title, $platform, $genre, $release_year, $cover_image,
            $rating, $enjoyability, $replayability,
            $status, $review, $playtime, $replay_status, $wishlist_rank,
            $game_type, $parent_game_id
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateGame(
        int     $id,
        string  $title,
        string  $platform,
        string  $genre,
        ?int    $release_year,
        ?string $cover_image,
        float   $rating,
        float   $enjoyability,
        float   $replayability,
        string  $status,
        string  $review,
        int     $playtime,
        string  $replay_status,
        int     $wishlist_rank,
        string  $game_type = 'game',
        ?int    $parent_game_id = null
    ): bool {
        if ($cover_image !== null) {
            $stmt = $this->conn->prepare(
                "UPDATE games SET title=?, platform=?, genre=?, release_year=?, cover_image=?,
                 rating=?, enjoyability=?, replayability=?, status=?, review=?, playtime=?,
                 replay_status=?, wishlist_rank=?, game_type=?, parent_game_id=? WHERE id=? AND user_id=?"
            );
            $stmt->bind_param("sssisdddssisisiii",
                $title, $platform, $genre, $release_year, $cover_image,
                $rating, $enjoyability, $replayability,
                $status, $review, $playtime, $replay_status, $wishlist_rank,
                $game_type, $parent_game_id, $id, $this->userId
            );
        } else {
            $stmt = $this->conn->prepare(
                "UPDATE games SET title=?, platform=?, genre=?, release_year=?,
                 rating=?, enjoyability=?, replayability=?, status=?, review=?, playtime=?,
                 replay_status=?, wishlist_rank=?, game_type=?, parent_game_id=? WHERE id=? AND user_id=?"
            );
            $stmt->bind_param("sssidddssisisiii",
                $title, $platform, $genre, $release_year,
                $rating, $enjoyability, $replayability,
                $status, $review, $playtime, $replay_status, $wishlist_rank,
                $game_type, $parent_game_id, $id, $this->userId
            );
        }
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function moveToRanking(
        int    $id,
        float  $rating,
        float  $enjoyability,
        float  $replayability,
        string $replay_status,
        string $review,
        int    $playtime
    ): bool {
        $stmt = $this->conn->prepare(
            "UPDATE games SET status='played', rating=?, enjoyability=?, replayability=?,
             replay_status=?, review=?, playtime=?, wishlist_rank=0 WHERE id=? AND user_id=?"
        );
        $stmt->bind_param("dddssiii",
            $rating, $enjoyability, $replayability,
            $replay_status, $review, $playtime, $id, $this->userId
        );
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateWishlistRank(int $id, int $rank): bool {
        $stmt = $this->conn->prepare("UPDATE games SET wishlist_rank=? WHERE id=? AND user_id=?");
        $stmt->bind_param("iii", $rank, $id, $this->userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function updateRankOrder(int $id, int $order): bool {
        $stmt = $this->conn->prepare("UPDATE games SET rank_order=? WHERE id=? AND user_id=?");
        $stmt->bind_param("iii", $order, $id, $this->userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function reRankByRating(string $status): void {
        $stmt = $this->conn->prepare(
            "SELECT id FROM games WHERE user_id = ? AND status = ? ORDER BY rating DESC, title ASC"
        );
        $stmt->bind_param("is", $this->userId, $status);
        $stmt->execute();
        $games = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($games as $i => $g) {
            $this->updateRankOrder((int)$g['id'], $i + 1);
        }
    }

    public function deleteGame(int $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM games WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $this->userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function getStats(): array {
        $stmt = $this->conn->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(status = 'played')   AS played,
                SUM(status = 'playing')  AS playing,
                SUM(status = 'backlog')  AS backlog,
                SUM(status = 'wishlist') AS wishlist,
                ROUND(AVG(NULLIF(rating, 0)), 1)        AS avg_rating,
                ROUND(AVG(NULLIF(enjoyability, 0)), 1)  AS avg_enjoy,
                ROUND(AVG(NULLIF(replayability, 0)), 1) AS avg_replay,
                SUM(playtime) AS total_hours
            FROM games
            WHERE user_id = ? AND (game_type = 'game' OR game_type IS NULL)
        ");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?? [];
    }

    public function getStatsByGenre(): array {
        $stmt = $this->conn->prepare("
            SELECT genre,
                COUNT(*) AS count,
                ROUND(AVG(NULLIF(rating,0)),1) AS avg_rating,
                ROUND(AVG(NULLIF(enjoyability,0)),1) AS avg_enjoy,
                ROUND(AVG(NULLIF(replayability,0)),1) AS avg_replay,
                SUM(playtime) AS total_hours
            FROM games
            WHERE user_id = ? AND status = 'played' AND genre != ''
            GROUP BY genre ORDER BY avg_rating DESC
        ");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getStatsByPlatform(): array {
        $stmt = $this->conn->prepare("
            SELECT platform,
                COUNT(*) AS count,
                ROUND(AVG(NULLIF(rating,0)),1) AS avg_rating,
                SUM(playtime) AS total_hours
            FROM games
            WHERE user_id = ? AND status = 'played' AND platform != ''
            GROUP BY platform ORDER BY count DESC
        ");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getMostPlayedGenre(): string {
        $stmt = $this->conn->prepare("
            SELECT genre, SUM(playtime) AS hrs FROM games
            WHERE user_id = ? AND status='played' AND genre != ''
            GROUP BY genre ORDER BY hrs DESC LIMIT 1
        ");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row['genre'] ?? '—';
    }

    public function getBestGameOfYear(int $year): ?array {
        $stmt = $this->conn->prepare("
            SELECT * FROM games WHERE user_id = ? AND status='played' AND YEAR(created_at)=?
            ORDER BY rating DESC LIMIT 1
        ");
        $stmt->bind_param("ii", $this->userId, $year);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    // ── Activity log ──────────────────────────────────────────────────────────

    public function logActivity(string $action, ?int $gameId, string $gameTitle, string $meta = ''): void {
        $stmt = $this->conn->prepare(
            "INSERT INTO activity_log (user_id, action, game_id, game_title, meta) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->bind_param("isiss", $this->userId, $action, $gameId, $gameTitle, $meta);
        $stmt->execute();
        $stmt->close();
    }

    public function getActivity(int $limit = 30): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bind_param("ii", $this->userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getFeedActivity(int $limit = 60): array {
        $stmt = $this->conn->prepare("
            SELECT al.*, a.username, a.avatar
            FROM activity_log al
            JOIN account a ON a.id = al.user_id
            JOIN followers f ON f.following_id = al.user_id
            WHERE f.follower_id = ?
            ORDER BY al.created_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("ii", $this->userId, $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    // ── Followers ─────────────────────────────────────────────────────────────

    public function isFollowing(int $targetId): bool {
        $stmt = $this->conn->prepare("SELECT 1 FROM followers WHERE follower_id=? AND following_id=?");
        $stmt->bind_param("ii", $this->userId, $targetId);
        $stmt->execute();
        $res = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $res;
    }

    public function follow(int $targetId): void {
        $stmt = $this->conn->prepare("INSERT IGNORE INTO followers (follower_id, following_id) VALUES (?,?)");
        $stmt->bind_param("ii", $this->userId, $targetId);
        $stmt->execute();
        $stmt->close();
    }

    public function unfollow(int $targetId): void {
        $stmt = $this->conn->prepare("DELETE FROM followers WHERE follower_id=? AND following_id=?");
        $stmt->bind_param("ii", $this->userId, $targetId);
        $stmt->execute();
        $stmt->close();
    }

    public function getFollowerCount(int $userId): int {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM followers WHERE following_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count;
    }

    public function getFollowingCount(int $userId): int {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM followers WHERE follower_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $count = (int)$stmt->get_result()->fetch_row()[0];
        $stmt->close();
        return $count;
    }

    public function getFollowing(int $userId): array {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.username, a.avatar FROM followers f
            JOIN account a ON a.id = f.following_id
            WHERE f.follower_id = ? ORDER BY f.created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getFollowers(int $userId): array {
        $stmt = $this->conn->prepare("
            SELECT a.id, a.username, a.avatar FROM followers f
            JOIN account a ON a.id = f.follower_id
            WHERE f.following_id = ? ORDER BY f.created_at DESC
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
?>
