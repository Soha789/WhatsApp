<?php
require_once 'config.php';

// Handle AJAX signup POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'signup') {
    header('Content-Type: application/json');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        echo json_encode(['ok' => false, 'error' => 'Username and password are required.']);
        exit;
    }

    try {
        $pdo = db();
        // unique username
        $exists = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $exists->execute([$username]);
        if ($exists->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Username already taken.']);
            exit;
        }

        // generate unique random 10-digit phone-like number (not starting with 0)
        do {
            $phone = (string)random_int(1000000000, 9999999999);
            $chk = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
            $chk->execute([$phone]);
        } while ($chk->fetch());

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, phone_number, created_at, last_seen) VALUES (?, ?, ?, NOW(), NOW())");
        $stmt->execute([$username, $hash, $phone]);

        echo json_encode(['ok' => true, 'phone_number' => $phone, 'message' => 'Signup successful.']);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => 'Server error: '.$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Sign Up — GreenChat</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root { --green:#16a34a; --green-d:#15803d; --green-ll:#eaffe6; --white:#fff; --ink:#0b1f14; }
  * { box-sizing: border-box; }
  body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; background: var(--green-ll); color: var(--ink); }
  .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
  .card {
    width: 100%; max-width: 460px; background: var(--white); border: 2px solid var(--green);
    border-radius: 16px; box-shadow: 0 10px 30px rgba(22,163,74,0.15); padding: 24px;
  }
  h1 { margin: 0 0 12px; color: var(--green-d); }
  p.sub { margin: 0 0 24px; color:#173b20 }
  label { display:block; font-weight:600; margin: 12px 0 6px; }
  input[type="text"], input[type="password"] {
    width:100%; padding:12px 14px; border-radius:12px; border:2px solid var(--green);
    background:#f8fff8; outline:none; transition:.2s border;
  }
  input:focus { border-color: var(--green-d); }
  button {
    margin-top:16px; width:100%; padding:12px 14px; border-radius:12px; border:0;
    background: var(--green); color:var(--white); font-weight:700; cursor:pointer; transition:.2s transform, .2s background;
  }
  button:hover { background: var(--green-d); transform: translateY(-1px); }
  .note { margin-top:12px; font-size:14px; }
  .msg { margin-top:16px; padding:12px; border-radius:12px; background:#ecffef; border:1px solid var(--green); display:none; }
  a { color: var(--green-d); font-weight:700; text-decoration: none; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Create Account</h1>
    <p class="sub">Green & white WhatsApp-style clone • Your number is assigned automatically.</p>
    <form id="signupForm">
      <label>Username</label>
      <input type="text" name="username" placeholder="e.g., soha11" required />
      <label>Password</label>
      <input type="password" name="password" placeholder="Choose a strong password" required />
      <button type="submit">Sign Up</button>
      <div class="msg" id="msg"></div>
      <p class="note">Already have an account? <a href="login.php">Log in</a></p>
    </form>
  </div>
</div>
<script>
// JS: AJAX submit; JS-only redirect (no PHP header)
const form = document.getElementById('signupForm');
const msg = document.getElementById('msg');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData(form);
  const res = await fetch('signup.php?action=signup', { method:'POST', body: fd });
  const data = await res.json();
  msg.style.display = 'block';
  if (data.ok) {
    msg.textContent = `✅ ${data.message} • Your number: ${data.phone_number}. Redirecting to login...`;
    setTimeout(() => { window.location.href = 'login.php'; }, 1300); // JS redirect
  } else {
    msg.textContent = `❌ ${data.error || 'Failed'}`;
  }
});
</script>
</body>
</html>
