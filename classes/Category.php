<?php
class Category
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(string $type, string $name, int $display_order = 0, int $is_active = 1): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO categories (type, name, display_order, is_active) VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssii', $type, $name, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM categories WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getNameById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT name FROM categories WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return (string) $name;
    }

    public function getActive(string $type): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM categories WHERE type = ? AND is_active = 1 ORDER BY display_order ASC, name ASC'
        );
        $stmt->bind_param('s', $type);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(string $type): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM categories WHERE type = ? ORDER BY display_order ASC, name ASC'
        );
        $stmt->bind_param('s', $type);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(int $id, string $name, int $display_order, int $is_active): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE categories SET name=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('siii', $name, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
