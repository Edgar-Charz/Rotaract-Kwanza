<?php
$_toasts = [];

if (!empty($_SESSION['flash_message'])) {
    $_toasts[] = ['type' => 'success', 'text' => $_SESSION['flash_message']];
    unset($_SESSION['flash_message']);
}
if (!empty($_SESSION['flash_error'])) {
    $_toasts[] = ['type' => 'danger', 'text' => $_SESSION['flash_error']];
    unset($_SESSION['flash_error']);
}

if (!empty($message ?? '')) {
    $already = array_filter($_toasts, fn($t) => $t['text'] === $message);
    if (empty($already)) {
        $_toasts[] = ['type' => 'success', 'text' => $message];
    }
}
if (!empty($error ?? '')) {
    $already = array_filter($_toasts, fn($t) => $t['text'] === $error);
    if (empty($already)) {
        $_toasts[] = ['type' => 'danger', 'text' => $error];
    }
}

if (!empty($_toasts)):
?>
<div class="pub-toast-stack" id="pubToastStack">
    <?php foreach ($_toasts as $_t):
        $icon = $_t['type'] === 'success' ? '✓' : '✕';
    ?>
    <div class="pub-toast pub-toast-<?= $_t['type'] ?>">
        <span class="pub-toast-icon"><?= $icon ?></span>
        <?= htmlspecialchars($_t['text'], ENT_QUOTES, 'UTF-8') ?>
        <button onclick="this.parentElement.remove()" class="pub-toast-close">&times;</button>
    </div>
    <?php endforeach; ?>
</div>
<style>
.pub-toast-stack { position:fixed;top:80px;left:50%;transform:translateX(-50%);z-index:9999;width:min(600px,92vw);display:flex;flex-direction:column;gap:10px; }
.pub-toast { display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:10px;font-size:14px;font-weight:600;box-shadow:0 4px 16px rgba(0,0,0,0.12);animation:fadeInDown .3s ease; }
.pub-toast-success { background:#d1f2e0;color:#1a5c35;border:1px solid #a3dfc0; }
.pub-toast-danger  { background:#fde8e8;color:#9b2335;border:1px solid #f5b8be; }
.pub-toast-icon { font-size:16px;flex-shrink:0; }
.pub-toast-close { margin-left:auto;background:none;border:none;cursor:pointer;font-size:18px;opacity:.6;line-height:1; }
.pub-toast-close:hover { opacity:1; }
@keyframes fadeInDown { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }
</style>
<script>
setTimeout(function(){
    var stack = document.getElementById('pubToastStack');
    if(stack){ stack.style.transition='opacity .4s'; stack.style.opacity='0'; setTimeout(function(){ if(stack) stack.remove(); },400); }
}, 5000);
</script>
<?php endif; ?>
