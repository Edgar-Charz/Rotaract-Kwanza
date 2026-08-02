<?php
class Pledge
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $name,
        string $email,
        string $phone,
        ?int $payment_account_id,
        string $method,
        string $method_label,
        ?float $amount,
        string $note = '',
        ?int $project_id = null,
        string $project_title = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO pledges (name, email, phone, payment_account_id, method, method_label, amount, note, project_id, project_title)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssissdsis', $name, $email, $phone, $payment_account_id, $method, $method_label, $amount, $note, $project_id, $project_title);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM pledges WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    // $limit is a safety cap, not a feature — prevents an unbounded fetch from
    // ever loading the entire table into memory if it grows very large; 5000
    // is far above any realistic row count for this app.
    public function getAll(string $status = '', int $limit = 5000): array
    {
        if ($status !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM pledges WHERE status = ? ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->bind_param('si', $status, $limit);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM pledges ORDER BY created_at DESC LIMIT ?'
            );
            $stmt->bind_param('i', $limit);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function count(string $status = ''): int
    {
        if ($status !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM pledges WHERE status = ?');
            $stmt->bind_param('s', $status);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM pledges');
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function sumConfirmedAmount(): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM pledges WHERE status = 'confirmed'");
        $stmt->execute();
        $stmt->bind_result($sum);
        $stmt->fetch();
        $stmt->close();
        return (float) $sum;
    }

    public function sumConfirmedAmountForProject(int $project_id): float
    {
        $stmt = $this->db->prepare("SELECT COALESCE(SUM(amount), 0) FROM pledges WHERE status = 'confirmed' AND project_id = ?");
        $stmt->bind_param('i', $project_id);
        $stmt->execute();
        $stmt->bind_result($sum);
        $stmt->fetch();
        $stmt->close();
        return (float) $sum;
    }

    /** Batched confirmed-amount totals per project, for the projects listing grid — avoids one query per card. */
    public function getConfirmedAmountsByProject(): array
    {
        $stmt = $this->db->prepare(
            "SELECT project_id, COALESCE(SUM(amount), 0) AS total
             FROM pledges WHERE status = 'confirmed' AND project_id IS NOT NULL
             GROUP BY project_id"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row['project_id']] = (float) $row['total'];
        }
        return $totals;
    }

    public function updateStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare('UPDATE pledges SET status=? WHERE id=?');
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function setEmailSent(int $id, bool $sent): bool
    {
        $stmt = $this->db->prepare('UPDATE pledges SET email_sent=? WHERE id=?');
        $val = $sent ? 1 : 0;
        $stmt->bind_param('ii', $val, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM pledges WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
