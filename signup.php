<?php require_once "config.php"; ?>
<?php
// Handle signup POST
$signup_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action']==='signup') {
    $name  = trim($_POST['name'] ?? "");
    $email = trim($_POST['email'] ?? "");
    $pass  = $_POST['password'] ?? "";

    if ($name === "" || $email === "" || $pass === "") {
        $signup_error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $signup_error = "Invalid email format.";
    } else {
        // Check email unique
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $signup_error = "Email already registered. Please login.";
        }
        $stmt->close();

        if ($signup_error === "") {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $user_number = random_user_number($mysqli);
            $stmt = $mysqli->prepare("INSERT INTO users (name,email,password_hash,user_number,created_at) VALUES (?,?,?,?,NOW())");
            $stmt->bind_param("ssss", $name, $email, $hash, $user_number);
            if ($stmt->execute()) {
                // Auto-login and JS redirect (no PHP header redirects)
                $_SESSION['user_id'] = $stmt->insert_id;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_number'] = $user_number;
                echo '<!doctype html><html><head><meta charset="utf-8"><script>setTimeout(()=>{window.location.href="chat.php";},400);</script></head><body>Signing you in…</body></html>';
                exit;
            } else {
                $signup_error = "Signup failed: " . $mysqli->error;
            }
            $stmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WhatsApp Clone – Signup</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root {
  --green: #12a150;
  --green-600:#0f8c45;
  --green-700:#0c7539;
  --green-100:#e9f7ef;
  --white: #ffffff;
  --text: #0a0f0d;
  --muted:#4a5b52;
  --ring: #bde5cc;
}
*{box-sizing:border-box}
body{
  margin:0; font-family:system-ui,Segoe UI,Roboto,Arial,Helvetica,sans-serif;
  background:linear-gradient(135deg,var(--green-100),var(--white));
  color:var(--text);
  min-height:100vh; display:grid; place-items:center;
}
.card{
  background:var(--white); width:min(720px,94vw); border-radius:16px;
  box-shadow:0 10px 30px rgba(0,0,0,.08); overflow:hidden; border:1px solid var(--ring);
}
.header{
  background:linear-gradient(90deg,var(--green),var(--green-700));
  color:var(--white); padding:22px 24px; display:flex; align-items:center; gap:12px;
}
.header .logo{
  width:42px; height:42px; border-radius:12px; background:var(--white);
  display:grid;place-items:center;color:var(--green-700); font-weight:800;
}
.header h1{font-size:20px; margin:0}
.content{padding:26px}
.grid{display:grid; grid-template-columns:1fr 1fr; gap:16px}
label{font-size:12px; color:var(--muted); display:block; margin-bottom:6px}
input{
  width:100%; padding:12px 14px; border-radius:12px; border:1px solid #dbe7df;
  outline:none; transition:box-shadow .2s,border-color .2s; font-size:15px;
}
input:focus{border-color:var(--green); box-shadow:0 0 0 4px rgba(18,161,80,.15)}
.btn{
  background:var(--green); color:var(--white); padding:12px 16px; border:none; border-radius:12px;
  font-weight:600; cursor:pointer; transition:transform .06s ease, background .2s; width:100%;
}
.btn:hover{background:var(--green-600)}
.btn:active{transform:translateY(1px)}
.row{display:flex; gap:14px; align-items:center}
.error{
  background:#fff5f5; color:#b00020; border:1px solid #ffd7d7; padding:12px 14px; border-radius:12px; margin:10px 0 0;
}
.note{font-size:12px; color:var(--muted); margin-top:10px}
.link{color:var(--green-700); text-decoration:none; font-weight:600}
.footer{
  background:#f6fbf8; padding:14px 24px; display:flex; justify-content:space-between; color:var(--muted); font-size:12px
}
@media (max-width:720px){ .grid{grid-template-columns:1fr} }
</style>
</head>
<body>
  <div class="card">
    <div class="header">
      <div class="logo">WA</div>
      <div>
        <h1>Create your account</h1>
        <div style="opacity:.9;font-size:13px">Green & white theme • No external files • JS redirects</div>
      </div>
    </div>
    <div class="content">
      <form method="post" id="signupForm">
        <input type="hidden" name="action" value="signup">
        <div class="grid">
          <div>
            <label>Full name</label>
            <input name="name" placeholder="e.g., Soha Khan" required>
          </div>
          <div>
            <label>Email</label>
            <input name="email" type="email" placeholder="you@example.com" required>
          </div>
        </div>
        <div class="grid" style="margin-top:10px">
          <div>
            <label>Password</label>
            <input name="password" type="password" placeholder="••••••••" required>
          </div>
          <div class="row" style="align-items:flex-end">
            <button class="btn" type="submit" id="btnSignup">Create account</button>
          </div>
        </div>
      </form>
      <?php if($signup_error): ?>
        <div class="error"><?= htmlspecialchars($signup_error) ?></div>
      <?php endif; ?>
      <div class="note">Already have an account?
        <a class="link" href="login.php">Login</a>
      </div>
    </div>
    <div class="footer">
      <div>Tip: Each user gets a unique <b>user number</b> automatically at signup.</div>
      <div>&copy; <?= date('Y') ?> WA Clone</div>
    </div>
  </div>
<script>
// Optional: intercept submit to give quick feedback.
document.getElementById('signupForm').addEventListener('submit', () => {
  document.getElementById('btnSignup').textContent = 'Creating...';
});
</script>
</body>
</html>
