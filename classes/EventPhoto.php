<?php
class EventPhoto
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(int $event_id, string $image_path, int $display_order = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO event_photos (event_id, image_path, display_order) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('isi', $event_id, $image_path, $display_order);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM event_photos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getByEvent(int $event_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM event_photos WHERE event_id = ? ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function countByEvent(int $event_id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM event_photos WHERE event_id = ?');
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM event_photos WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
