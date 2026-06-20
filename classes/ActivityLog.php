<?php
class ActivityLog
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function log(
        int $admin_id,
        string $admin_username,
        string $action,
        string $description,
        string $ip_address
    ): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_log (admin_id, admin_username, action, description, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('issss', $admin_id, $admin_username, $action, $description, $ip_address);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function count(string $admin_username = '', string $action = ''): int
    {
        $where = [];
        $types = '';
        $params = [];

        if ($admin_username !== '') {
            $where[] = 'admin_username = ?';
            $types .= 's';
            $params[] = $admin_username;
        }
        if ($action !== '') {
            $where[] = 'action = ?';
            $types .= 's';
            $params[] = $action;
        }

        $sql = 'SELECT COUNT(*) FROM activity_log';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getPage(
        int $limit,
        int $offset,
        string $admin_username = '',
        string $action = ''
    ): array {
        $where = [];
        $types = '';
        $params = [];

        if ($admin_username !== '') {
            $where[] = 'admin_username = ?';
            $types .= 's';
            $params[] = $admin_username;
        }
        if ($action !== '') {
            $where[] = 'action = ?';
            $types .= 's';
            $params[] = $action;
        }

        $sql = 'SELECT * FROM activity_log';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
        $types .= 'ii';
        $params[] = $limit;
        $params[] = $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getDistinctAdmins(): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT admin_username FROM activity_log ORDER BY admin_username'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_column($rows, 'admin_username');
    }

    public function deleteOlderThanDays(int $days): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'
        );
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $deleted = $stmt->affected_rows;
        $stmt->close();
        return $deleted;
    }
}
