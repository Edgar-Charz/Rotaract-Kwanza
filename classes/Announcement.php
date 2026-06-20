<?php
class Announcement
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $title,
        string $slug,
        string $content,
        string $image_path,
        string $category,
        int $is_published = 0
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO announcements (title, slug, content, image_path, category, is_published)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('sssssi', $title, $slug, $content, $image_path, $category, $is_published);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function findBySlug(string $slug): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM announcements WHERE slug = ? AND is_published = 1 LIMIT 1"
        );
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getImagePathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM announcements WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function getPublished(int $limit = 0, string $category = ''): array
    {
        $where = "WHERE is_published = 1";
        $params = [];
        $types = '';

        if ($category !== '') {
            $where .= ' AND category = ?';
            $types .= 's';
            $params[] = $category;
        }

        $limitClause = $limit > 0 ? " LIMIT $limit" : '';
        $sql = "SELECT * FROM announcements $where ORDER BY created_at DESC$limitClause";

        $stmt = $this->db->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(string $category = ''): array
    {
        if ($category !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM announcements WHERE category = ? ORDER BY created_at DESC'
            );
            $stmt->bind_param('s', $category);
        } else {
            $stmt = $this->db->prepare('SELECT * FROM announcements ORDER BY created_at DESC');
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function countPublished(string $category = ''): int
    {
        if ($category !== '') {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM announcements WHERE is_published = 1 AND category = ?"
            );
            $stmt->bind_param('s', $category);
        } else {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM announcements WHERE is_published = 1"
            );
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function update(
        int $id,
        string $title,
        string $content,
        string $image_path,
        string $category,
        int $is_published
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE announcements SET title=?, content=?, image_path=?, category=?, is_published=? WHERE id=?'
        );
        $stmt->bind_param('ssssii', $title, $content, $image_path, $category, $is_published, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function togglePublished(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE announcements SET is_published = 1 - is_published WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM announcements WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
