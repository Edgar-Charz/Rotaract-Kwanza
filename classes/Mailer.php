<?php
/**
 * Simple mail wrapper using PHP's built-in mail().
 * Reads from/name from site_settings if not overridden.
 * Structure mirrors PHPMailer so swapping later is easy.
 */
class Mailer
{
    private string $fromEmail;
    private string $fromName;
    private string $siteName;

    public function __construct(string $fromEmail = '', string $fromName = '', string $siteName = 'Rotaract Kwanza')
    {
        $strip = fn(string $v): string => str_replace(["\r", "\n"], '', $v);
        $this->fromEmail = $strip($fromEmail ?: 'noreply@rotaractkwanza.org');
        $this->fromName  = $strip($fromName  ?: $siteName);
        $this->siteName  = $strip($siteName);
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody, string $replyTo = ''): bool
    {
        $subject = str_replace(["\r", "\n"], '', $subject);
        $strip = fn(string $v): string => str_replace(["\r", "\n"], '', $v);
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . ($replyTo ? $strip($replyTo) : $this->fromEmail),
            'X-Mailer: PHP/' . phpversion(),
        ]);

        $sent = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $this->wrap($htmlBody), $headers);
        if (!$sent) {
            error_log("Mailer: failed to send \"$subject\" to $to (check php.ini mail/sendmail configuration)");
        }
        return $sent;
    }

    // ── Email templates ───────────────────────────────────────────────────────

    public function applicationReceived(string $to, string $name, string $clubName = ''): bool
    {
        $club = htmlspecialchars($clubName ?: $this->siteName);
        $n    = htmlspecialchars($name);
        $html = "<p>Hi <strong>$n</strong>,</p>
<p>Thank you for applying to join <strong>$club</strong>!</p>
<p>Your application has been received and is currently under review. Our team will be in touch once a decision has been made.</p>
<p>In the meantime, feel free to follow us on social media for the latest updates.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>The Rotaract Kwanza Team</p>";
        return $this->send($to, $name, "Application Received — $club", $html);
    }

    public function applicationStatusChange(string $to, string $name, string $status, string $clubName = ''): bool
    {
        $club = htmlspecialchars($clubName ?: $this->siteName);
        $n    = htmlspecialchars($name);

        if ($status === 'approved') {
            $html = "<p>Hi <strong>$n</strong>,</p>
<p>We are delighted to inform you that your application to join <strong>$club</strong> has been <strong style='color:#27ae60'>approved!</strong></p>
<p>Welcome to the Rotaract family. We look forward to serving alongside you and making a positive impact in our community.</p>
<p>An officer will reach out soon with next steps for onboarding.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>Warmly,<br>The Rotaract Kwanza Team</p>";
            $subject = "Welcome to $club — Application Approved!";
        } else {
            $html = "<p>Hi <strong>$n</strong>,</p>
<p>Thank you for your interest in joining <strong>$club</strong>.</p>
<p>After careful consideration, we are unable to move your application forward at this time.</p>
<p>We appreciate your enthusiasm for service and encourage you to stay connected with us or reapply in a future cycle. If you have any questions, please don't hesitate to reach out.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>With respect,<br>The Rotaract Kwanza Team</p>";
            $subject = "Update on Your Application to $club";
        }

        return $this->send($to, $name, $subject, $html);
    }

    public function birthdayGreeting(string $to, string $name, string $clubName = ''): bool
    {
        $club = htmlspecialchars($clubName ?: $this->siteName);
        $n    = htmlspecialchars($name);
        $html = "<p>Hi <strong>$n</strong>,</p>
<p>🎉 Happy Birthday from all of us at <strong>$club</strong>!</p>
<p>We hope your day is filled with joy, and we're grateful to have you as part of our Rotaract family.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>Warmly,<br>The Rotaract Kwanza Team</p>";
        return $this->send($to, $name, "🎂 Happy Birthday, $n!", $html);
    }

    public function rsvpConfirmation(string $to, string $name, array $event, bool $isMember = false, string $cancelUrl = ''): bool
    {
        $n     = htmlspecialchars($name);
        $title = htmlspecialchars($event['title']);
        $date  = date('l, d F Y', strtotime($event['event_date']));
        $time  = $event['event_time'] ? ' &mdash; ' . htmlspecialchars($event['event_time']) : '';
        $loc   = $event['location']   ? '<br>📍 ' . htmlspecialchars($event['location']) : '';

        $greeting = $isMember
            ? "<p>Hi <strong>$n</strong>,</p><p>Great to have you confirmed — thanks for being an active member!</p>"
            : "<p>Hi <strong>$n</strong>,</p><p>Your RSVP has been confirmed! We look forward to seeing you at:</p>";

        $cancelLine = $cancelUrl
            ? "<p>Can't make it anymore? <a href='" . htmlspecialchars($cancelUrl) . "'>Cancel your RSVP</a>.</p>"
            : "<p>If you need to cancel or make changes, please contact us directly.</p>";

        $html = "$greeting
<div style='background:#fce4ef;border-radius:10px;padding:16px 20px;margin:16px 0'>
  <div style='font-size:17px;font-weight:700;color:#C0396B'>$title</div>
  <div style='margin-top:6px;color:#2d3436'>📅 $date$time$loc</div>
</div>
$cancelLine"
            . (!$isMember ? "<p>Not a member yet? We'd love to have you — <a href='join.php'>learn how to join</a>.</p>" : '')
            . "<p style='color:#636e72;font-size:13px;margin-top:24px'>See you there!<br>The Rotaract Kwanza Team</p>";

        return $this->send($to, $name, "RSVP Confirmed: $title", $html);
    }

    public function projectSignupConfirmation(string $to, string $name, array $project, bool $isMember = false, string $cancelUrl = ''): bool
    {
        $n     = htmlspecialchars($name);
        $title = htmlspecialchars($project['title']);

        $greeting = $isMember
            ? "<p>Hi <strong>$n</strong>,</p><p>Thanks for stepping up again — we've got you down to help with:</p>"
            : "<p>Hi <strong>$n</strong>,</p><p>Thank you for volunteering! We've got you down to help with:</p>";

        $cancelLine = $cancelUrl
            ? "<p>Change of plans? <a href='" . htmlspecialchars($cancelUrl) . "'>Withdraw your signup</a>.</p>"
            : '';

        $html = "$greeting
<div style='background:#fce4ef;border-radius:10px;padding:16px 20px;margin:16px 0'>
  <div style='font-size:17px;font-weight:700;color:#C0396B'>$title</div>
</div>
<p>An officer will be in touch soon with details on how you can help.</p>
$cancelLine"
            . (!$isMember ? "<p>Enjoyed helping out? We'd love to have you as a member — <a href='join.php'>learn how to join</a>.</p>" : '')
            . "<p style='color:#636e72;font-size:13px;margin-top:24px'>Thank you for your service!<br>The Rotaract Kwanza Team</p>";

        return $this->send($to, $name, "Thanks for volunteering — $title", $html);
    }

    public function newsletterConfirmation(string $to, string $unsubscribeUrl, string $clubName = ''): bool
    {
        $club = htmlspecialchars($clubName ?: $this->siteName);
        $html = "<p>You're subscribed!</p>
<p>We'll email you whenever <strong>$club</strong> posts news, meeting minutes, or announcements.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>Didn't sign up for this? <a href='" . htmlspecialchars($unsubscribeUrl) . "'>Unsubscribe here</a> — no hard feelings.</p>";
        return $this->send($to, $to, "You're subscribed to $club updates", $html);
    }

    public function announcementNotification(string $to, array $post, string $unsubscribeUrl, string $clubName = ''): bool
    {
        $club   = htmlspecialchars($clubName ?: $this->siteName);
        $title  = htmlspecialchars($post['title']);
        $excerpt = htmlspecialchars(mb_strimwidth(trim(strip_tags($post['content'])), 0, 220, '…'));
        $link   = htmlspecialchars($post['url']);

        $html = "<p>New from <strong>$club</strong>:</p>
<div style='background:#fce4ef;border-radius:10px;padding:16px 20px;margin:16px 0'>
  <div style='font-size:17px;font-weight:700;color:#C0396B'>$title</div>
  <p style='margin:10px 0 0;color:#2d3436'>$excerpt</p>
</div>
<p><a href='$link'>Read the full post &rarr;</a></p>
<p style='color:#636e72;font-size:13px;margin-top:24px'><a href='" . htmlspecialchars($unsubscribeUrl) . "'>Unsubscribe</a> from these updates at any time.</p>";

        return $this->send($to, $to, "$title — $club", $html);
    }

    public function contactNotification(string $to, string $senderName, string $senderEmail, string $subject, string $msg): bool
    {
        $n    = htmlspecialchars($senderName);
        $em   = htmlspecialchars($senderEmail);
        $subj = htmlspecialchars($subject ?: '(no subject)');
        $body = nl2br(htmlspecialchars($msg));

        $html = "<p>New message from the contact form:</p>
<div style='background:#fce4ef;border-radius:10px;padding:16px 20px;margin:16px 0'>
  <div><strong>From:</strong> $n &lt;$em&gt;</div>
  <div><strong>Subject:</strong> $subj</div>
</div>
<p>$body</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>Reply directly to this email to respond to $n.</p>";

        return $this->send($to, $this->siteName, "New Contact Message: $subj", $html, $senderEmail);
    }

    public function contactConfirmation(string $to, string $name): bool
    {
        $n = htmlspecialchars($name);
        $html = "<p>Hi <strong>$n</strong>,</p>
<p>Thanks for reaching out to <strong>{$this->siteName}</strong>! We've received your message and will get back to you as soon as possible.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>The Rotaract Kwanza Team</p>";

        return $this->send($to, $name, "We received your message — {$this->siteName}", $html);
    }

    public function donationPledgeThanks(string $to, string $name, string $methodLabel, ?float $amount, ?string $projectTitle = null): bool
    {
        $n = htmlspecialchars($name);
        $methodLabel = htmlspecialchars($methodLabel);
        $amountLine = $amount ? "<p>Pledged amount: <strong>" . htmlspecialchars(number_format($amount, 2)) . "</strong></p>" : '';
        $purposeLine = $projectTitle
            ? "<p>Thank you so much for your generosity! We've noted your gift towards <strong>" . htmlspecialchars($projectTitle) . "</strong>, via <strong>$methodLabel</strong>.</p>"
            : "<p>Thank you so much for your generosity! We've noted your donation via <strong>$methodLabel</strong>.</p>";
        $html = "<p>Hi <strong>$n</strong>,</p>
$purposeLine
$amountLine
<p>This is an unverified pledge — one of our officers will follow up shortly to confirm receipt. If you haven't sent the funds yet, no rush; this is just so we know to expect it.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>With gratitude,<br>The Rotaract Kwanza Team</p>";

        return $this->send($to, $name, "Thank you for your donation — {$this->siteName}", $html);
    }

    // ── Private template wrapper ──────────────────────────────────────────────

    private function wrap(string $content): string
    {
        $site = htmlspecialchars($this->siteName);
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body{margin:0;padding:0;background:#f5f5f7;font-family:'Segoe UI',system-ui,sans-serif}
.wrap{max-width:600px;margin:30px auto;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.09)}
.hdr{background:linear-gradient(135deg,#C0396B 0%,#D4882A 100%);padding:28px 32px;text-align:center}
.hdr h1{margin:0;color:#fff;font-size:20px;font-weight:700;letter-spacing:.3px}
.hdr p{margin:4px 0 0;color:rgba(255,255,255,.75);font-size:13px}
.body{padding:32px;color:#2d3436;line-height:1.7;font-size:14px}
.ftr{padding:18px 32px;background:#f8f9fb;color:#b2bec3;font-size:11px;text-align:center;border-top:1px solid #eee}
a{color:#C0396B}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr"><h1>$site</h1><p>Rotaract Club of Kwanza</p></div>
  <div class="body">$content</div>
  <div class="ftr">&copy; $year $site &mdash; All rights reserved.</div>
</div>
</body></html>
HTML;
    }

    // ── Static factory using site_settings ────────────────────────────────────

    public static function fromSettings(mysqli $conn): self
    {
        try {
            require_once dirname(__FILE__) . '/../classes/SiteSettings.php';
            $ss        = new SiteSettings($conn);
            $siteName  = $ss->get('site_name', 'Rotaract Kwanza');
            $fromEmail = $ss->get('mail_from_email', '') ?: $ss->get('contact_email', 'noreply@rotaractkwanza.org');
            $fromName  = $ss->get('mail_from_name', '') ?: $siteName;
        } catch (Throwable $e) {
            $siteName  = 'Rotaract Kwanza';
            $fromEmail = 'noreply@rotaractkwanza.org';
            $fromName  = $siteName;
        }
        return new self($fromEmail, $fromName, $siteName);
    }
}
