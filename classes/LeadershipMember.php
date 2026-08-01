<?php
require_once __DIR__ . '/TeamRole.php';

class LeadershipMember
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    private function getRoleName(int $role_id): string
    {
        if ($role_id <= 0) {
            return '';
        }
        return (new TeamRole($this->db))->getNameById($role_id);
    }

    public function create(
        int $term_id,
        string $full_name,
        string $role,
        string $description,
        string $photo_path,
        int $display_order = 0,
        int $is_active = 1,
        string $linkedin_url = '',
        string $instagram_url = '',
        int $role_id = 0
    ): int {
        $role_name     = $this->getRoleName($role_id) ?: $role;
        $role_id_param = $role_id > 0 ? $role_id : null;
        $stmt = $this->db->prepare(
            'INSERT INTO leadership_members (term_id, full_name, role, role_id, description, photo_path, linkedin_url, instagram_url, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ississssii', $term_id, $full_name, $role_name, $role_id_param, $description, $photo_path, $linkedin_url, $instagram_url, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    // lm.* is selected first, then the COALESCE'd role is selected again under
    // the same alias — mysqli's fetch_assoc() keeps the *last* column for a
    // repeated name, so this intentionally overrides lm.role with the live
    // team_roles value when a role_id is set, falling back to the legacy
    // free-text lm.role for unlinked officers.
    private function baseSelect(): string
    {
        return "SELECT lm.*, COALESCE(tr.name, lm.role) AS role
                FROM leadership_members lm
                LEFT JOIN team_roles tr ON lm.role_id = tr.id";
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare($this->baseSelect() . ' WHERE lm.id = ? LIMIT 1');
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
            $this->baseSelect() . ' WHERE lm.term_id = ? ORDER BY lm.display_order ASC, lm.created_at ASC'
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
            $this->baseSelect() . ' WHERE lm.term_id = ? AND lm.is_active = 1 ORDER BY lm.display_order ASC, lm.created_at ASC'
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
        int $is_active,
        string $linkedin_url = '',
        string $instagram_url = '',
        int $role_id = 0
    ): bool {
        $role_name     = $this->getRoleName($role_id) ?: $role;
        $role_id_param = $role_id > 0 ? $role_id : null;
        $stmt = $this->db->prepare(
            'UPDATE leadership_members SET full_name=?, role=?, role_id=?, description=?, photo_path=?, linkedin_url=?, instagram_url=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('ssissssiii', $full_name, $role_name, $role_id_param, $description, $photo_path, $linkedin_url, $instagram_url, $display_order, $is_active, $id);
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
