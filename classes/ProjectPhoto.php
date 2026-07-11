<?php
class ProjectPhoto
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(int $project_id, string $image_path, int $display_order = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO project_photos (project_id, image_path, display_order) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('isi', $project_id, $image_path, $display_order);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
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
