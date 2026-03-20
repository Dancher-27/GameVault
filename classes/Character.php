<?php

class Character {
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
    }

    // Alle karakters van een game ophalen (top picks eerst)
    public function getByGame(int $game_id): array {
        $stmt = $this->conn->prepare(
            "SELECT * FROM characters WHERE game_id = ? ORDER BY is_top_pick DESC, name ASC"
        );
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $chars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $chars;
    }

    // Karakter toevoegen
    public function addCharacter(
        int     $game_id,
        string  $name,
        string  $description,
        ?string $image,
        int     $is_top_pick
    ): bool {
        $stmt = $this->conn->prepare(
            "INSERT INTO characters (game_id, name, description, image, is_top_pick) VALUES (?, ?, ?, ?, ?)"
        );
        // types: i s s s i
        $stmt->bind_param("isssi", $game_id, $name, $description, $image, $is_top_pick);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Karakter verwijderen
    public function deleteCharacter(int $id): bool {
        $stmt = $this->conn->prepare("DELETE FROM characters WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    // Toggle top pick
    public function toggleTopPick(int $id): bool {
        $stmt = $this->conn->prepare("UPDATE characters SET is_top_pick = NOT is_top_pick WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}
?>
