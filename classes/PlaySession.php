<?php
class PlaySession {
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    public function getSessions(int $gameId): array {
        $stmt = $this->conn->prepare(
            'SELECT * FROM play_sessions WHERE game_id = ? ORDER BY played_date DESC'
        );
        $stmt->bind_param('i', $gameId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function addSession(int $gameId, string $date, string $note): bool {
        $stmt = $this->conn->prepare(
            'INSERT INTO play_sessions (game_id, played_date, note) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('iss', $gameId, $date, $note);
        return $stmt->execute();
    }

    public function deleteSession(int $id): bool {
        $stmt = $this->conn->prepare('DELETE FROM play_sessions WHERE id = ?');
        $stmt->bind_param('i', $id);
        return $stmt->execute();
    }
}
