<?php
class ProjectSignup
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        int $project_id,
        string $name,
        string $email,
        string $phone,
        string $notes = '',
        ?int $member_id = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO project_signups (project_id, name, email, phone, notes, member_id)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issssi', $project_id, $name, $email, $phone, $notes, $member_id);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM project_signups WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function update(int $id, string $name, string $email, string $phone, string $notes = ''): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE project_signups SET name=?, email=?, phone=?, notes=? WHERE id=?'
        );
        $stmt->bind_param('ssssi', $name, $email, $phone, $notes, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    public function alreadySignedUp(int $project_id, string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM project_signups WHERE project_id = ? AND email = ?');
        $stmt->bind_param('is', $project_id, $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getByProject(int $project_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, p.title AS project_title
             FROM project_signups s
             JOIN projects p ON p.id = s.project_id
             WHERE s.project_id = ?
             ORDER BY s.created_at DESC'
        );
        $stmt->bind_param('i', $project_id);
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
        $stmt = $this->db->prepare(
            'SELECT s.*, p.title AS project_title
             FROM project_signups s
             JOIN projects p ON p.id = s.project_id
             ORDER BY s.created_at DESC
             LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** Batched form of markContacted() for admin bulk actions — one query instead of one per row. */
    public function markContactedBatch(array $ids, int $contacted): int
    {
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE project_signups SET contacted=? WHERE id IN ($placeholders)");
        $stmt->bind_param('i' . str_repeat('i', count($ids)), $contacted, ...$ids);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return (int) $n;
    }

    public function count(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM project_signups');
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getSummaryByProject(): array
    {
        $stmt = $this->db->prepare(
            'SELECT project_id, COUNT(*) AS n
             FROM project_signups
             GROUP BY project_id'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function markContacted(int $id, int $contacted): bool
    {
        $stmt = $this->db->prepare('UPDATE project_signups SET contacted=? WHERE id=?');
        $stmt->bind_param('ii', $contacted, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM project_signups WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
