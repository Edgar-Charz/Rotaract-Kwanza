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
        string $notes = '',
        ?int $member_id = null,
        string $guest_names = ''
    ): int {
        $token = bin2hex(random_bytes(16));
        $stmt = $this->db->prepare(
            'INSERT INTO event_rsvps (event_id, name, email, phone, guests, notes, member_id, guest_names, cancel_token)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssisiss', $event_id, $name, $email, $phone, $guests, $notes, $member_id, $guest_names, $token);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM event_rsvps WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    /** Looks up an RSVP by its public cancel_token, joined with the event it's for. */
    public function findByToken(string $token): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, e.title AS event_title, e.event_date, e.event_time
             FROM event_rsvps r JOIN events e ON e.id = r.event_id
             WHERE r.cancel_token = ? LIMIT 1'
        );
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function update(int $id, string $name, string $email, string $phone, int $guests, string $notes = '', string $guest_names = ''): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE event_rsvps SET name=?, email=?, phone=?, guests=?, notes=?, guest_names=? WHERE id=?'
        );
        $stmt->bind_param('sssissi', $name, $email, $phone, $guests, $notes, $guest_names, $id);
        $stmt->execute();
        $stmt->close();
        return true;
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

    public function getGuestCount(int $event_id): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(guests),0) FROM event_rsvps WHERE event_id = ?');
        $stmt->bind_param('i', $event_id);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
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
