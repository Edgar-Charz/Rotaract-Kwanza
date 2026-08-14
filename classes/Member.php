<?php
class Member
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $first_name,
        string $last_name,
        string $email,
        string $phone,
        string $occupation,
        string $why_join,
        string $status = 'pending',
        string $notes = '',
        string $photo_path = '',
        string $bio = '',
        string $linkedin_url = '',
        string $instagram_url = '',
        string $year_of_study = '',
        ?string $birthday = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO members (first_name, last_name, email, phone, occupation, year_of_study, birthday, bio, linkedin_url, instagram_url, why_join, status, notes, photo_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssssssssssss', $first_name, $last_name, $email, $phone, $occupation, $year_of_study, $birthday, $bio, $linkedin_url, $instagram_url, $why_join, $status, $notes, $photo_path);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function updatePhoto(int $id, string $path): bool
    {
        $stmt = $this->db->prepare('UPDATE members SET photo_path=? WHERE id=?');
        $stmt->bind_param('si', $path, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function getPhotoById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT COALESCE(photo_path,"") FROM members WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM members WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM members WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM members WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    // $limit is a safety cap, not a feature — prevents an unbounded fetch from
    // ever loading the entire table into memory if it grows very large; 5000
    // is far above any realistic row count for this app.
    public function getAll(string $status = '', int $limit = 5000): array
    {
        if ($status !== '') {
            $stmt = $this->db->prepare('SELECT * FROM members WHERE status = ? ORDER BY created_at DESC LIMIT ?');
            $stmt->bind_param('si', $status, $limit);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM members ORDER BY created_at DESC LIMIT ?');
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getRecent(int $limit): array
    {
        $stmt = $this->db->prepare('SELECT * FROM members ORDER BY created_at DESC LIMIT ?');
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function count(string $status = ''): int
    {
        if ($status !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM members WHERE status = ?');
            $stmt->bind_param('s', $status);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM members');
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getFullName(int $id): string
    {
        $stmt = $this->db->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM members WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($name);
        $stmt->fetch();
        $stmt->close();
        return (string) $name;
    }

    public function update(
        int $id,
        string $first_name,
        string $last_name,
        string $email,
        string $phone,
        string $occupation,
        string $why_join,
        string $status,
        string $notes,
        string $bio = '',
        string $linkedin_url = '',
        string $instagram_url = '',
        string $year_of_study = '',
        ?string $birthday = null
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE members SET first_name=?, last_name=?, email=?, phone=?, occupation=?, year_of_study=?, birthday=?, bio=?, linkedin_url=?, instagram_url=?,
             why_join=?, status=?, notes=? WHERE id=?'
        );
        $stmt->bind_param('sssssssssssssi', $first_name, $last_name, $email, $phone, $occupation, $year_of_study, $birthday, $bio, $linkedin_url, $instagram_url, $why_join, $status, $notes, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE members SET status=? WHERE id=?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function setStatusEmailSent(int $id, bool $sent): bool
    {
        $stmt = $this->db->prepare('UPDATE members SET status_email_sent=? WHERE id=?');
        $val = $sent ? 1 : 0;
        $stmt->bind_param('ii', $val, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function getTodaysBirthdays(bool $onlyUnsent = false): array
    {
        $sql = "SELECT id, first_name, last_name, email, birthday, last_birthday_email_sent, 'member' AS source_type FROM members
                WHERE status = 'approved' AND birthday IS NOT NULL
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
            "SELECT id, first_name, last_name, email, birthday, last_birthday_email_sent, 'member' AS source_type FROM members
             WHERE status = 'approved' AND birthday IS NOT NULL
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
        $stmt = $this->db->prepare('UPDATE members SET last_birthday_email_sent=? WHERE id=?');
        $stmt->bind_param('si', $date, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM members WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function getApprovedForDirectory(): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, first_name, last_name, occupation, bio, linkedin_url, instagram_url, created_at, COALESCE(photo_path,'') AS photo_path
             FROM members
             WHERE status = 'approved' AND show_in_directory = 1
             ORDER BY first_name, last_name"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** Paginated, DB-filtered variant of getApprovedForDirectory() for the public directory page. */
    public function getApprovedForDirectoryPage(int $limit, int $offset, string $search = ''): array
    {
        $sql = "SELECT id, first_name, last_name, occupation, bio, linkedin_url, instagram_url, created_at, COALESCE(photo_path,'') AS photo_path
                FROM members
                WHERE status = 'approved' AND show_in_directory = 1";
        $types = '';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR occupation LIKE ?)';
            $like = "%$search%";
            $types .= 'sss';
            $params = [$like, $like, $like];
        }
        $sql .= ' ORDER BY first_name, last_name LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function countApprovedForDirectory(string $search = ''): int
    {
        $sql = "SELECT COUNT(*) FROM members WHERE status = 'approved' AND show_in_directory = 1";
        $types = '';
        $params = [];
        if ($search !== '') {
            $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR occupation LIKE ?)';
            $like = "%$search%";
            $types = 'sss';
            $params = [$like, $like, $like];
        }
        $stmt = $this->db->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getWithDues(int $year): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.first_name, m.last_name, m.email, m.phone,
                    d.id AS dues_id, d.amount_due, d.amount_paid, d.payment_date, d.notes, d.status AS dues_status
             FROM members m
             LEFT JOIN member_dues d ON d.member_id = m.id AND d.year = ?
             WHERE m.status IN ('approved', 'pending')"
        );
        $stmt->bind_param('i', $year);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
