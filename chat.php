<?php require_once "config.php"; require_login(); ?>
<?php
// chat.php also serves AJAX endpoints (send, fetch, mark_read, pick_by_number, me)
header_remove('X-Powered-By');

$current_id = $_SESSION['user_id'];
$current_name = $_SESSION['user_name'];
$current_number = $_SESSION['user_number'];

// AJAX router (POST only)
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax'])) {
    $action = $_POST['ajax'];

    if ($action === 'me') {
        json_response([
            "id"=>$current_id,
            "name"=>$current_name,
            "number"=>$current_number
        ]);
    }

    if ($action === 'list_users') {
        $q = $_POST['q'] ?? "";
        if ($q !== "") {
            $like = "%".$q."%";
            $stmt = $mysqli->prepare("SELECT id,name,user_number FROM users WHERE id<>? AND (name LIKE ? OR user_number LIKE ?) ORDER BY name ASC LIMIT 50");
            $stmt->bind_param("iss", $current_id, $like, $like);
        } else {
            $stmt = $mysqli->prepare("SELECT id,name,user_number FROM users WHERE id<>? ORDER BY name ASC LIMIT 50");
            $stmt->bind_param("i", $current_id);
        }
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        json_response(["users"=>$res]);
    }

    if ($action === 'pick_by_number') { // select chat partner using their number
        $num = trim($_POST['number'] ?? "");
        $stmt = $mysqli->prepare("SELECT id,name,user_number FROM users WHERE user_number=? AND id<>? LIMIT 1");
        $stmt->bind_param("si", $num, $current_id);
        $stmt->execute();
        $u = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$u) json_response(["error"=>"No user found with that number."], 404);
        json_response(["user"=>$u]);
    }

    if ($action === 'send') {
        $to_id = intval($_POST['to_id'] ?? 0);
        $body  = trim($_POST['body'] ?? "");
        if ($to_id<=0 || $body==="") json_response(["error"=>"Missing fields"], 422);

        // confirm receiver exists
        $stmt = $mysqli->prepare("SELECT id FROM users WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $to_id); $stmt->execute(); $stmt->store_result();
        if ($stmt->num_rows === 0) { $stmt->close(); json_response(["error"=>"User not found"], 404); }
        $stmt->close();

        $status = 'sent';
        $stmt = $mysqli->prepare("INSERT INTO messages (sender_id,receiver_id,body,status,sent_at) VALUES (?,?,?,?,NOW())");
        $stmt->bind_param("iiss", $current_id, $to_id, $body, $status);
        $ok = $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();

        if ($ok) {
            json_response(["ok"=>true,"message_id"=>$id,"sent_at"=>date('Y-m-d H:i:s'),"status"=>$status]);
        } else {
            json_response(["error"=>"Failed to send."], 500);
        }
    }

    if ($action === 'fetch') {
        $with_id = intval($_POST['with_id'] ?? 0);
        $last_id = intval($_POST['last_id'] ?? 0);

        if ($with_id<=0) json_response(["error"=>"Missing with_id"], 422);

        // Mark any 'sent' to me from with_id as delivered
        $stmt = $mysqli->prepare("UPDATE messages SET status='delivered' WHERE receiver_id=? AND sender_id=? AND status='sent'");
        $stmt->bind_param("ii", $current_id, $with_id);
        $stmt->execute(); $stmt->close();

        if ($last_id > 0) {
            $stmt = $mysqli->prepare("SELECT id,sender_id,receiver_id,body,status,sent_at FROM messages 
                WHERE ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?)) 
                AND id>? ORDER BY id ASC LIMIT 200");
            $stmt->bind_param("iiiii", $current_id, $with_id, $with_id, $current_id, $last_id);
        } else {
            $stmt = $mysqli->prepare("SELECT id,sender_id,receiver_id,body,status,sent_at FROM messages 
                WHERE (sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?) 
                ORDER BY id ASC LIMIT 200");
            $stmt->bind_param("iiii", $current_id, $with_id, $with_id, $current_id);
        }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        json_response(["messages"=>$rows]);
    }

    if ($action === 'mark_read') {
        $with_id = intval($_POST['with_id'] ?? 0);
        if ($with_id<=0) json_response(["error"=>"Missing with_id"], 422);
        $stmt = $mysqli->prepare("UPDATE messages SET status='read' WHERE receiver_id=? AND sender_id=? AND status IN ('sent','delivered')");
        $stmt->bind_param("ii", $current_id, $with_id);
        $stmt->execute(); $stmt->close();
        json_response(["ok"=>true]);
    }

    json_response(["error"=>"Unknown action"], 400);
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>WhatsApp Clone – Chat</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
:root{
  --green:#12a150; --green-50:#f3fbf6; --green-100:#e9f7ef; --green-600:#0f8c45; --green-700:#0c7539;
  --white:#ffffff; --muted:#4a5b52; --ring:#bde5cc; --bubble:#dff4e8; --bubble-me:#bff0d3;
}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,Segoe UI,Roboto,Arial,Helvetica,sans-serif;background:linear-gradient(135deg,var(--green-100),var(--white));color:#0a0f0d;height:100vh}
.app{display:grid;grid-template-columns:300px 1fr;height:100vh;max-height:100vh}
.sidebar{
  border-right:1px solid var(--ring); background:var(--white); display:flex; flex-direction:column; min-width:0;
}
.sidebar .top{
  background:linear-gradient(90deg,var(--green),var(--green-700));color:#fff;padding:14px 16px;display:flex;justify-content:space-between;align-items:center
}
.brand{font-weight:800}
.me{font-size:12px;opacity:.95}
.search{padding:10px;border-bottom:1px solid var(--ring);background:var(--green-50)}
.search input{width:100%;padding:10px 12px;border-radius:12px;border:1px solid #dbe7df;outline:none}
.search input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(18,161,80,.15)}
.userlist{overflow:auto}
.user{display:flex;gap:10px;align-items:center;padding:12px 14px;border-bottom:1px solid #eef5ef;cursor:pointer}
.user:hover{background:#f6fbf8}
.avatar{width:36px;height:36px;border-radius:10px;background:var(--green);color:#fff;display:grid;place-items:center;font-weight:800}
.meta{display:flex;flex-direction:column}
.meta .name{font-weight:700}
.meta .num{font-size:12px;color:var(--muted)}
.logout{color:#fff;background:rgba(255,255,255,.18);padding:8px 10px;border-radius:8px;text-decoration:none;font-weight:700}

.main{display:grid;grid-template-rows:auto 1fr auto; height:100vh}
.header{
  background:#fff; border-bottom:1px solid var(--ring); padding:12px 16px; display:flex; align-items:center; gap:10px;
}
.header .title{font-weight:800}
.header .small{font-size:12px; color:var(--muted)}
.messages{background:linear-gradient(180deg,var(--green-50),#fff); padding:16px; overflow:auto}
.msg{max-width:72%; margin:8px 0; padding:10px 12px; border-radius:14px; position:relative; box-shadow:0 1px 0 rgba(0,0,0,.03)}
.msg .time{font-size:11px; color:#3b4a43; opacity:.85; margin-top:6px; text-align:right}
.msg .status{font-size:11px; margin-left:6px}
.from-me{margin-left:auto; background:var(--bubble-me)}
.from-them{margin-right:auto; background:var(--bubble)}
.composer{display:flex; gap:10px; padding:12px; background:#fff; border-top:1px solid var(--ring)}
.composer input{flex:1;padding:12px 14px;border-radius:12px;border:1px solid #dbe7df;outline:none}
.composer input:focus{border-color:var(--green);box-shadow:0 0 0 4px rgba(18,161,80,.12)}
.composer button{background:var(--green);color:#fff;border:none;border-radius:12px;padding:12px 16px;font-weight:800;cursor:pointer}
.info{padding:8px 12px;font-size:12px;color:var(--muted);background:#f6fbf8;border-bottom:1px solid var(--ring)}
.helper{display:flex;gap:8px;align-items:center}
.helper input{width:150px;padding:8px;border:1px solid #dbe7df;border-radius:10px}
.helper button{background:var(--green-600);color:#fff;border:none;border-radius:10px;padding:8px 10px;cursor:pointer}
@media (max-width:900px){ .app{grid-template-columns:1fr} .sidebar{display:none} }
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="top">
      <div>
        <div class="brand">WA Clone</div>
        <div class="me">You: <b><?= htmlspecialchars($current_name) ?></b> • #<?= htmlspecialchars($current_number) ?></div>
      </div>
      <a class="logout" href="logout.php">Logout</a>
    </div>
    <div class="search">
      <input id="search" placeholder="Search users or number…">
    </div>
    <div class="userlist" id="userlist"></div>
  </aside>

  <main class="main">
    <div class="header">
      <div class="avatar" id="chatAvatar">?</div>
      <div>
        <div class="title" id="chatWith">Select a user</div>
        <div class="small" id="chatWithNumber">—</div>
      </div>
    </div>
    <div class="info">
      <div class="helper">
        <span>Quick jump by number:</span>
        <input id="jumpNum" placeholder="Enter user number">
        <button onclick="pickByNumber()">Open</button>
      </div>
    </div>
    <div class="messages" id="messages"></div>
    <div class="composer">
      <input id="inputMsg" placeholder="Type a message and press Enter…" disabled>
      <button id="btnSend" disabled onclick="sendMsg()">Send</button>
    </div>
  </main>
</div>

<script>
// Minimal client state
let me = {id:<?= (int)$current_id ?>, name:<?= json_encode($current_name) ?>, number:<?= json_encode($current_number) ?>};
let withUser = null;     // {id, name, user_number}
let lastId = 0;
let pollTimer = null;

function el(id){ return document.getElementById(id); }

function avatarLetters(name){ return (name||'?').trim().split(/\s+/).map(s=>s[0]).join('').slice(0,2).toUpperCase(); }

function api(data){
  return fetch('chat.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams(Object.assign({ajax:'__'}, data)).toString().replace('ajax=%5F%5F','ajax='+encodeURIComponent(data.ajax))
  }).then(r=>r.json());
}

function listUsers(q=''){
  api({ajax:'list_users', q}).then(({users})=>{
    const ul = el('userlist');
    ul.innerHTML = '';
    users.forEach(u=>{
      const div = document.createElement('div');
      div.className = 'user';
      div.onclick = ()=>openChat(u);
      div.innerHTML = `
        <div class="avatar">${avatarLetters(u.name)}</div>
        <div class="meta">
          <div class="name">${escapeHtml(u.name)}</div>
          <div class="num">#${u.user_number}</div>
        </div>`;
      ul.appendChild(div);
    });
  });
}

function openChat(u){
  withUser = u;
  lastId = 0;
  el('chatWith').textContent = u.name;
  el('chatWithNumber').textContent = '#'+u.user_number;
  el('chatAvatar').textContent = avatarLetters(u.name);
  el('inputMsg').disabled = false;
  el('btnSend').disabled = false;
  el('inputMsg').focus();
  el('messages').innerHTML = '';
  // Start polling
  if (pollTimer) clearInterval(pollTimer);
  fetchMessages(); // first fetch
  pollTimer = setInterval(fetchMessages, 1500);
}

function fetchMessages(){
  if (!withUser) return;
  api({ajax:'fetch', with_id: withUser.id, last_id:lastId}).then(({messages})=>{
    if (Array.isArray(messages) && messages.length){
      messages.forEach(m=>{
        addMsg(m);
        lastId = Math.max(lastId, m.id);
      });
      scrollToBottom();
      // After render, mark read (for messages to me)
      api({ajax:'mark_read', with_id: withUser.id});
    }
  });
}

function addMsg(m){
  const wrap = document.createElement('div');
  const mine = (m.sender_id == me.id);
  wrap.className = 'msg ' + (mine ? 'from-me' : 'from-them');
  let status = '';
  if (mine){
     status = `<span class="status">${m.status==='read'?'✓✓ read':(m.status==='delivered'?'✓✓ delivered':'✓ sent')}</span>`;
  }
  wrap.innerHTML = `
    <div>${escapeHtml(m.body)}</div>
    <div class="time">${formatTime(m.sent_at)} ${status}</div>
  `;
  el('messages').appendChild(wrap);
}

function sendMsg(){
  if (!withUser) return;
  const val = el('inputMsg').value.trim();
  if (!val) return;
  el('inputMsg').value = '';
  api({ajax:'send', to_id: withUser.id, body: val}).then(res=>{
    if (res.ok){
      // Optimistic add
      addMsg({id:res.message_id, sender_id:me.id, receiver_id:withUser.id, body:val, status:res.status, sent_at:res.sent_at});
      scrollToBottom();
    }
  });
}

function pickByNumber(){
  const num = el('jumpNum').value.trim();
  if (!num) return;
  api({ajax:'pick_by_number', number:num}).then((res)=>{
    if (res.user){ openChat(res.user); }
    else { alert(res.error || 'Not found'); }
  }).catch(()=>alert('Not found'));
}

function scrollToBottom(){ const c=el('messages'); c.scrollTop=c.scrollHeight; }

function escapeHtml(s){ return s.replace(/[&<>"']/g, m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;' }[m])); }

function formatTime(ts){
  // Expect "YYYY-MM-DD HH:MM:SS"
  const d = new Date(ts.replace(' ', 'T'));
  if (isNaN(d)) return ts;
  return d.toLocaleString();
}

// UI events
el('search').addEventListener('input', e=>listUsers(e.target.value));
el('inputMsg').addEventListener('keydown', e=>{
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});

// Init
listUsers();

</script>
</body>
</html>
