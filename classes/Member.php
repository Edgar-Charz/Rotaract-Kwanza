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
        string $notes = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO members (first_name, last_name, email, phone, occupation, why_join, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssssss', $first_name, $last_name, $email, $phone, $occupation, $why_join, $status, $notes);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
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

    public function getAll(string $status = ''): array
    {
        if ($status !== '') {
            $stmt = $this->db->prepare('SELECT * FROM members WHERE status = ? ORDER BY created_at DESC');
            $stmt->bind_param('s', $status);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM members ORDER BY created_at DESC');
        }
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
        string $notes
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE members SET first_name=?, last_name=?, email=?, phone=?, occupation=?,
             why_join=?, status=?, notes=? WHERE id=?'
        );
        $stmt->bind_param('ssssssssi', $first_name, $last_name, $email, $phone, $occupation, $why_join, $status, $notes, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
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

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM members WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
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
