<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// =============================================
// KONFIGURASI GROQ API
// Ganti dengan API key kamu dari https://console.groq.com
// =============================================
define('GROQ_API_KEY', '');
define('GROQ_MODEL',   'llama-3.3-70b-versatile'); // Bisa diganti: mixtral-8x7b-32768, gemma2-9b-it, dll
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');

// ── Handle AJAX request ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
    header('Content-Type: application/json; charset=utf-8');

    $message  = trim($_POST['message'] ?? '');
    $history  = json_decode($_POST['history'] ?? '[]', true);

    if ($message === '') {
        echo json_encode(['error' => 'Pesan tidak boleh kosong.']);
        exit;
    }

    // Bangun array messages untuk Groq
    $messages = [
        [
            'role'    => 'system',
            'content' => 'Kamu adalah asisten AI bernama Sakura AI, bagian dari aplikasi Sakura App — platform belajar bahasa Jepang. Bantu pengguna dengan ramah dalam bahasa Indonesia. Kamu bisa menjawab pertanyaan umum maupun seputar belajar bahasa Jepang (kosakata, kanji, tata bahasa, budaya Jepang, dll). Jawaban singkat, jelas, dan informatif.',
        ],
    ];

    // Tambahkan riwayat percakapan (maks 10 pesan terakhir agar hemat token)
    if (is_array($history)) {
        $history = array_slice($history, -10);
        foreach ($history as $h) {
            if (isset($h['role'], $h['content'])) {
                $messages[] = [
                    'role'    => in_array($h['role'], ['user', 'assistant']) ? $h['role'] : 'user',
                    'content' => mb_substr($h['content'], 0, 2000),
                ];
            }
        }
    }

    // Tambahkan pesan baru user
    $messages[] = [
        'role'    => 'user',
        'content' => mb_substr(sanitize($message), 0, 1000),
    ];

    // Panggil Groq API
    $payload = json_encode([
        'model'       => GROQ_MODEL,
        'messages'    => $messages,
        'max_tokens'  => 1024,
        'temperature' => 0.7,
    ]);

    $ch = curl_init(GROQ_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . GROQ_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);

    $result   = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        echo json_encode(['error' => 'Gagal menghubungi Groq: ' . $curlErr]);
        exit;
    }

    $data = json_decode($result, true);

    if ($httpCode !== 200 || !isset($data['choices'][0]['message']['content'])) {
        $errMsg = $data['error']['message'] ?? ('HTTP ' . $httpCode);
        echo json_encode(['error' => 'Groq error: ' . $errMsg]);
        exit;
    }

    echo json_encode([
        'reply' => $data['choices'][0]['message']['content'],
        'model' => $data['model'] ?? GROQ_MODEL,
    ]);
    exit;
}
// ────────────────────────────────────────────────────────────────────────────

$initial  = strtoupper(mb_substr($user['name'], 0, 1));
$isAdmin  = $user['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Tanya AI</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ── Layout ── */
    .chat-page-wrap {
      display: flex;
      flex-direction: column;
      height: 100dvh;
      overflow: hidden;
    }
    .chat-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      max-width: 760px;
      width: 100%;
      margin: 0 auto;
      padding: 0 16px;
      overflow: hidden;
    }

    /* ── Header info ── */
    .chat-heading {
      padding: 18px 0 12px;
      text-align: center;
      flex-shrink: 0;
    }
    .chat-heading-title {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--text);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .chat-heading-sub {
      font-size: .78rem;
      color: var(--text-muted);
      margin-top: 4px;
    }
    .chat-model-badge {
      display: inline-block;
      background: linear-gradient(135deg,#7c3aed,#a855f7);
      color: #fff;
      border-radius: 20px;
      padding: 2px 10px;
      font-size: .7rem;
      font-weight: 700;
      margin-top: 6px;
      letter-spacing: .03em;
    }

    /* ── Messages ── */
    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 10px 0 16px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      scroll-behavior: smooth;
    }
    .chat-messages::-webkit-scrollbar { width: 4px; }
    .chat-messages::-webkit-scrollbar-thumb { background: var(--card-border); border-radius: 4px; }

    .msg-row {
      display: flex;
      gap: 10px;
      align-items: flex-end;
    }
    .msg-row.user { flex-direction: row-reverse; }

    .msg-avatar {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .75rem;
      font-weight: 800;
      flex-shrink: 0;
    }
    .msg-avatar.ai-av {
      background: linear-gradient(135deg,#7c3aed,#a855f7);
      color: #fff;
    }
    .msg-avatar.user-av {
      background: linear-gradient(135deg,var(--torii),#c0392b);
      color: #fff;
    }

    .msg-bubble {
      max-width: 72%;
      padding: 11px 15px;
      border-radius: 18px;
      font-size: .875rem;
      line-height: 1.6;
      word-break: break-word;
      white-space: pre-wrap;
    }
    .msg-row.user .msg-bubble {
      background: linear-gradient(135deg,var(--torii),#c0392b);
      color: #fff;
      border-bottom-right-radius: 6px;
    }
    .msg-row.ai .msg-bubble {
      background: var(--card-bg);
      color: var(--text);
      border: 1px solid var(--card-border);
      border-bottom-left-radius: 6px;
    }

    /* Welcome bubble */
    .msg-row.ai.welcome .msg-bubble {
      border-color: rgba(124,58,237,.3);
      background: linear-gradient(135deg,rgba(124,58,237,.07),rgba(168,85,247,.05));
    }

    /* Typing indicator */
    .typing-dots {
      display: flex;
      gap: 4px;
      align-items: center;
      padding: 4px 0;
    }
    .typing-dots span {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--text-muted);
      animation: typingBounce .9s infinite;
    }
    .typing-dots span:nth-child(2) { animation-delay: .15s; }
    .typing-dots span:nth-child(3) { animation-delay: .3s; }
    @keyframes typingBounce {
      0%,60%,100% { transform: translateY(0); opacity:.5; }
      30% { transform: translateY(-6px); opacity:1; }
    }

    /* Error bubble */
    .msg-bubble.error-bubble {
      background: rgba(183,75,75,.1);
      border-color: rgba(183,75,75,.3);
      color: var(--torii);
    }

    /* ── Input area ── */
    .chat-input-wrap {
      flex-shrink: 0;
      padding: 12px 0 20px;
      border-top: 1px solid var(--card-border);
    }
    .chat-input-row {
      display: flex;
      gap: 10px;
      align-items: flex-end;
    }
    .chat-textarea {
      flex: 1;
      resize: none;
      border: 1.5px solid var(--card-border);
      border-radius: 14px;
      padding: 11px 14px;
      font-size: .875rem;
      font-family: inherit;
      color: var(--text);
      background: var(--card-bg);
      outline: none;
      max-height: 120px;
      min-height: 44px;
      line-height: 1.5;
      transition: border-color .2s;
    }
    .chat-textarea:focus { border-color: #7c3aed; }
    .chat-textarea::placeholder { color: var(--text-muted); }

    .chat-send-btn {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      border: none;
      background: linear-gradient(135deg,#7c3aed,#a855f7);
      color: #fff;
      font-size: 1.15rem;
      cursor: pointer;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: transform .18s, opacity .18s;
      box-shadow: 0 2px 8px rgba(124,58,237,.35);
    }
    .chat-send-btn:hover:not(:disabled) { transform: scale(1.08); }
    .chat-send-btn:disabled { opacity: .45; cursor: not-allowed; }

    .chat-hint {
      font-size: .72rem;
      color: var(--text-muted);
      margin-top: 8px;
      text-align: center;
    }

    /* ── Topbar AI badge ── */
    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .topbar-back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 20px;
      background: rgba(183,75,75,.1);
      border: 1.5px solid rgba(183,75,75,.25);
      color: var(--torii, #b74b4b);
      font-size: .85rem;
      font-weight: 700;
      text-decoration: none;
    
      transition: background .18s;
    }
    .topbar-back-btn:hover { border-color: var(--torii); }

    /* ── Starter suggestions ── */
    .chat-suggestions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 14px;
      justify-content: center;
    }
    .suggestion-chip {
      padding: 6px 13px;
      border-radius: 20px;
      background: var(--card-bg);
      border: 1.5px solid var(--card-border);
      color: var(--text);
      font-size: .78rem;
      cursor: pointer;
      transition: border-color .18s, background .18s;
    }
    .suggestion-chip:hover {
      border-color: #a855f7;
      background: rgba(124,58,237,.07);
    }
  </style>
</head>
<body class="dashboard-page chat-page-wrap">

  <!-- Background -->
  <div class="asanoha-bg"></div>

  <!-- ── TOPBAR ── -->
  <header class="topbar">
    <div class="topbar-brand">🤖 Sakura AI</div>
    <div class="topbar-actions">
      <a href="beranda.php" class="topbar-back-btn">← Beranda</a>
      <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">☀️</button>
    </div>
  </header>

  <!-- ── CHAT MAIN ── -->
  <main class="chat-main">

    <!-- Heading -->
    <div class="chat-heading">
      <div class="chat-heading-title">🌸 Sakura AI</div>
      <div class="chat-heading-sub">Asisten belajar bahasa Jepang & teman ngobrol kamu</div>
      <span class="chat-model-badge">⚡ Powered by Groq · <?= htmlspecialchars(GROQ_MODEL) ?></span>
    </div>

    <!-- Messages -->
    <div class="chat-messages" id="chatMessages">
      <!-- Welcome message -->
      <div class="msg-row ai welcome">
        <div class="msg-avatar ai-av">AI</div>
        <div class="msg-bubble">
          こんにちは、<?= htmlspecialchars($user['name']) ?>さん！ 🌸<br><br>
          Saya <strong>Sakura AI</strong>, asisten pintarmu. Kamu bisa tanya apa saja — mulai dari kosakata Jepang, kanji, tata bahasa, hingga pertanyaan umum lainnya.<br><br>
          Mau mulai dari mana? 😊
        </div>
      </div>
    </div>

    <!-- Suggestions (muncul di awal, hilang setelah kirim pesan pertama) -->
    <div class="chat-suggestions" id="chatSuggestions">
      <button class="suggestion-chip" onclick="sendSuggestion(this)">Apa arti ありがとう?</button>
      <button class="suggestion-chip" onclick="sendSuggestion(this)">Cara bilang "Selamat pagi" dalam bahasa Jepang?</button>
      <button class="suggestion-chip" onclick="sendSuggestion(this)">Jelaskan perbedaan は dan が</button>
      <button class="suggestion-chip" onclick="sendSuggestion(this)">Tips belajar kanji untuk pemula</button>
    </div>

    <!-- Input -->
    <div class="chat-input-wrap">
      <div class="chat-input-row">
        <textarea
          id="chatInput"
          class="chat-textarea"
          placeholder="Ketik pesanmu di sini…"
          rows="1"
          maxlength="1000"
        ></textarea>
        <button class="chat-send-btn" id="sendBtn" onclick="sendMessage()" title="Kirim">➤</button>
      </div>
     
  </main>

  <script src="js/theme.js"></script>
  <script>
    const chatMessages    = document.getElementById('chatMessages');
    const chatInput       = document.getElementById('chatInput');
    const sendBtn         = document.getElementById('sendBtn');
    const suggestionsEl   = document.getElementById('chatSuggestions');

    // Riwayat percakapan (dikirim ke server setiap request)
    let history = [];
    let isLoading = false;

    // Auto-resize textarea
    chatInput.addEventListener('input', () => {
      chatInput.style.height = 'auto';
      chatInput.style.height = Math.min(chatInput.scrollHeight, 120) + 'px';
    });

    // Enter = kirim, Shift+Enter = baris baru
    chatInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    function scrollToBottom() {
      chatMessages.scrollTop = chatMessages.scrollHeight;
    }

    function appendMessage(role, content, isError = false) {
      const row = document.createElement('div');
      row.className = 'msg-row ' + role;

      const avatar = document.createElement('div');
      avatar.className = 'msg-avatar ' + (role === 'user' ? 'user-av' : 'ai-av');
      avatar.textContent = role === 'user' ? '<?= $initial ?>' : 'AI';

      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble' + (isError ? ' error-bubble' : '');
      bubble.textContent = content;

      row.appendChild(avatar);
      row.appendChild(bubble);
      chatMessages.appendChild(row);
      scrollToBottom();
      return row;
    }

    function showTyping() {
      const row = document.createElement('div');
      row.className = 'msg-row ai';
      row.id = 'typingRow';

      const avatar = document.createElement('div');
      avatar.className = 'msg-avatar ai-av';
      avatar.textContent = 'AI';

      const bubble = document.createElement('div');
      bubble.className = 'msg-bubble';
      bubble.innerHTML = '<div class="typing-dots"><span></span><span></span><span></span></div>';

      row.appendChild(avatar);
      row.appendChild(bubble);
      chatMessages.appendChild(row);
      scrollToBottom();
    }

    function removeTyping() {
      const el = document.getElementById('typingRow');
      if (el) el.remove();
    }

    function hideSuggestions() {
      if (suggestionsEl) {
        suggestionsEl.style.display = 'none';
      }
    }

    async function sendMessage(text) {
      const message = (text || chatInput.value).trim();
      if (!message || isLoading) return;

      hideSuggestions();
      chatInput.value = '';
      chatInput.style.height = 'auto';

      appendMessage('user', message);
      history.push({ role: 'user', content: message });

      isLoading = true;
      sendBtn.disabled = true;
      showTyping();

      try {
        const fd = new FormData();
        fd.append('action', 'chat');
        fd.append('message', message);
        fd.append('history', JSON.stringify(history.slice(0, -1))); // tanpa pesan terakhir yg baru ditambah

        const res  = await fetch('chatbot.php', { method: 'POST', body: fd });
        const data = await res.json();
        removeTyping();

        if (data.error) {
          appendMessage('ai', '⚠️ ' + data.error, true);
        } else {
          appendMessage('ai', data.reply);
          history.push({ role: 'assistant', content: data.reply });
        }
      } catch (err) {
        removeTyping();
        appendMessage('ai', '⚠️ Terjadi kesalahan koneksi. Coba lagi ya!', true);
      }

      isLoading = false;
      sendBtn.disabled = false;
      chatInput.focus();
    }

    function sendSuggestion(btn) {
      sendMessage(btn.textContent);
    }

    // Fokus input saat halaman dibuka
    chatInput.focus();
  </script>
</body>
</html>