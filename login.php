<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    header('Content-Type: application/json');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        echo json_encode(['ok'=>false,'error'=>'Username and password are required.']); exit;
    }
    try {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, $row['password_hash'])) {
            echo json_encode(['ok'=>false,'error'=>'Invalid credentials.']); exit;
        }
        $_SESSION['user_id'] = (int)$row['id'];
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id=?")->execute([$_SESSION['user_id']]);
        echo json_encode(['ok'=>true,'message'=>'Login successful.']);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'Server error: '.$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Login — GreenChat</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root { --green:#16a34a; --green-d:#15803d; --white:#fff; --bg:#f2fff5; --ink:#0b1f14; }
  body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background: var(--bg); color: var(--ink); }
  .wrap { min-height: 100vh; display:grid; place-items:center; padding:24px; }
  .card { width:100%; max-width:460px; background:var(--white); border:2px solid var(--green); border-radius:16px; padding:24px; box-shadow: 0 10px 30px rgba(22,163,74,0.15); }
  h1 { margin:0 0 12px; color:var(--green-d); }
  p.sub { margin:0 0 24px; color:#173b20; }
  label { display:block; font-weight:600; margin:12px 0 6px; }
  input[type="text"], input[type="password"] {
    width:100%; padding:12px 14px; border-radius:12px; border:2px solid var(--green);
    background:#f8fff8; outline:none;
  }
  button {
    margin-top:16px; width:100%; padding:12px 14px; border-radius:12px; border:0;
    background: var(--green); color:var(--white); font-weight:700; cursor:pointer;
  }
  .msg { margin-top:16px; padding:12px; border-radius:12px; background:#ecffef; border:1px solid var(--green); display:none; }
  a { color: var(--green-d); font-weight:700; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Welcome back</h1>
    <p class="sub">Log in to start chatting in real time.</p>
    <form id="loginForm">
      <label>Username</label>
      <input type="text" name="username" placeholder="Your username" required />
      <label>Password</label>
      <input type="password" name="password" placeholder="Your password" required />
      <button type="submit">Log In</button>
      <div class="msg" id="msg"></div>
      <p class="sub" style="margin-top:10px;">New here? <a href="signup.php">Create an account</a></p>
    </form>
  </div>
</div>
<script>
const form = document.getElementById('loginForm');
const msg = document.getElementById('msg');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  const res = await fetch('login.php?action=login', { method:'POST', body: fd });
  const data = await res.json();
  msg.style.display = 'block';
  if (data.ok) {
    msg.textContent = '✅ ' + data.message + ' Redirecting...';
    setTimeout(()=>{ window.location.href = 'chat.php'; }, 800); // JS redirect
  } else {
    msg.textContent = '❌ ' + (data.error || 'Login failed');
  }
});
</script>
</body>
</html>
