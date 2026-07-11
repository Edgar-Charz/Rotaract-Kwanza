<?php
class Gallery
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(
        string $title,
        string $description,
        string $image_path,
        string $category,
        int $display_order = 0
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO gallery (title, description, image_path, category, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, 1)'
        );
        $stmt->bind_param('ssssi', $title, $description, $image_path, $category, $display_order);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM gallery WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getImagePathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM gallery WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function countActive(): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gallery WHERE is_active = 1');
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getActive(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gallery WHERE is_active = 1 ORDER BY display_order ASC, created_at DESC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM gallery ORDER BY display_order ASC, created_at DESC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function count(string $category = ''): int
    {
        if ($category !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM gallery WHERE category = ?');
            $stmt->bind_param('s', $category);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM gallery');
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getPage(int $limit, int $offset, string $category = ''): array
    {
        if ($category !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM gallery WHERE category = ? ORDER BY display_order ASC, created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bind_param('sii', $category, $limit, $offset);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM gallery ORDER BY display_order ASC, created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getCategories(): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_column($rows, 'category');
    }

    public function update(
        int $id,
        string $title,
        string $description,
        string $image_path,
        string $category,
        int $display_order,
        int $is_active
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE gallery SET title=?, description=?, image_path=?, category=?,
             display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('ssssiii', $title, $description, $image_path, $category, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function toggleVisibility(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE gallery SET is_active = 1 - is_active WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM gallery WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
