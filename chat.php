<?php
require_once 'config.php';

// ---------- JSON API ACTIONS (same file) ----------
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // Must be logged in for chat actions
    $public_actions = []; // none
    if (!in_array($_GET['action'], $public_actions, true)) {
        require_login_json();
    }

    $pdo = db();

    if ($_GET['action'] === 'me') {
        echo json_encode(['ok'=>true,'me'=>current_user()]);
        exit;
    }

    if ($_GET['action'] === 'users') {
        // all users except me
        $stmt = $pdo->prepare("SELECT id, username, phone_number, last_seen FROM users WHERE id <> ? ORDER BY username ASC");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode(['ok'=>true,'users'=>$stmt->fetchAll()]);
        exit;
    }

    if ($_GET['action'] === 'fetch' ) {
        // fetch messages for a conversation
        $other_id = (int)($_GET['other_id'] ?? 0);
        if ($other_id <= 0) { echo json_encode(['ok'=>false,'error'=>'Invalid other_id']); exit; }

        $stmt = $pdo->prepare("
            SELECT id, sender_id, receiver_id, content, status, created_at
            FROM messages
            WHERE (sender_id = :me AND receiver_id = :other)
               OR (sender_id = :other AND receiver_id = :me)
            ORDER BY id ASC
        ");
        $stmt->execute([':me'=>$_SESSION['user_id'], ':other'=>$other_id]);
        $rows = $stmt->fetchAll();

        // mark as read for messages sent by other to me
        $pdo->prepare("
          UPDATE messages SET status='read'
          WHERE receiver_id=? AND sender_id=? AND status <> 'read'
        ")->execute([$_SESSION['user_id'], $other_id]);

        echo json_encode(['ok'=>true,'messages'=>$rows]);
        exit;
    }

    if ($_GET['action'] === 'send') {
        // send message to other_id
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['ok'=>false,'error'=>'POST only']); exit; }
        $other_id = (int)($_POST['other_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        if ($other_id<=0 || $content==='') { echo json_encode(['ok'=>false,'error'=>'Missing fields']); exit; }

        // ensure other exists
        $chk = $pdo->prepare("SELECT id FROM users WHERE id=?");
        $chk->execute([$other_id]);
        if (!$chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'User not found']); exit; }

        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content, status, created_at) VALUES (?, ?, ?, 'sent', NOW())");
        $stmt->execute([$_SESSION['user_id'], $other_id, $content]);

        // simulate immediate 'delivered' (server accepted)
        $pdo->prepare("UPDATE messages SET status='delivered' WHERE id=?")->execute([$pdo->lastInsertId()]);

        echo json_encode(['ok'=>true,'message'=>'sent']);
        exit;
    }

    if ($_GET['action'] === 'touch') {
        // update last_seen heartbeat
        $pdo->prepare("UPDATE users SET last_seen = NOW() WHERE id=?")->execute([$_SESSION['user_id']]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Unknown action']);
    exit;
}

// ---------- UI (HTML + CSS + JS) ----------
if (!isset($_SESSION['user_id'])) {
    // JS redirect (no PHP header), still render a minimal page:
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><script>window.location.href="login.php";</script></head><body>Redirecting...</body></html>';
    exit;
}
$me = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>GreenChat — WhatsApp Clone</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --green:#16a34a; --green-d:#15803d; --green-dd:#0e7a2f; --white:#fff; --bg:#f3fff5; --ink:#0b1f14; --muted:#d9f7df;
  }
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:var(--bg);color:var(--ink);}
  .app{display:grid; grid-template-columns: 320px 1fr; height:100vh;}
  .sidebar{
    border-right: 2px solid var(--green); background: var(--white);
    display:flex; flex-direction:column;
  }
  .me{
    padding:14px; border-bottom:2px solid var(--green); background: linear-gradient(0deg, var(--white), #f7fff9);
  }
  .me .title{font-weight:800; color:var(--green-d)}
  .me .phone{font-size:13px; color:#22663a}
  .users{overflow:auto; }
  .user{
    padding:12px 14px; border-bottom:1px solid #ebffef; cursor:pointer; display:flex; align-items:center; gap:10px;
  }
  .user:hover{ background:#f4fff6; }
  .bubble{
    width:34px; height:34px; border-radius:50%; background:var(--muted); display:grid; place-items:center; font-weight:800; color:var(--green-dd);
  }
  .username{ font-weight:700; color:#0d4123; }
  .phone{ font-size:12px; color:#1a6f36; }

  .chat{
    display:grid; grid-template-rows: auto 1fr auto; height:100vh;
  }
  .chat-head{
    display:flex; align-items:center; gap:10px; padding:12px 16px; border-bottom:2px solid var(--green); background: var(--white);
  }
  .chat-title{ font-weight:800; color:var(--green-dd); }
  .chat-area{
    padding: 18px; overflow:auto; background-image: radial-gradient(#e7ffe8 1px, transparent 1px);
    background-size: 12px 12px;
  }
  .msg-row{ display:flex; margin:6px 0; }
  .msg-row.me{ justify-content: flex-end; }
  .msg{
    max-width:64ch; padding:10px 12px; border-radius:14px; position:relative; background:#eaffec; border:1px solid #baf1c2;
  }
  .msg.me{ background:#c7ffd1; border-color:#86f09a; }
  .time{ font-size:11px; color:#276f3b; margin-top:4px; text-align:right; }
  .status{ font-size:11px; margin-left:6px; opacity:.8; }
  .composer{
    display:flex; gap:10px; padding:12px; border-top:2px solid var(--green); background: var(--white);
  }
  .input{
    flex:1; padding:12px 14px; border-radius:12px; border:2px solid var(--green); background:#f8fff8; outline:none;
  }
  .send{
    padding:12px 16px; border-radius:12px; border:0; background:var(--green); color:var(--white); font-weight:800; cursor:pointer;
  }
  .top-actions{ margin-left:auto; display:flex; gap:8px; }
  .btn{
    padding:8px 10px; border-radius:10px; border:2px solid var(--green); background:#f6fff6; cursor:pointer; font-weight:700; color:#135c2b;
  }
  .btn:hover{ background:#eaffea; }
  .empty{
    display:grid; place-items:center; color:#20693a; font-weight:700; height:100%;
  }
  @media (max-width:900px){
    .app{ grid-template-columns: 1fr; }
    .sidebar{ position:fixed; inset:0; transform: translateX(-100%); transition:.25s transform; z-index:10; }
    .sidebar.open{ transform: translateX(0); }
    .chat-head .btn{ display:inline-block; }
  }
</style>
</head>
<body>
<div class="app">
  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="me">
      <div class="title">You</div>
      <div class="you-username"></div>
      <div class="phone"></div>
    </div>
    <div class="users" id="users"></div>
  </aside>

  <!-- CHAT -->
  <main class="chat">
    <div class="chat-head">
      <button class="btn" id="toggleSidebar" style="display:none">☰</button>
      <div class="bubble" id="headBubble">?</div>
      <div>
        <div class="chat-title" id="chatTitle">Select a contact</div>
        <div class="phone" id="chatPhone"></div>
      </div>
      <div class="top-actions">
        <button class="btn" onclick="window.location.href='logout.php'">Logout</button>
      </div>
    </div>

    <div class="chat-area" id="chatArea">
      <div class="empty">Choose a user from the left to start chatting.</div>
    </div>

    <div class="composer">
      <input class="input" id="composerInput" placeholder="Type a message and press Enter…" />
      <button class="send" id="sendBtn">Send</button>
    </div>
  </main>
</div>

<script>
let me = null;
let activeUser = null;
let pollTimer = null;

const usersEl = document.getElementById('users');
const chatArea = document.getElementById('chatArea');
const chatTitle = document.getElementById('chatTitle');
const chatPhone = document.getElementById('chatPhone');
const headBubble = document.getElementById('headBubble');
const input = document.getElementById('composerInput');

const sidebar = document.getElementById('sidebar');
const toggleSidebar = document.getElementById('toggleSidebar');

function initials(name){ return (name||'?').substring(0,1).toUpperCase(); }

async function api(path, opts={}){
  const res = await fetch('chat.php?action='+path, opts);
  return res.json();
}

async function boot(){
  const meRes = await api('me');
  if(!meRes.ok){ window.location.href='login.php'; return; }
  me = meRes.me;
  document.querySelector('.you-username').textContent = me.username;
  document.querySelector('.me .phone').textContent = 'Your number: '+me.phone_number;

  loadUsers();
  heartbeat();
  setInterval(heartbeat, 15000);

  // mobile sidebar
  if (window.matchMedia('(max-width: 900px)').matches) {
    toggleSidebar.style.display = 'inline-block';
    toggleSidebar.addEventListener('click', ()=> sidebar.classList.toggle('open'));
  }
}

async function loadUsers(){
  const res = await api('users');
  if(!res.ok) return;
  usersEl.innerHTML = '';
  res.users.forEach(u=>{
    const li = document.createElement('div');
    li.className = 'user';
    li.innerHTML = `
      <div class="bubble">${initials(u.username)}</div>
      <div>
        <div class="username">${u.username}</div>
        <div class="phone">${u.phone_number}</div>
      </div>
    `;
    li.addEventListener('click', ()=> openChat(u));
    usersEl.appendChild(li);
  });
}

function scrollBottom(){ chatArea.scrollTop = chatArea.scrollHeight; }

function renderMessages(msgs){
  chatArea.innerHTML = '';
  msgs.forEach(m=>{
    const row = document.createElement('div');
    const mine = (m.sender_id === me.id);
    row.className = 'msg-row'+(mine?' me':'');
    const bubble = document.createElement('div');
    bubble.className = 'msg'+(mine?' me':'');
    const time = new Date(m.created_at.replace(' ', 'T'));
    const hh = time.getHours().toString().padStart(2,'0');
    const mm = time.getMinutes().toString().padStart(2,'0');
    bubble.innerHTML = `
      <div>${escapeHtml(m.content)}</div>
      <div class="time">${hh}:${mm}
        ${mine ? `<span class="status">• ${m.status}</span>` : ``}
      </div>
    `;
    row.appendChild(bubble);
    chatArea.appendChild(row);
  });
  scrollBottom();
}

function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }

async function openChat(user){
  activeUser = user;
  chatTitle.textContent = user.username;
  chatPhone.textContent = 'Number: '+user.phone_number;
  headBubble.textContent = initials(user.username);
  if (window.matchMedia('(max-width: 900px)').matches) sidebar.classList.remove('open');
  if (pollTimer) clearInterval(pollTimer);
  await fetchAndRender();
  pollTimer = setInterval(fetchAndRender, 1200);
}

async function fetchAndRender(){
  if(!activeUser) return;
  const res = await api('fetch&other_id='+encodeURIComponent(activeUser.id));
  if(!res.ok) return;
  renderMessages(res.messages);
}

async function sendNow(){
  const text = input.value.trim();
  if(!text || !activeUser) return;
  const fd = new FormData();
  fd.append('other_id', activeUser.id);
  fd.append('content', text);
  const res = await api('send', { method:'POST', body: fd });
  if (res.ok){
    input.value='';
    fetchAndRender();
  }
}

input.addEventListener('keydown', (e)=>{
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendNow();
  }
});
document.getElementById('sendBtn').addEventListener('click', sendNow);

async function heartbeat(){ await api('touch'); }

boot();
</script>
</body>
</html>
