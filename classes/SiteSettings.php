<?php
class SiteSettings
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function get(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare(
            'SELECT setting_value FROM site_settings WHERE setting_key = ?'
        );
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($value);
        $found = $stmt->fetch();
        $stmt->close();
        return $found ? (string) $value : $default;
    }

    public function set(string $key, string $value): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO site_settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
