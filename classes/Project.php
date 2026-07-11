<?php
class Project
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $title,
        string $description,
        string $impact_stat,
        string $impact_label,
        string $icon_type,
        string $status = 'active',
        int $is_featured = 0,
        string $image_path = '',
        string $instagram_url = '',
        string $tiktok_url = ''
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO projects (title, description, impact_stat, impact_label, icon_type, status, is_featured, image_path, instagram_url, tiktok_url)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssssisss', $title, $description, $impact_stat, $impact_label, $icon_type, $status, $is_featured, $image_path, $instagram_url, $tiktok_url);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM projects WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getTitleById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT title FROM projects WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($title);
        $stmt->fetch();
        $stmt->close();
        return (string) $title;
    }

    public function count(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM projects');
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getFeatured(int $limit = 4): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects WHERE is_featured = 1 ORDER BY created_at DESC LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects ORDER BY is_featured DESC, created_at DESC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function update(
        int $id,
        string $title,
        string $description,
        string $impact_stat,
        string $impact_label,
        string $icon_type,
        string $status,
        int $is_featured,
        string $image_path = '',
        string $instagram_url = '',
        string $tiktok_url = ''
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE projects SET title=?, description=?, impact_stat=?, impact_label=?,
             icon_type=?, status=?, is_featured=?, image_path=?, instagram_url=?, tiktok_url=? WHERE id=?'
        );
        $stmt->bind_param('ssssssisssi', $title, $description, $impact_stat, $impact_label, $icon_type, $status, $is_featured, $image_path, $instagram_url, $tiktok_url, $id);
        $stmt->execute();
        $stmt->close();
        return true;
    }

    public function getImagePathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM projects WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM projects WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
