<?php
class PaymentAccount
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(string $type, string $label, string $details, int $display_order = 0, int $is_active = 1): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO payment_accounts (type, label, details, display_order, is_active)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssii', $type, $label, $details, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM payment_accounts WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getActiveByType(string $type): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment_accounts WHERE type = ? AND is_active = 1 ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->bind_param('s', $type);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payment_accounts ORDER BY type ASC, display_order ASC, created_at ASC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(int $id, string $type, string $label, string $details, int $display_order, int $is_active): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE payment_accounts SET type=?, label=?, details=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('sssiii', $type, $label, $details, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM payment_accounts WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
