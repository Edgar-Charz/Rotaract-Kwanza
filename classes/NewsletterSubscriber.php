<?php
class NewsletterSubscriber
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    /** Returns the new subscriber's id, or the existing one's id if already subscribed. */
    public function create(string $email): int
    {
        $existing = $this->findByEmail($email);
        if ($existing) {
            return (int) $existing['id'];
        }

        $token = bin2hex(random_bytes(16));
        $stmt  = $this->db->prepare('INSERT INTO newsletter_subscribers (email, unsubscribe_token) VALUES (?, ?)');
        $stmt->bind_param('ss', $email, $token);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM newsletter_subscribers WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function findByEmail(string $email): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM newsletter_subscribers WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function findByToken(string $token): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM newsletter_subscribers WHERE unsubscribe_token = ? LIMIT 1');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM newsletter_subscribers ORDER BY created_at DESC');
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function count(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM newsletter_subscribers');
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM newsletter_subscribers WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
