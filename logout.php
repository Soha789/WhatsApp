<?php require_once "config.php"; ?>
<?php
// Destroy session and JS-redirect to login (no PHP header)
$_SESSION = [];
if (session_id() !== "" || isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}
session_destroy();
?>
<!doctype html>
<html><head><meta charset="utf-8"><title>Logging out…</title>
<script>setTimeout(()=>{window.location.href="login.php";},300);</script>
<style>
body{font-family:system-ui,Segoe UI,Roboto,Arial,Helvetica,sans-serif;display:grid;place-items:center;min-height:100vh;background:linear-gradient(135deg,#e9f7ef,#fff);color:#0a0f0d}
.box{background:#fff;border:1px solid #bde5cc;border-radius:12px;padding:18px 20px}
</style>
</head><body><div class="box">Logging you out…</div></body></html>
