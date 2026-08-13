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
            'SELECT id, username, full_name, password_hash,
                    COALESCE(role, \'super_admin\') AS role,
                    must_change_password, is_suspended
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
            'SELECT id, username, full_name, password_hash, email,
                    COALESCE(role, \'super_admin\') AS role, must_change_password
             FROM admins WHERE id = ? LIMIT 1'
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

    // Any deliberate password change — self-service or after a forced reset —
    // clears must_change_password, since the account no longer needs one.
    public function updatePassword(int $id, string $new_password): bool
    {
        $hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('UPDATE admins SET password_hash=?, must_change_password=0 WHERE id=?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Super-admin-initiated reset: sets a new (temporary) password and flags
     * the account so the next successful login is redirected to
     * force_password_change.php before anything else is reachable.
     */
    public function resetPassword(int $id, string $temp_password): bool
    {
        $hash = password_hash($temp_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare('UPDATE admins SET password_hash=?, must_change_password=1 WHERE id=?');
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /** Used by register.php to self-disable once a first admin account exists. */
    public function count(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM admins');
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function usernameExists(string $username): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM admins WHERE username = ? LIMIT 1');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $ok;
    }

    public function create(string $username, string $password, string $full_name, string $role = 'editor', string $email = ''): int|false
    {
        if ($this->usernameExists($username)) {
            return false;
        }
        if (!in_array($role, ['super_admin', 'editor', 'viewer'], true)) {
            $role = 'editor';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare(
            'INSERT INTO admins (username, password_hash, full_name, role, email) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssss', $username, $hash, $full_name, $role, $email);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function updateEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE admins SET email=? WHERE id=?');
        $stmt->bind_param('si', $email, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function usernameTakenByOther(string $username, int $excludeId): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM admins WHERE username = ? AND id != ? LIMIT 1');
        $stmt->bind_param('si', $username, $excludeId);
        $stmt->execute();
        $ok = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $ok;
    }

    /** Returns false if the username is already taken by a different account. */
    public function updateUsername(int $id, string $new_username): bool
    {
        if ($this->usernameTakenByOther($new_username, $id)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE admins SET username=? WHERE id=?');
        $stmt->bind_param('si', $new_username, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    /**
     * Unlock an admin account by clearing its login attempt record.
     * Called by super admin from settings.php.
     */
    public function unlockAccount(string $username): bool
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE username = ?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Get all currently locked accounts (accounts with active lockouts).
     * Returns array of ['username', 'locked_until'] rows.
     */
    public function getLockedAccounts(): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.id, a.username, a.full_name, a.email,
                    UNIX_TIMESTAMP(la.locked_until) AS locked_until
             FROM login_attempts la
             INNER JOIN admins a ON a.username = la.username
             WHERE la.locked_until IS NOT NULL AND la.locked_until > NOW()
             ORDER BY locked_until DESC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Suspend an admin account (permanent until manually unsuspended).
     */
    public function suspendAccount(string $username): bool
    {
        $stmt = $this->db->prepare('UPDATE admins SET is_suspended=1 WHERE username=?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Unsuspend an admin account.
     */
    public function unsuspendAccount(string $username): bool
    {
        $stmt = $this->db->prepare('UPDATE admins SET is_suspended=0 WHERE username=?');
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    /**
     * Get all suspended admin accounts.
     */
    public function getSuspendedAccounts(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, username, email, full_name
             FROM admins
             WHERE is_suspended=1
             ORDER BY username'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }
}
