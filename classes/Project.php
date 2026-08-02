<?php
class Project
{
    // Single source of truth for the project status workflow — key => admin-facing
    // label. Referenced by admin/projects.php's add/edit selects instead of
    // retyping the same three strings in both modals.
    //
    // 'featured' used to be a third option here, redundant with the separate
    // is_featured flag (which already controls the "Featured" badge + sort
    // priority) — a project could say status "Featured" while a different
    // is_featured=1 project also showed the identical badge, which just
    // confused visitors comparing two cards. Dropped in favor of is_featured
    // alone; existing 'featured'-status rows were migrated to 'active'.
    const STATUSES = [
        'active'    => 'Active',
        'planning'  => 'Planning',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

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
        string $tiktok_url = '',
        string $x_url = '',
        ?string $start_date = null,
        ?string $end_date = null,
        ?float $funding_goal = null
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO projects (title, description, impact_stat, impact_label, icon_type, status, is_featured, image_path, instagram_url, tiktok_url, x_url, start_date, end_date, funding_goal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('ssssssissssssd', $title, $description, $impact_stat, $impact_label, $icon_type, $status, $is_featured, $image_path, $instagram_url, $tiktok_url, $x_url, $start_date, $end_date, $funding_goal);
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

    public function count(string $search = ''): int
    {
        if ($search !== '') {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM projects WHERE title LIKE ? OR description LIKE ?');
            $like = "%$search%";
            $stmt->bind_param('ss', $like, $like);
        } else {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM projects');
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    /** Paginated + optionally keyword-filtered listing for the public projects page. */
    public function getPage(int $limit, int $offset, string $search = ''): array
    {
        if ($search !== '') {
            $stmt = $this->db->prepare(
                'SELECT * FROM projects WHERE title LIKE ? OR description LIKE ?
                 ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?'
            );
            $like = "%$search%";
            $stmt->bind_param('ssii', $like, $like, $limit, $offset);
        } else {
            $stmt = $this->db->prepare(
                'SELECT * FROM projects ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?'
            );
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Count/page variants scoped to projects that belong in the main public
     * grid (status 'active' or 'planning') — completed work gets its own
     * section below (see getCompleted()) and cancelled projects are hidden
     * from public listings entirely (same as Event's cancelled status),
     * the same way Event's getUpcoming()/getPast() split keeps past events
     * out of the main list.
     */
    public function countActive(string $search = ''): int
    {
        if ($search !== '') {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM projects WHERE status NOT IN ('completed', 'cancelled') AND (title LIKE ? OR description LIKE ?)");
            $like = "%$search%";
            $stmt->bind_param('ss', $like, $like);
        } else {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM projects WHERE status NOT IN ('completed', 'cancelled')");
        }
        $stmt->execute();
        $stmt->bind_result($n);
        $stmt->fetch();
        $stmt->close();
        return (int) $n;
    }

    public function getActivePage(int $limit, int $offset, string $search = ''): array
    {
        if ($search !== '') {
            $stmt = $this->db->prepare(
                "SELECT * FROM projects WHERE status NOT IN ('completed', 'cancelled') AND (title LIKE ? OR description LIKE ?)
                 ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?"
            );
            $like = "%$search%";
            $stmt->bind_param('ssii', $like, $like, $limit, $offset);
        } else {
            $stmt = $this->db->prepare(
                "SELECT * FROM projects WHERE status NOT IN ('completed', 'cancelled')
                 ORDER BY is_featured DESC, created_at DESC LIMIT ? OFFSET ?"
            );
            $stmt->bind_param('ii', $limit, $offset);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** Most recently finished completed projects, for the "Completed Projects" section. */
    public function getCompleted(int $limit = 6): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM projects WHERE status = 'completed'
             ORDER BY COALESCE(end_date, created_at) DESC LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
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

    // $limit is a safety cap, not a feature — prevents an unbounded fetch from
    // ever loading the entire table into memory if it grows very large; 5000
    // is far above any realistic row count for this app.
    public function getAll(int $limit = 5000): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM projects ORDER BY is_featured DESC, created_at DESC LIMIT ?'
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /** Same as getAll() but excludes cancelled projects — for the homepage
     *  preview grid, which shouldn't ever surface a scrapped project just
     *  to fill a slot. */
    public function getAllExcludingCancelled(int $limit = 5000): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM projects WHERE status != 'cancelled' ORDER BY is_featured DESC, created_at DESC LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
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
        string $tiktok_url = '',
        string $x_url = '',
        ?string $start_date = null,
        ?string $end_date = null,
        ?float $funding_goal = null
    ): bool {
        $stmt = $this->db->prepare(
            'UPDATE projects SET title=?, description=?, impact_stat=?, impact_label=?,
             icon_type=?, status=?, is_featured=?, image_path=?, instagram_url=?, tiktok_url=?, x_url=?, start_date=?, end_date=?, funding_goal=? WHERE id=?'
        );
        $stmt->bind_param('ssssssissssssdi', $title, $description, $impact_stat, $impact_label, $icon_type, $status, $is_featured, $image_path, $instagram_url, $tiktok_url, $x_url, $start_date, $end_date, $funding_goal, $id);
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
