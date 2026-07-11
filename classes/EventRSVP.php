<?php
class EventRSVP
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        int $event_id,
        string $name,
        string $email,
        string $phone,
        int $guests,
        string $notes = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO event_rsvps (event_id, name, email, phone, guests, notes)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssis', $event_id, $name, $email, $phone, $guests, $notes);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function alreadyRegistered(int $event_id, string $email): bool
    {
        $stmt = $this->db->prepare('SELECT id FROM event_rsvps WHERE event_id = ? AND email = ?');
        $stmt->bind_param('is', $event_id, $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        return $exists;
    }

    public function getByEvent(int $event_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, e.title AS event_title
             FROM event_rsvps r
             JOIN events e ON e.id = r.event_id
             WHERE r.event_id = ?
             ORDER BY r.created_at DESC'
        );
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, e.title AS event_title
             FROM event_rsvps r
             JOIN events e ON e.id = r.event_id
             ORDER BY r.created_at DESC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function count(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM event_rsvps');
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getSummaryByEvent(): array
    {
        $stmt = $this->db->prepare(
            'SELECT event_id, COUNT(*) AS n, SUM(guests) AS g
             FROM event_rsvps
             GROUP BY event_id'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function markAttended(int $id, int $attended): bool
    {
        $stmt = $this->db->prepare('UPDATE event_rsvps SET attended=? WHERE id=?');
        $stmt->bind_param('ii', $attended, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    public function getAttendanceSummary(int $event_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS total, SUM(attended) AS attended FROM event_rsvps WHERE event_id = ?'
        );
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: ['total' => 0, 'attended' => 0];
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM event_rsvps WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
