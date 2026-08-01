<?php
// A simple flat photo gallery for "current team" group photos — no parent
// entity, since there is only ever one current team (unlike leadership terms
// or events, which each have their own photo set).
class TeamPhoto
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(string $image_path, int $display_order = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO team_photos (image_path, display_order) VALUES (?, ?)'
        );
        $stmt->bind_param('si', $image_path, $display_order);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM team_photos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM team_photos ORDER BY display_order ASC, created_at ASC');
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM team_photos WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
