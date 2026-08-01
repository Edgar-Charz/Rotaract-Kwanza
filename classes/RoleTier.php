<?php
class RoleTier
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(string $name, int $display_order = 0): int
    {
        $stmt = $this->db->prepare('INSERT INTO role_tiers (name, display_order) VALUES (?, ?)');
        $stmt->bind_param('si', $name, $display_order);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM role_tiers WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getNameById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT name FROM role_tiers WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return (string) $name;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM role_tiers ORDER BY display_order ASC, name ASC');
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(int $id, string $name, int $display_order): bool
    {
        $stmt = $this->db->prepare('UPDATE role_tiers SET name=?, display_order=? WHERE id=?');
        $stmt->bind_param('sii', $name, $display_order, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM role_tiers WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
