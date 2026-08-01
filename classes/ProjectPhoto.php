<?php
class ProjectPhoto
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(int $project_id, string $image_path, int $display_order = 0, string $caption = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO project_photos (project_id, image_path, display_order, caption) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('isis', $project_id, $image_path, $display_order, $caption);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateCaption(int $id, string $caption): bool
    {
        $stmt = $this->db->prepare('UPDATE project_photos SET caption=? WHERE id=?');
        $stmt->bind_param('si', $caption, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function updateOrder(int $id, int $display_order): bool
    {
        $stmt = $this->db->prepare('UPDATE project_photos SET display_order=? WHERE id=?');
        $stmt->bind_param('ii', $display_order, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM project_photos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getByProject(int $project_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM project_photos WHERE project_id = ? ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function countByProject(int $project_id): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM project_photos WHERE project_id = ?');
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM project_photos WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
