<?php
class LeadershipTermPhoto
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function create(int $term_id, string $image_path, int $display_order = 0): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO leadership_term_photos (term_id, image_path, display_order) VALUES (?, ?, ?)'
        );
        $stmt->bind_param('isi', $term_id, $image_path, $display_order);
        $stmt->execute();
        $id = (int) $this->db->insert_id;
        $stmt->close();
        return $id;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM leadership_term_photos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: false;
    }

    public function getByTerm(int $term_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM leadership_term_photos WHERE term_id = ? ORDER BY display_order ASC, created_at ASC'
        );
        $stmt->bind_param('i', $term_id);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Batched form of getByTerm() for pages that render every term's photos
     * at once (e.g. leadership_history.php) — one query instead of one per
     * term. Returns rows grouped by term_id, each already in display order.
     *
     * @param int[] $term_ids
     * @return array<int, array> term_id => photo rows
     */
    public function getByTerms(array $term_ids): array
    {
        $grouped = [];
        if (!$term_ids) return $grouped;

        $placeholders = implode(',', array_fill(0, count($term_ids), '?'));
        $types = str_repeat('i', count($term_ids));
        $stmt = $this->db->prepare(
            "SELECT * FROM leadership_term_photos WHERE term_id IN ($placeholders) ORDER BY term_id ASC, display_order ASC, created_at ASC"
        );
        $stmt->bind_param($types, ...$term_ids);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $row) {
            $grouped[(int) $row['term_id']][] = $row;
        }
        return $grouped;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM leadership_term_photos WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }
}
