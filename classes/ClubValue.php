<?php
class ClubValue
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(string $icon_key, string $title, string $description, int $display_order = 0, int $is_active = 1): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO club_values (icon_key, title, description, display_order, is_active)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssii', $icon_key, $title, $description, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM club_values WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getTitleById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT title FROM club_values WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($title);
        $stmt->fetch();
        $stmt->close();
        return (string) $title;
    }

    public function getActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM club_values WHERE is_active = 1 ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM club_values ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(int $id, string $icon_key, string $title, string $description, int $display_order, int $is_active): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE club_values SET icon_key=?, title=?, description=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('sssiii', $icon_key, $title, $description, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM club_values WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
