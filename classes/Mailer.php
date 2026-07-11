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
        $this->fromEmail = $fromEmail ?: 'noreply@rotaractkwanza.org';
        $this->fromName  = $fromName  ?: $siteName;
        $this->siteName  = $siteName;
    }

    public function send(string $to, string $toName, string $subject, string $htmlBody): bool
    {
        $subject = str_replace(["\r", "\n"], '', $subject);
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $this->fromName . ' <' . $this->fromEmail . '>',
            'Reply-To: ' . $this->fromEmail,
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

    public function rsvpConfirmation(string $to, string $name, array $event): bool
    {
        $n     = htmlspecialchars($name);
        $title = htmlspecialchars($event['title']);
        $date  = date('l, d F Y', strtotime($event['event_date']));
        $time  = $event['event_time'] ? ' &mdash; ' . htmlspecialchars($event['event_time']) : '';
        $loc   = $event['location']   ? '<br>📍 ' . htmlspecialchars($event['location']) : '';

        $html = "<p>Hi <strong>$n</strong>,</p>
<p>Your RSVP has been confirmed! We look forward to seeing you at:</p>
<div style='background:#fce4ef;border-radius:10px;padding:16px 20px;margin:16px 0'>
  <div style='font-size:17px;font-weight:700;color:#C0396B'>$title</div>
  <div style='margin-top:6px;color:#2d3436'>📅 $date$time$loc</div>
</div>
<p>If you need to cancel or make changes, please contact us directly.</p>
<p style='color:#636e72;font-size:13px;margin-top:24px'>See you there!<br>The Rotaract Kwanza Team</p>";

        return $this->send($to, $name, "RSVP Confirmed: $title", $html);
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
            $ss       = new SiteSettings($conn);
            $email    = $ss->get('contact_email', 'noreply@rotaractkwanza.org');
            $siteName = $ss->get('site_name', 'Rotaract Kwanza');
        } catch (Throwable $e) {
            $email    = 'noreply@rotaractkwanza.org';
            $siteName = 'Rotaract Kwanza';
        }
        return new self($email, $siteName, $siteName);
    }
}
