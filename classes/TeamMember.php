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
        string $instagram_url = '',
        ?string $birthday = null
    ): int {
        $role_name = $this->getRoleName($role_id);
        $role_id_param = $role_id > 0 ? $role_id : null;
        $bday = !empty($birthday) ? $birthday : null;
        if ($this->hasRoleId()) {
            $stmt = $this->db->prepare(
                'INSERT INTO team_members (full_name, role, role_id, term, description, image_path, email, linkedin_url, instagram_url, display_order, is_active, birthday)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssissssssiis', $full_name, $role_name, $role_id_param, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $bday);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO team_members (full_name, role, term, description, image_path, email, linkedin_url, instagram_url, display_order, is_active, birthday)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('ssissssssis', $full_name, $role_name, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $bday);
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

    // $limit is a safety cap, not a feature — prevents an unbounded fetch from
    // ever loading the entire table into memory if it grows very large; 5000
    // is far above any realistic row count for this app.
    public function getAll(int $limit = 5000): array
    {
        if ($this->canJoinRoles()) {
            $stmt = $this->db->prepare(
                $this->baseSelect() . '
                 ORDER BY COALESCE(tr.display_order, 9999) ASC, tm.display_order ASC, tm.created_at ASC
                 LIMIT ?'
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
                 ORDER BY tier ASC, display_order ASC, created_at ASC
                 LIMIT ?'
            );
        }
        $stmt->bind_param('i', $limit);
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
        string $instagram_url = '',
        ?string $birthday = null
    ): bool {
        $role_name = $this->getRoleName($role_id);
        $role_id_param = $role_id > 0 ? $role_id : null;
        $bday = !empty($birthday) ? $birthday : null;
        if ($this->hasRoleId()) {
            $stmt = $this->db->prepare(
                'UPDATE team_members SET full_name=?, role=?, role_id=?, term=?, description=?, image_path=?,
                 email=?, linkedin_url=?, instagram_url=?, display_order=?, is_active=?, birthday=? WHERE id=?'
            );
            $stmt->bind_param('ssissssssiisi', $full_name, $role_name, $role_id_param, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $bday, $id);
        } else {
            $stmt = $this->db->prepare(
                'UPDATE team_members SET full_name=?, role=?, term=?, description=?, image_path=?,
                 email=?, linkedin_url=?, instagram_url=?, display_order=?, is_active=?, birthday=? WHERE id=?'
            );
            $stmt->bind_param('ssissssssisi', $full_name, $role_name, $term, $description, $image_path, $email, $linkedin_url, $instagram_url, $display_order, $is_active, $bday, $id);
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

    public function getTodaysBirthdays(bool $onlyUnsent = false): array
    {
        $sql = "SELECT id, full_name AS first_name, '' AS last_name, email, birthday, last_birthday_email_sent, 'team_member' AS source_type FROM team_members
                WHERE is_active = 1 AND birthday IS NOT NULL
                  AND MONTH(birthday) = MONTH(CURDATE()) AND DAY(birthday) = DAY(CURDATE())";
        if ($onlyUnsent) {
            $sql .= " AND (last_birthday_email_sent IS NULL OR last_birthday_email_sent <> CURDATE())";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getUpcomingBirthdays(int $daysAhead = 2): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, full_name AS first_name, '' AS last_name, email, birthday, last_birthday_email_sent, 'team_member' AS source_type FROM team_members
             WHERE is_active = 1 AND birthday IS NOT NULL
               AND (
                 (MONTH(birthday) = MONTH(DATE_ADD(CURDATE(), INTERVAL 1 DAY)) AND DAY(birthday) = DAY(DATE_ADD(CURDATE(), INTERVAL 1 DAY)))
                 OR
                 (MONTH(birthday) = MONTH(DATE_ADD(CURDATE(), INTERVAL 2 DAY)) AND DAY(birthday) = DAY(DATE_ADD(CURDATE(), INTERVAL 2 DAY)))
               )
             ORDER BY DAY(birthday) ASC"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function markBirthdayEmailSent(int $id, string $date): bool
    {
        $stmt = $this->db->prepare('UPDATE team_members SET last_birthday_email_sent=? WHERE id=?');
        $stmt->bind_param('si', $date, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
