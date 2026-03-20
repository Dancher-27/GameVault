<?php
class Series {
    private mysqli $conn;
    private int $userId;

    public function __construct(mysqli $conn, int $userId) {
        $this->conn   = $conn;
        $this->userId = $userId;
    }

    public function getAllSeries(): array {
        $stmt = $this->conn->prepare("
            SELECT s.*,
                COUNT(gs.game_id)                  AS game_count,
                SUM(g.status = 'played')            AS played_count,
                ROUND(AVG(NULLIF(g.rating, 0)), 1)  AS avg_rating,
                SUM(g.playtime)                     AS total_hours
            FROM series s
            LEFT JOIN game_series gs ON gs.series_id = s.id
            LEFT JOIN games g        ON g.id = gs.game_id AND g.user_id = ?
            WHERE s.user_id = ?
            GROUP BY s.id
            ORDER BY s.name ASC
        ");
        $stmt->bind_param("ii", $this->userId, $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getSeriesById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM series WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $id, $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function getGamesInSeries(int $seriesId): array {
        $stmt = $this->conn->prepare("
            SELECT g.*, gs.series_order
            FROM games g
            JOIN game_series gs ON gs.game_id = g.id
            WHERE gs.series_id = ? AND g.user_id = ?
            ORDER BY gs.series_order ASC, g.release_year ASC, g.title ASC
        ");
        $stmt->bind_param("ii", $seriesId, $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getSeriesForGame(int $gameId): ?array {
        $stmt = $this->conn->prepare("
            SELECT s.*, gs.series_order
            FROM series s
            JOIN game_series gs ON gs.series_id = s.id
            WHERE gs.game_id = ? AND s.user_id = ?
        ");
        $stmt->bind_param("ii", $gameId, $this->userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }

    public function createSeries(string $name, string $description): int {
        $stmt = $this->conn->prepare("INSERT INTO series (user_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $this->userId, $name, $description);
        $stmt->execute();
        $id = (int)$this->conn->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateSeries(int $id, string $name, string $description): bool {
        $stmt = $this->conn->prepare("UPDATE series SET name=?, description=? WHERE id=? AND user_id=?");
        $stmt->bind_param("ssii", $name, $description, $id, $this->userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function deleteSeries(int $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM series WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $this->userId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function assignGame(int $gameId, int $seriesId, int $order): bool {
        $stmt = $this->conn->prepare("
            INSERT INTO game_series (game_id, series_id, series_order)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE series_id=VALUES(series_id), series_order=VALUES(series_order)
        ");
        $stmt->bind_param("iii", $gameId, $seriesId, $order);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    public function removeGame(int $gameId): bool {
        $stmt = $this->conn->prepare("DELETE FROM game_series WHERE game_id=?");
        $stmt->bind_param("i", $gameId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
?>
