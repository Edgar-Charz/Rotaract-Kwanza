<?php
class TeamMember
{
    private mysqli $db;
    private ?bool $hasRoleId = null;
    private ?bool $hasTeamRoles = null;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    private function hasRoleId(): bool
    {
        if ($this->hasRoleId !== null) {
            return $this->hasRoleId;
        }

        $stmt = $this->db->prepare("SHOW COLUMNS FROM team_members LIKE 'role_id'");
        if (!$stmt) {
            $this->hasRoleId = false;
            return false;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $this->hasRoleId = $res && $res->num_rows > 0;
        $stmt->close();
        return $this->hasRoleId;
    }

    private function hasTeamRoles(): bool
    {
        if ($this->hasTeamRoles !== null) {
            return $this->hasTeamRoles;
        }

        $stmt = $this->db->prepare("SHOW TABLES LIKE 'team_roles'");
        if (!$stmt) {
            $this->hasTeamRoles = false;
            return false;
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $this->hasTeamRoles = $res && $res->num_rows > 0;
        $stmt->close();
        return $this->hasTeamRoles;
    }

    private function getRoleName(int $role_id): string
    {
        if ($role_id <= 0 || !$this->hasTeamRoles()) {
            return 'Team Member';
        }
        return (new TeamRole($this->db))->getNameById($role_id) ?: 'Team Member';
    }

    public function create(
        string $full_name,
        int $role_id,
        string $description,
        string $image_path,
        string $email,
        int $display_order = 0,
        int $is_active = 1,
        string $term = '',
        string $linkedin_url = '',
        string $instagram_url = ''
    ): int {
        $role_name = $this->getRoleName($role_id);
        if ($this->hasRoleId()) {
            $stmt = $this->db->prepare(
                'INSERT INTO team_members (full_name, role, role_id, term, description, image_path, email, linkedin_url, instagram_url, display_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssissssssii', $full_name, $role_name, $role_id, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO team_members (full_name, role, term, description, image_path, email, linkedin_url, instagram_url, display_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssissssssi', $full_name, $role_name, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active);
        }

        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    private function canJoinRoles(): bool
    {
        return $this->hasRoleId() && $this->hasTeamRoles();
    }

    public function findById(int $id): array|false
    {
        if ($this->canJoinRoles()) {
            $stmt = $this->db->prepare($this->baseSelect() . ' WHERE tm.id = ? LIMIT 1');
        } else {
            $stmt = $this->db->prepare(
                'SELECT *, COALESCE(role, "Team Member") AS role,
                        CASE tier
                          WHEN 1 THEN "Leadership (President)"
                          WHEN 2 THEN "Executive Committee"
                          WHEN 3 THEN "Directors & Coordinators"
                          WHEN 4 THEN "Team Members"
                          ELSE "Team Members"
                        END AS tier_label
                 FROM team_members WHERE id = ? LIMIT 1'
            );
        }

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

    // tm.* is selected first, then the COALESCE'd role/tier_label columns are selected again
    // under the same aliases — mysqli's fetch_assoc() keeps the *last* column for a repeated
    // name, so these intentionally override tm.role with the live team_roles value when a
    // role_id is set, falling back to the legacy free-text tm.role for unlinked members.
    private function baseSelect(): string
    {
        return "SELECT tm.*,
                       COALESCE(tr.name, tm.role, 'Team Member') AS role,
                       COALESCE(tr.tier_label, 'Team Members') AS tier_label
                FROM team_members tm
                LEFT JOIN team_roles tr ON tm.role_id = tr.id";
    }

    public function getActive(): array
    {
        if ($this->canJoinRoles()) {
            $stmt = $this->db->prepare(
                $this->baseSelect() . ' WHERE tm.is_active = 1
                 ORDER BY COALESCE(tr.display_order, 9999) ASC, tm.display_order ASC, tm.created_at ASC'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT *, COALESCE(role, "Team Member") AS role,
                        CASE tier
                          WHEN 1 THEN "Leadership (President)"
                          WHEN 2 THEN "Executive Committee"
                          WHEN 3 THEN "Directors & Coordinators"
                          WHEN 4 THEN "Team Members"
                          ELSE "Team Members"
                        END AS tier_label
                 FROM team_members WHERE is_active = 1
                 ORDER BY tier ASC, display_order ASC, created_at ASC'
            );
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        if ($this->canJoinRoles()) {
            $stmt = $this->db->prepare(
                $this->baseSelect() . '
                 ORDER BY COALESCE(tr.display_order, 9999) ASC, tm.display_order ASC, tm.created_at ASC'
            );
        } else {
            $stmt = $this->db->prepare(
                'SELECT *, COALESCE(role, "Team Member") AS role,
                        CASE tier
                          WHEN 1 THEN "Leadership (President)"
                          WHEN 2 THEN "Executive Committee"
                          WHEN 3 THEN "Directors & Coordinators"
                          WHEN 4 THEN "Team Members"
                          ELSE "Team Members"
                        END AS tier_label
                 FROM team_members
                 ORDER BY tier ASC, display_order ASC, created_at ASC'
            );
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(
        int $id,
        string $full_name,
        int $role_id,
        string $description,
        string $image_path,
        string $email,
        int $display_order,
        int $is_active,
        string $term = '',
        string $linkedin_url = '',
        string $instagram_url = ''
    ): bool {
        $role_name = $this->getRoleName($role_id);
        if ($this->hasRoleId()) {
            $stmt = $this->db->prepare(
                'UPDATE team_members SET full_name=?, role=?, role_id=?, term=?, description=?, image_path=?,
                 email=?, linkedin_url=?, instagram_url=?, display_order=?, is_active=? WHERE id=?'
            );
            $stmt->bind_param('ssissssssiii', $full_name, $role_name, $role_id, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $id);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE team_members SET full_name=?, role=?, term=?, description=?, image_path=?,
                 email=?, linkedin_url=?, instagram_url=?, display_order=?, is_active=? WHERE id=?'
            );
            $stmt->bind_param('ssissssssi', $full_name, $role_name, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $id);
        }
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
