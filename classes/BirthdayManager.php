<?php
require_once __DIR__ . '/Member.php';
require_once __DIR__ . '/TeamMember.php';
require_once __DIR__ . '/LeadershipMember.php';
require_once __DIR__ . '/Mailer.php';

class BirthdayManager
{
    private mysqli $db;

    public function __construct(mysqli $conn)
    {
        $this->db = $conn;
    }

    /**
     * Checks for birthdays today across members, team officers, and past leadership.
     * Automatically sends Happy Birthday emails to any unsent birthday today.
     * Returns ['today' => [...], 'upcoming' => [...]]
     */
    public function checkAndAutoSendBirthdays(): array
    {
        $memberObj     = new Member($this->db);
        $teamObj       = new TeamMember($this->db);
        $leadershipObj = new LeadershipMember($this->db);
        $mailer        = Mailer::fromSettings($this->db);
        $todayDate     = date('Y-m-d');

        // Fetch today's birthdays across all tables
        $todaysMembers    = $memberObj->getTodaysBirthdays(false);
        $todaysTeam       = $teamObj->getTodaysBirthdays(false);
        $todaysLeadership = $leadershipObj->getTodaysBirthdays(false);
        $todaysAll        = array_merge($todaysMembers, $todaysTeam, $todaysLeadership);

        // Auto-send emails to any unsent birthday today
        foreach ($todaysAll as $item) {
            if (!empty($item['email']) && (empty($item['last_birthday_email_sent']) || $item['last_birthday_email_sent'] !== $todayDate)) {
                $name = trim($item['first_name'] . ' ' . ($item['last_name'] ?? ''));
                try {
                    $sent = $mailer->birthdayGreeting($item['email'], $name);
                    if ($sent) {
                        $this->markSent($item['id'], $item['source_type'], $todayDate);
                        log_activity('birthday_auto_send', "Automated Happy Birthday email sent to $name ({$item['email']})");
                    }
                } catch (Throwable $e) {
                }
            }
        }

        // Refresh lists after potential auto-send
        $todaysMembers    = $memberObj->getTodaysBirthdays(false);
        $todaysTeam       = $teamObj->getTodaysBirthdays(false);
        $todaysLeadership = $leadershipObj->getTodaysBirthdays(false);
        $todaysAll        = array_merge($todaysMembers, $todaysTeam, $todaysLeadership);

        // Fetch upcoming birthdays (next 48h)
        $upcomingMembers    = $memberObj->getUpcomingBirthdays(2);
        $upcomingTeam       = $teamObj->getUpcomingBirthdays(2);
        $upcomingLeadership = $leadershipObj->getUpcomingBirthdays(2);
        $upcomingAll        = array_merge($upcomingMembers, $upcomingTeam, $upcomingLeadership);

        return [
            'today'    => $todaysAll,
            'upcoming' => $upcomingAll
        ];
    }

    /**
     * Manually sends a Happy Birthday email to a specific person by ID and source_type.
     */
    public function sendBirthdayWish(int $id, string $sourceType): bool
    {
        $email = '';
        $name  = '';

        if ($sourceType === 'member') {
            $m = (new Member($this->db))->findById($id);
            if ($m) {
                $email = $m['email'] ?? '';
                $name  = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
            }
        } elseif ($sourceType === 'team_member') {
            $tm = (new TeamMember($this->db))->findById($id);
            if ($tm) {
                $email = $tm['email'] ?? '';
                $name  = $tm['full_name'] ?? '';
            }
        } elseif ($sourceType === 'leadership_member') {
            $lm = (new LeadershipMember($this->db))->findById($id);
            if ($lm) {
                $email = $lm['email'] ?? '';
                $name  = $lm['full_name'] ?? '';
            }
        }

        if (!$email) {
            throw new Exception("Recipient email address not found.");
        }

        $mailer   = Mailer::fromSettings($this->db);
        $today    = date('Y-m-d');
        $sent     = $mailer->birthdayGreeting($email, $name);

        if ($sent) {
            $this->markSent($id, $sourceType, $today);
            log_activity('birthday_manual_send', "Manually sent Happy Birthday email to $name ($email)");
            return true;
        }

        return false;
    }

    /**
     * Marks last_birthday_email_sent on the target table.
     */
    public function markSent(int $id, string $sourceType, string $date): bool
    {
        if ($sourceType === 'member') {
            return (new Member($this->db))->markBirthdayEmailSent($id, $date);
        } elseif ($sourceType === 'team_member') {
            return (new TeamMember($this->db))->markBirthdayEmailSent($id, $date);
        } elseif ($sourceType === 'leadership_member') {
            return (new LeadershipMember($this->db))->markBirthdayEmailSent($id, $date);
        }
        return false;
    }
}
