<?php

/**
 * Minimal dependency-free SMTP client — no Composer/PHPMailer in this project,
 * so this hand-rolls just enough of RFC 5321 to talk to a real mail server:
 * connect (plain/SSL), EHLO, optional STARTTLS, optional AUTH LOGIN, and a
 * single MAIL FROM/RCPT TO/DATA send. Built specifically so Mailer::send()
 * can swap from PHP's mail() to this without either side knowing about the
 * other's internals — Mailer builds the message, this just transports it.
 *
 * Deliberately not a general-purpose library: no multi-recipient batching,
 * no attachments, no connection pooling. This app sends one templated HTML
 * email at a time to one recipient; anything beyond that is out of scope
 * until an actual need shows up.
 */
class SmtpMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $encryption; // '', 'tls', or 'ssl'
    private int $timeoutSecs;

    /** @var resource|null */
    private $socket = null;

    public function __construct(
        string $host,
        int $port = 587,
        string $username = '',
        string $password = '',
        string $encryption = '',
        int $timeoutSecs = 10
    ) {
        $this->host        = $host;
        $this->port        = $port;
        $this->username    = $username;
        $this->password    = $password;
        $this->encryption  = strtolower($encryption);
        $this->timeoutSecs = max(3, $timeoutSecs);
    }

    /**
     * Sends one HTML email. Throws RuntimeException on any connection or
     * protocol failure — callers (Mailer::send()) catch this and fall back
     * to a bool return, matching how the mail()-based path never throws.
     */
    public function send(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $replyTo = ''
    ): void {
        $this->connect();
        $this->hello();
        if ($this->encryption === 'tls') {
            $this->startTls();
            $this->hello(); // EHLO again post-STARTTLS, per RFC 3207
        }
        if ($this->username !== '') {
            $this->authenticate();
        }

        $this->command('MAIL FROM:<' . $this->cleanAddress($fromEmail) . '>', [250]);
        $this->command('RCPT TO:<' . $this->cleanAddress($toEmail) . '>', [250, 251]);
        $this->command('DATA', [354]);

        $message = $this->buildMessage($fromEmail, $fromName, $toEmail, $toName, $subject, $htmlBody, $replyTo);
        // Normalize to CRLF *before* dot-stuffing: $htmlBody (built from a
        // heredoc template) carries bare \n line endings, but the SMTP server
        // only recognizes \r\n as a line boundary. Stuffing against \n-based
        // "lines" while the server parses \r\n-based ones meant a stuffed dot
        // was never recognized as such server-side and never got un-stuffed —
        // every CSS selector line (".wrap{", ".hdr{"...) arrived doubled.
        $message = preg_replace('/\r\n|\r|\n/', "\r\n", $message);
        // Dot-stuff any line that starts with "." — otherwise the server reads
        // it as the end-of-DATA marker and truncates the message there.
        $stuffed = preg_replace('/^\./m', '..', $message);
        $this->write($stuffed . "\r\n.\r\n");
        $this->readResponse([250]);

        $this->command('QUIT', [221], false);
        $this->close();
    }

    public function close(): void
    {
        if ($this->socket) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    // ── Connection setup ─────────────────────────────────────────────────────

    private function connect(): void
    {
        $target = ($this->encryption === 'ssl' ? 'ssl://' : '') . $this->host;
        $errno = 0;
        $errstr = '';
        $this->socket = @fsockopen($target, $this->port, $errno, $errstr, $this->timeoutSecs);
        if (!$this->socket) {
            throw new RuntimeException("Could not connect to $this->host:$this->port ($errstr)");
        }
        stream_set_timeout($this->socket, $this->timeoutSecs);
        $this->readResponse([220]);
    }

    private function hello(): void
    {
        $clientName = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->command('EHLO ' . $clientName, [250]);
    }

    private function startTls(): void
    {
        $this->command('STARTTLS', [220]);
        $ok = @stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($ok !== true) {
            throw new RuntimeException('STARTTLS negotiation failed');
        }
    }

    private function authenticate(): void
    {
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($this->username), [334]);
        $this->command(base64_encode($this->password), [235]);
    }

    // ── Message building ─────────────────────────────────────────────────────

    private function buildMessage(
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        string $replyTo
    ): string {
        $encodeHeader = fn(string $v): string => '=?UTF-8?B?' . base64_encode($v) . '?=';
        $strip        = fn(string $v): string => str_replace(["\r", "\n"], '', $v);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $strip($fromName) . ' <' . $this->cleanAddress($fromEmail) . '>',
            'To: ' . $strip($toName) . ' <' . $this->cleanAddress($toEmail) . '>',
            'Reply-To: <' . $this->cleanAddress($replyTo ?: $fromEmail) . '>',
            'Subject: ' . $encodeHeader($strip($subject)),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: RotaractKwanza-SmtpMailer',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $htmlBody;
    }

    private function cleanAddress(string $email): string
    {
        // MAIL FROM/RCPT TO take a bare address — strip anything a caller
        // might have accidentally left in "Name <addr>" form.
        if (preg_match('/<([^>]+)>/', $email, $m)) {
            return trim($m[1]);
        }
        return trim($email);
    }

    // ── Wire protocol ────────────────────────────────────────────────────────

    private function command(string $line, array $expectedCodes, bool $expectResponse = true): ?string
    {
        $this->write($line . "\r\n");
        if (!$expectResponse) {
            return null;
        }
        return $this->readResponse($expectedCodes);
    }

    private function write(string $data): void
    {
        if (@fwrite($this->socket, $data) === false) {
            throw new RuntimeException('Failed writing to SMTP socket');
        }
    }

    private function readResponse(array $expectedCodes): string
    {
        $full = '';
        while (($line = fgets($this->socket, 515)) !== false) {
            $full .= $line;
            // A multi-line SMTP reply uses "250-..." for continuation lines
            // and "250 ..." (space, not dash) on the final line.
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $meta = stream_get_meta_data($this->socket);
        if (!empty($meta['timed_out'])) {
            throw new RuntimeException('SMTP connection timed out waiting for a response');
        }
        if ($full === '') {
            throw new RuntimeException('No response from SMTP server');
        }
        $code = (int) substr($full, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException("Unexpected SMTP response: " . trim($full));
        }
        return $full;
    }
}
