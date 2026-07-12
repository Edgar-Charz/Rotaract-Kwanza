<?php
class LeadershipTerm
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public static function parseYears(string $label): array
    {
        if (preg_match('/(\d{4})\D+(\d{4})/', $label, $m)) {
            return [(int) $m[1], (int) $m[2]];
        }
        if (preg_match('/(\d{4})/', $label, $m)) {
            $y = (int) $m[1];
            return [$y, $y + 1];
        }
        return [0, 0];
    }

    public function create(
        string $term_label,
        ?int $year_start,
        ?int $year_end,
        string $summary,
        string $image_path,
        int $display_order = 0,
        int $is_active = 1
    ): int {
        if (!$year_start && !$year_end) {
            [$year_start, $year_end] = self::parseYears($term_label);
        }
        $stmt = $this->db->prepare(
            'INSERT INTO leadership_terms (term_label, year_start, year_end, summary, image_path, display_order, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('siissii', $term_label, $year_start, $year_end, $summary, $image_path, $display_order, $is_active);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM leadership_terms WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function findByLabel(string $label): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM leadership_terms WHERE term_label = ? LIMIT 1');
        $stmt->bind_param('s', $label);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getLabelById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT term_label FROM leadership_terms WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($label);
        $stmt->fetch();
        $stmt->close();
        return (string) $label;
    }

    public function getImagePathById(int $id): string
    {
        $stmt = $this->db->prepare('SELECT image_path FROM leadership_terms WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->bind_result($path);
        $stmt->fetch();
        $stmt->close();
        return (string) $path;
    }

    public function getAllWithMemberCounts(): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, COUNT(m.id) AS member_count
             FROM leadership_terms t
             LEFT JOIN leadership_members m ON m.term_id = t.id
             GROUP BY t.id
             ORDER BY t.year_start DESC, t.display_order ASC, t.id DESC'
        );
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    public function getActiveWithMembers(): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM leadership_terms WHERE is_active = 1
             ORDER BY year_start DESC, display_order ASC, id DESC'
        );
        $stmt->execute();
        $terms = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$terms) {
            return [];
        }

        require_once dirname(__FILE__) . '/LeadershipMember.php';
        $lm = new LeadershipMember($this->db);
        foreach ($terms as &$term) {
            $term['members'] = $lm->getActiveByTermId((int) $term['id']);
        }
        unset($term);

        return array_values(array_filter($terms, fn($t) => !empty($t['members'])));
    }

    public function update(
        int $id,
        string $term_label,
        ?int $year_start,
        ?int $year_end,
        string $summary,
        string $image_path,
        int $display_order,
        int $is_active
    ): bool {
        if (!$year_start && !$year_end) {
            [$year_start, $year_end] = self::parseYears($term_label);
        }
        $stmt = $this->db->prepare(
            'UPDATE leadership_terms SET term_label=?, year_start=?, year_end=?, summary=?, image_path=?, display_order=?, is_active=? WHERE id=?'
        );
        $stmt->bind_param('siissiii', $term_label, $year_start, $year_end, $summary, $image_path, $display_order, $is_active, $id);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM leadership_terms WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
