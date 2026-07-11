<?php
class TeamMember
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $full_name,
        string $role,
        string $description,
        string $image_path,
        string $email,
        int $display_order = 0,
        int $is_active = 1,
        string $term = '',
        string $linkedin_url = '',
        int $tier = 3,
        string $instagram_url = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO team_members (full_name, role, tier, term, description, image_path, email, linkedin_url, instagram_url, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssissssssii', $full_name, $role, $tier, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM team_members WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getFullNameById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT full_name FROM team_members WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return (string) $name;
    }

    public function getImagePathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM team_members WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function getActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM team_members WHERE is_active = 1 ORDER BY tier ASC, display_order ASC, created_at ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM team_members ORDER BY tier ASC, display_order ASC, created_at ASC'
        );
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
        string $image_path,
        string $email,
        int $display_order,
        int $is_active,
        string $term = '',
        string $linkedin_url = '',
        int $tier = 3,
        string $instagram_url = ''
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE team_members SET full_name=?, role=?, tier=?, term=?, description=?, image_path=?,
             email=?, linkedin_url=?, instagram_url=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('ssissssssiii', $full_name, $role, $tier, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM team_members WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
