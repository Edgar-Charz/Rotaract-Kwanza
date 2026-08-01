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

    /** id => image_path map for a set of rows — used by bulk delete to clean up files with one query. */
    public function getImagePathsByIds(array $ids): array
    {
        if (!$ids) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT id, image_path FROM gallery WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        $map = [];
        foreach ($rows as $row) $map[(int) $row['id']] = $row['image_path'];
        return $map;
    }

    /** Batched form of toggleVisibility()-style updates for admin bulk actions — one query instead of one per row. */
    public function updateVisibilityBatch(array $ids, int $active): int
    {
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE gallery SET is_active=? WHERE id IN ($placeholders)");
        $stmt->bind_param('i' . str_repeat('i', count($ids)), $active, ...$ids);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return (int) $n;
    }

    public function updateCategoryBatch(array $ids, string $category): int
    {
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("UPDATE gallery SET category=? WHERE id IN ($placeholders)");
        $stmt->bind_param('s' . str_repeat('i', count($ids)), $category, ...$ids);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return (int) $n;
    }

    /** Batched form of delete() for admin bulk actions — one query instead of one per row. */
    public function deleteBatch(array $ids): int
    {
        if (!$ids) return 0;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("DELETE FROM gallery WHERE id IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $stmt->execute();
        $n = $stmt->affected_rows;
        $stmt->close();
        return (int) $n;
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

    /** Paginated, active-only listing for the public gallery page. */
    public function getActivePage(int $limit, int $offset, string $category = ''): array
    {
        $sql = 'SELECT * FROM gallery WHERE is_active = 1';
        $types = '';
        $params = [];
        if ($category !== '') {
            $sql .= ' AND category = ?';
            $types .= 's';
            $params[] = $category;
        }
        $sql .= ' ORDER BY display_order ASC, created_at DESC LIMIT ? OFFSET ?';
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

    public function countActiveFiltered(string $category = ''): int
    {
        $sql = 'SELECT COUNT(*) FROM gallery WHERE is_active = 1';
        $types = '';
        $params = [];
        if ($category !== '') {
            $sql .= ' AND category = ?';
            $types = 's';
            $params[] = $category;
        }
        $stmt = $this->db->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getActiveCategories(): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT category FROM gallery WHERE is_active = 1 AND category IS NOT NULL AND category != '' ORDER BY category"
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_column($rows, 'category');
    }

    public function count(string $category = '', string $search = ''): int
    {
        [$where, $types, $params] = $this->buildFilter($category, $search);
        $sql = 'SELECT COUNT(*) FROM gallery';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        if ($types !== '') $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getPage(int $limit, int $offset, string $category = '', string $search = ''): array
    {
        [$where, $types, $params] = $this->buildFilter($category, $search);
        $sql = 'SELECT * FROM gallery';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY display_order ASC, created_at DESC LIMIT ? OFFSET ?';
        $types    .= 'ii';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    private function buildFilter(string $category, string $search): array
    {
        $where  = [];
        $types  = '';
        $params = [];
        if ($category !== '') {
            $where[]  = 'category = ?';
            $types   .= 's';
            $params[] = $category;
        }
        if ($search !== '') {
            $where[]  = '(title LIKE ? OR description LIKE ?)';
            $types   .= 'ss';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        return [$where, $types, $params];
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
