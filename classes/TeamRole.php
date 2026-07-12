<?php
class TeamRole
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(string $name, string $tier_label, int $display_order = 0, int $is_active = 1): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO team_roles (name, tier_label, display_order, is_active)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssii', $name, $tier_label, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM team_roles WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getNameById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT name FROM team_roles WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return (string) $name;
    }

    public function getActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM team_roles WHERE is_active = 1 ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM team_roles ORDER BY display_order ASC, name ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(int $id, string $name, string $tier_label, int $display_order, int $is_active): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE team_roles SET name=?, tier_label=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('ssiii', $name, $tier_label, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM team_roles WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
