<?php

class RecognitionPhoto
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function getByRecipient(int $recognitionId, int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM monthly_recognition_photos WHERE recognition_id = ? AND member_id = ? ORDER BY display_order, id');
        $stmt->bind_param('ii', $recognitionId, $memberId);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $photos;
    }

    public function create(int $recognitionId, int $memberId, string $imagePath, int $displayOrder, string $caption = ''): void
    {
        $stmt = $this->db->prepare('INSERT INTO monthly_recognition_photos (recognition_id, member_id, image_path, caption, display_order) VALUES (?, ?, ?, ?, ?)');
        $stmt->bind_param('iissi', $recognitionId, $memberId, $imagePath, $caption, $displayOrder);
        $stmt->execute();
        $stmt->close();
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM monthly_recognition_photos WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $photo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $photo ?: false;
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM monthly_recognition_photos WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
}
