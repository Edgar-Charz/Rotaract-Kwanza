<?php
/**
 * Turns uncaught exceptions and fatal PHP errors into a friendly page
 * instead of a raw error dump (display_errors is on in this environment,
 * which otherwise leaks file paths / query fragments / stack traces to
 * visitors). The real error always still goes to error_log() so it's not
 * lost, just hidden from the response.
 *
 * Included by session_init.php, so it's active on every public and admin
 * page with no per-page changes needed. Must stay dependency-free (no DB,
 * no session assumptions) since the failure this is catching might be in
 * exactly those layers.
 */

if (!defined('ROTARACT_ERROR_HANDLER_ACTIVE')) {
    define('ROTARACT_ERROR_HANDLER_ACTIVE', true);

    // Buffered so a fatal error partway through rendering can be discarded
    // instead of leaving a half-rendered page followed by an error dump.
    ob_start();

    function rotaract_render_fatal(string $summary): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        // Non-HTML responses (CSV/JSON exports, the search AJAX endpoint)
        // get a minimal same-format-ish message instead of an HTML page,
        // so a fatal mid-export doesn't hand back HTML wrapped in a .csv.
        $contentType = '';
        foreach (headers_list() as $h) {
            if (stripos($h, 'Content-Type:') === 0) $contentType = $h;
        }
        $isHtml = $contentType === '' || stripos($contentType, 'text/html') !== false;

        if (!$isHtml) {
            if (stripos($contentType, 'application/json') !== false) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['error' => 'Server error. Please try again later.']);
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                echo 'Server error. Please try again later.';
            }
            return;
        }

        $isAdmin = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/');
        $accent  = '#C0396B';
        $home    = $isAdmin ? 'index.php' : 'index.php';
        $homeLabel = $isAdmin ? 'Back to Dashboard' : 'Back to Home';
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something Went Wrong</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#fff5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Nunito,sans-serif;color:#2d3436}
  .box{max-width:440px;text-align:center;padding:40px}
  .code{font-size:3.5rem;font-weight:700;color:<?= $accent ?>;line-height:1;font-family:Georgia,'Times New Roman',serif}
  h1{font-size:1.4rem;margin:12px 0 8px}
  p{color:#636e72;font-size:14px;line-height:1.6;margin:0 0 24px}
  a{display:inline-block;padding:11px 26px;background:<?= $accent ?>;color:#fff;border-radius:8px;font-weight:700;font-size:14px;text-decoration:none}
</style>
</head>
<body>
  <div class="box">
    <div class="code">500</div>
    <h1>Something Went Wrong</h1>
    <p>An unexpected error occurred on our end. It's been logged and we'll take a look. Please try again shortly.</p>
    <a href="<?= htmlspecialchars($home, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($homeLabel, ENT_QUOTES, 'UTF-8') ?></a>
  </div>
</body>
</html>
<?php
    }

    set_exception_handler(function (Throwable $e): void {
        error_log('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());
        rotaract_render_fatal($e->getMessage());
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if ($err && in_array($err['type'], $fatalTypes, true)) {
            error_log('Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
            rotaract_render_fatal($err['message']);
        }
    });
}
