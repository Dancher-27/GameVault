<?php
class Tag {
    private mysqli $conn;
    private int $userId;

    public function __construct(mysqli $conn, int $userId) {
        $this->conn   = $conn;
        $this->userId = $userId;
    }

    public function getAllTags(): array {
        $stmt = $this->conn->prepare("SELECT * FROM tags WHERE user_id = ? ORDER BY name ASC");
        $stmt->bind_param("i", $this->userId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getTagsByGame(int $gameId): array {
        $stmt = $this->conn->prepare(
            "SELECT t.* FROM tags t
             JOIN game_tags gt ON gt.tag_id = t.id
             WHERE gt.game_id = ? AND t.user_id = ?"
        );
        $stmt->bind_param("ii", $gameId, $this->userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $result;
    }

    public function createTag(string $name, string $color): int {
        $stmt = $this->conn->prepare(
            "INSERT INTO tags (user_id, name, color) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE color=VALUES(color)"
        );
        $stmt->bind_param("iss", $this->userId, $name, $color);
        $stmt->execute();
        $id = (int)$this->conn->insert_id;
        if ($id === 0) {
            $s2 = $this->conn->prepare("SELECT id FROM tags WHERE user_id=? AND name=?");
            $s2->bind_param("is", $this->userId, $name);
            $s2->execute();
            $id = (int)$s2->get_result()->fetch_assoc()['id'];
            $s2->close();
        }
        $stmt->close();
        return $id;
    }

    public function setTagsForGame(int $gameId, array $tagIds): void {
        $del = $this->conn->prepare("DELETE FROM game_tags WHERE game_id=?");
        $del->bind_param("i", $gameId);
        $del->execute();
        $del->close();
        if (empty($tagIds)) return;
        $ins = $this->conn->prepare("INSERT IGNORE INTO game_tags (game_id, tag_id) VALUES (?, ?)");
        foreach ($tagIds as $tid) {
            $tid = (int)$tid;
            $ins->bind_param("ii", $gameId, $tid);
            $ins->execute();
        }
        $ins->close();
    }

    public function deleteTag(int $id): void {
        $stmt = $this->conn->prepare("DELETE FROM tags WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $id, $this->userId);
        $stmt->execute();
        $stmt->close();
    }
}
