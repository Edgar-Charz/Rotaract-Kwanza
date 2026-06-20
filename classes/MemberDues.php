<?php
class MemberDues
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    /**
     * Insert or update dues record for a member/year combination.
     */
    public function save(
        int $member_id,
        int $year,
        float $amount_due,
        float $amount_paid,
        string $payment_date,
        string $notes,
        string $status
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO member_dues (member_id, year, amount_due, amount_paid, payment_date, notes, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 amount_due    = VALUES(amount_due),
                 amount_paid   = VALUES(amount_paid),
                 payment_date  = VALUES(payment_date),
                 notes         = VALUES(notes),
                 status        = VALUES(status)'
        );
        $stmt->bind_param('iiddsss', $member_id, $year, $amount_due, $amount_paid, $payment_date, $notes, $status);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM member_dues WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getByMember(int $member_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM member_dues WHERE member_id = ? ORDER BY year DESC'
        );
        $stmt->bind_param('i', $member_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM member_dues WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
