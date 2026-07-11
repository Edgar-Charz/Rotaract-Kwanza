<?php
class ContactMessage
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $full_name,
        string $email,
        string $subject,
        string $message
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO contact_messages (full_name, email, subject, message)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->bind_param('ssss', $full_name, $email, $subject, $message);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_messages WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getRecent(int $limit): array
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ?');
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function count(string $status = ''): int
    {
        if ($status !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM contact_messages WHERE status = ?');
            $stmt->bind_param('s', $status);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM contact_messages');
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getAll(string $status = ''): array
    {
        if ($status !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM contact_messages WHERE status = ? ORDER BY created_at DESC'
            );
            $stmt->bind_param('s', $status);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM contact_messages ORDER BY created_at DESC'
            );
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE contact_messages SET status=? WHERE id=?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function markReplied(int $id, string $admin_notes): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE contact_messages SET admin_notes=?, status='replied' WHERE id=?"
        );
        $stmt->bind_param('si', $admin_notes, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM contact_messages WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
