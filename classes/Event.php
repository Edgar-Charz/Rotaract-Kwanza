<?php
class Event
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $title,
        string $event_date,
        string $event_time,
        string $location,
        string $description,
        string $category,
        string $status = 'upcoming',
        int $is_featured = 0,
        string $image_path = '',
        string $instagram_url = '',
        string $tiktok_url = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO events (title, event_date, event_time, location, description, category, status, is_featured, image_path, instagram_url, tiktok_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssssisss', $title, $event_date, $event_time, $location, $description, $category, $status, $is_featured, $image_path, $instagram_url, $tiktok_url);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM events WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function findUpcomingById(int $id): array|false
    {
        $stmt = $this->db->prepare("SELECT * FROM events WHERE id = ? AND status = 'upcoming' LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getTitleById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT title FROM events WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($title);
        $stmt->fetch();
        $stmt->close();
        return (string) $title;
    }

    public function countByStatus(string $status): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM events WHERE status = ?');
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getAll(string $status = ''): array
    {
        $where = $status !== '' ? 'WHERE e.status = ?' : '';
        $stmt  = $this->db->prepare(
            "SELECT e.*, COUNT(r.id) AS rsvp_count
             FROM events e
             LEFT JOIN event_rsvps r ON r.event_id = e.id
             $where
             GROUP BY e.id
             ORDER BY e.event_date DESC"
        );
        if ($status !== '') {
            $stmt->bind_param('s', $status);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAllTitles(): array
    {
        $stmt = $this->db->prepare('SELECT id, title, event_date FROM events ORDER BY event_date DESC');
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getFeaturedUpcoming(int $limit = 3): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM events WHERE status = 'upcoming' AND is_featured = 1
             ORDER BY event_date ASC LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getUpcoming(int $limit = 0): array
    {
        $sql = "SELECT * FROM events WHERE status = 'upcoming' ORDER BY event_date ASC";
        if ($limit > 0) {
            $sql .= ' LIMIT ?';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $limit);
        } else {
            $stmt = $this->db->prepare($sql);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getPast(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM events WHERE status = 'past' ORDER BY event_date DESC LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getCancelled(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM events WHERE status = 'cancelled' ORDER BY event_date DESC LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(
        int $id,
        string $title,
        string $event_date,
        string $event_time,
        string $location,
        string $description,
        string $category,
        string $status,
        int $is_featured,
        string $image_path = '',
        string $instagram_url = '',
        string $tiktok_url = ''
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE events SET title=?, event_date=?, event_time=?, location=?, description=?,
             category=?, status=?, is_featured=?, image_path=?, instagram_url=?, tiktok_url=? WHERE id=?'
        );
        $stmt->bind_param('sssssssisssi', $title, $event_date, $event_time, $location, $description, $category, $status, $is_featured, $image_path, $instagram_url, $tiktok_url, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    public function getImagePathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM events WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM events WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
