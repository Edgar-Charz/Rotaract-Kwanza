<?php
class LeadershipMember
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        int $term_id,
        string $full_name,
        string $role,
        string $description,
        string $photo_path,
        int $display_order = 0,
        int $is_active = 1
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO leadership_members (term_id, full_name, role, description, photo_path, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssii', $term_id, $full_name, $role, $description, $photo_path, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM leadership_members WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getFullNameById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT full_name FROM leadership_members WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return (string) $name;
    }

    public function getPhotoPathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT photo_path FROM leadership_members WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function getByTermId(int $term_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM leadership_members WHERE term_id = ? ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->bind_param('i', $term_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getActiveByTermId(int $term_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM leadership_members WHERE term_id = ? AND is_active = 1 ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->bind_param('i', $term_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(
        int $id,
        string $full_name,
        string $role,
        string $description,
        string $photo_path,
        int $display_order,
        int $is_active
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE leadership_members SET full_name=?, role=?, description=?, photo_path=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('ssssiii', $full_name, $role, $description, $photo_path, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM leadership_members WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
