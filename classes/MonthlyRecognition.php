<?php

class MonthlyRecognition
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    public function getLatestPublished(): array|false
    {
        $result = $this->db->query(
            "SELECT * FROM monthly_recognitions
             WHERE is_published = 1
             ORDER BY recognition_month DESC LIMIT 1"
        );
        $recognition = $result->fetch_assoc();
        if (!$recognition) {
            return false;
        }
        $recognition['members'] = $this->getMembers((int) $recognition['id']);
        return $recognition;
    }

    public function getPublished(): array
    {
        $result = $this->db->query(
            "SELECT * FROM monthly_recognitions
             WHERE is_published = 1
             ORDER BY recognition_month DESC"
        );
        $recognitions = $result->fetch_all(MYSQLI_ASSOC);
        foreach ($recognitions as &$recognition) {
            $recognition['members'] = $this->getMembers((int) $recognition['id']);
            foreach ($recognition['members'] as &$member) {
                $member['photos'] = $this->getPhotos((int) $recognition['id'], (int) $member['id']);
            }
            unset($member);
        }
        unset($recognition);
        return $recognitions;
    }

    public function getAll(): array
    {
        $result = $this->db->query(
            "SELECT r.*, COUNT(rm.member_id) AS member_count
             FROM monthly_recognitions r
             LEFT JOIN monthly_recognition_members rm ON rm.recognition_id = r.id
             GROUP BY r.id
             ORDER BY r.recognition_month DESC"
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }

    public function findByMonth(string $month): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM monthly_recognitions WHERE recognition_month = ? LIMIT 1');
        $stmt->bind_param('s', $month);
        $stmt->execute();
        $recognition = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$recognition) {
            return false;
        }
        $recognition['members'] = $this->getMembers((int) $recognition['id']);
        return $recognition;
    }

    public function findById(int $id): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM monthly_recognitions WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $recognition = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$recognition) {
            return false;
        }
        $recognition['members'] = $this->getMembers($id);
        return $recognition;
    }

    public function getPhotos(int $recognitionId, int $memberId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM monthly_recognition_photos WHERE recognition_id = ? AND member_id = ? ORDER BY display_order, id');
        $stmt->bind_param('ii', $recognitionId, $memberId);
        $stmt->execute();
        $photos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $photos;
    }

    public function save(string $month, string $citation, bool $published, array $memberIds): void
    {
        $this->db->begin_transaction();
        try {
            $existing = $this->findByMonth($month);
            $isPublished = $published ? 1 : 0;
            if ($existing) {
                $id = (int) $existing['id'];
                $stmt = $this->db->prepare('UPDATE monthly_recognitions SET citation = ?, is_published = ? WHERE id = ?');
                $stmt->bind_param('sii', $citation, $isPublished, $id);
                $stmt->execute();
                $stmt->close();
            } else {
                $stmt = $this->db->prepare('INSERT INTO monthly_recognitions (recognition_month, citation, is_published) VALUES (?, ?, ?)');
                $stmt->bind_param('ssi', $month, $citation, $isPublished);
                $stmt->execute();
                $id = (int) $this->db->insert_id;
                $stmt->close();
            }

            $previousCitations = [];
            $stmt = $this->db->prepare('SELECT member_id, citation FROM monthly_recognition_members WHERE recognition_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
                $previousCitations[(int) $row['member_id']] = $row['citation'] ?? '';
            }
            $stmt->close();
            $stmt = $this->db->prepare('DELETE FROM monthly_recognition_members WHERE recognition_id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $this->db->prepare('INSERT INTO monthly_recognition_members (recognition_id, member_id, display_order, citation) VALUES (?, ?, ?, ?)');
            foreach (array_values(array_unique(array_map('intval', $memberIds))) as $order => $memberId) {
                if ($memberId > 0) {
                    $memberCitation = $previousCitations[$memberId] ?? '';
                    $stmt->bind_param('iiis', $id, $memberId, $order, $memberCitation);
                    $stmt->execute();
                }
            }
            $stmt->close();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateDetails(int $id, string $citation, bool $published, array $memberCitations): void
    {
        $this->db->begin_transaction();
        try {
            $isPublished = $published ? 1 : 0;
            $stmt = $this->db->prepare('UPDATE monthly_recognitions SET citation = ?, is_published = ? WHERE id = ?');
            $stmt->bind_param('sii', $citation, $isPublished, $id);
            $stmt->execute();
            $stmt->close();
            $stmt = $this->db->prepare('UPDATE monthly_recognition_members SET citation = ? WHERE recognition_id = ? AND member_id = ?');
            foreach ($memberCitations as $memberId => $memberCitation) {
                $memberId = (int) $memberId;
                $memberCitation = trim((string) $memberCitation);
                $stmt->bind_param('sii', $memberCitation, $id, $memberId);
                $stmt->execute();
            }
            $stmt->close();
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    public function updateRecipientCitation(int $recognitionId, int $memberId, string $citation): bool
    {
        $stmt = $this->db->prepare('UPDATE monthly_recognition_members SET citation = ? WHERE recognition_id = ? AND member_id = ?');
        $stmt->bind_param('sii', $citation, $recognitionId, $memberId);
        $stmt->execute();
        $ok = $stmt->affected_rows >= 0;
        $stmt->close();
        return $ok;
    }

    public function removeRecipient(int $recognitionId, int $memberId): bool
    {
        $stmt = $this->db->prepare('DELETE FROM monthly_recognition_members WHERE recognition_id = ? AND member_id = ?');
        $stmt->bind_param('ii', $recognitionId, $memberId);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM monthly_recognitions WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $ok = $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    }

    private function getMembers(int $recognitionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.occupation, m.year_of_study, m.bio,
                    COALESCE(m.linkedin_url, '') AS linkedin_url, COALESCE(m.instagram_url, '') AS instagram_url,
                    COALESCE(m.photo_path, '') AS photo_path,
                    COALESCE(rm.citation, '') AS citation
             FROM monthly_recognition_members rm
             JOIN members m ON m.id = rm.member_id
             WHERE rm.recognition_id = ?
             ORDER BY rm.display_order, m.first_name, m.last_name"
        );
        $stmt->bind_param('i', $recognitionId);
        $stmt->execute();
        $members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $members;
    }
}
