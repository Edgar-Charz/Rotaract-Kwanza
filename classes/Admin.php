<?php
class Admin
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    /**
     * Attempt login. Returns admin row on success, false on failure.
     */
    public function login(string $username, string $password): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, full_name, password_hash
             FROM admins WHERE username = ? LIMIT 1'
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row && password_verify($password, $row['password_hash'])) {
            return $row;
        }
        return false;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, full_name, password_hash FROM admins WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function verifyPassword(int $id, string $current_password): bool
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM admins WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($hash);
        $stmt->fetch();
        $stmt->close();
        return password_verify($current_password, (string) $hash);
    }

    public function updatePassword(int $id, string $new_password): bool
    {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('UPDATE admins SET password_hash=? WHERE id=?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
