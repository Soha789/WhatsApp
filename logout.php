<?php
require_once 'config.php';
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Logging out…</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{ --green:#16a34a; --white:#fff; }
  body{ margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background:#edfff0; color:#0b1f14; }
  .box{ min-height:100vh; display:grid; place-items:center; }
  .card{ background:var(--white); border:2px solid var(--green); padding:24px; border-radius:16px; }
</style>
</head>
<body>
  <div class="box">
    <div class="card">You’ve been logged out. Redirecting to login…</div>
  </div>
<script>
  setTimeout(()=>{ window.location.href='login.php'; }, 800);
</script>
</body>
</html>
