<?php require_once "config.php"; ?>
<?php
$login_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='login') {
    $email = trim($_POST['email'] ?? "");
    $pass  = $_POST['password'] ?? "";

    if ($email==="" || $pass==="") {
        $login_error = "Both fields are required.";
    } else {
        $stmt = $mysqli->prepare("SELECT id,name,password_hash,user_number FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->bind_result($uid,$name,$hash,$user_number);
        if ($stmt->fetch()) {
            if (password_verify($pass, $hash)) {
                $_SESSION['user_id'] = $uid;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_number'] = $user_number;
                // JS redirect only
                echo '<!doctype html><html><head><meta charset="utf-8"><script>setTimeout(()=>{window.location.href="chat.php";},250);</script></head><body>Logging in…</body></html>';
                exit;
            } else {
                $login_error = "Invalid credentials.";
            }
        } else {
            $login_error = "Account not found.";
        }
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WhatsApp Clone – Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{--green:#12a150;--green-600:#0f8c45;--green-700:#0c7539;--white:#fff;--ring:#bde5cc;--muted:#4a5b52}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial,Helvetica,sans-serif;background:linear-gradient(135deg,#e9f7ef,#fff);min-height:100vh;display:grid;place-items:center;color:#0a0f0d}
.card{background:#fff;width:min(620px,94vw);border-radius:16px;border:1px solid var(--ring);box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
.header{background:linear-gradient(90deg,var(--green),var(--green-700));color:#fff;padding:22px 24px}
.header h1{margin:0;font-size:20px}
.content{padding:24px}
label{font-size:12px;color:var(--muted);display:block;margin-bottom:6px}
input{width:100%;padding:12px 14px;border:1px solid #dbe7df;border-radius:12px;font-size:15px;outline:none}
input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(18,161,80,.15)}
.btn{width:100%;background:var(--green);color:#fff;border:none;border-radius:12px;padding:12px 16px;font-weight:700;margin-top:10px;cursor:pointer}
.btn:hover{background:var(--green-600)}
.note{margin-top:10px;color:var(--muted);font-size:12px}
.link{color:var(--green-700);text-decoration:none;font-weight:700}
.error{background:#fff5f5;color:#b00020;border:1px solid #ffd7d7;padding:12px 14px;border-radius:12px;margin-top:10px}
.footer{background:#f6fbf8;padding:12px 24px;color:var(--muted);font-size:12px}
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <h1>Welcome back</h1>
      <div style="opacity:.85;font-size:13px">Login to start chatting</div>
    </div>
    <div class="content">
      <form method="post" id="loginForm">
        <input type="hidden" name="action" value="login">
        <label>Email</label>
        <input name="email" type="email" placeholder="you@example.com" required>
        <div style="height:10px"></div>
        <label>Password</label>
        <input name="password" type="password" placeholder="••••••••" required>
        <button class="btn" type="submit" id="btnLogin">Login</button>
      </form>
      <?php if($login_error): ?>
        <div class="error"><?= htmlspecialchars($login_error) ?></div>
      <?php endif; ?>
      <div class="note">New here? <a class="link" href="signup.php">Create an account</a></div>
    </div>
    <div class="footer">&copy; <?= date('Y') ?> WA Clone</div>
  </div>
<script>
document.getElementById('loginForm').addEventListener('submit', ()=>{
  document.getElementById('btnLogin').textContent = 'Checking…';
});
</script>
</body>
</html>
