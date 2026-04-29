<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$active_nav = 'chatbot';
$page_title = 'AI Chat';
$extra_head = '';
include 'includes/header.php';
?>

<div class="page-container">
  <div class="section-header">
    <div class="section-title">
      <span class="dot"></span>
      AI Game Assistant
    </div>
    <span style="font-size:12px;color:var(--text-dim)">Using LLaMA 3.1 + RAG</span>
  </div>

  <p style="color:var(--text-secondary);font-size:14px;max-width:680px;margin-bottom:24px;line-height:1.7">
    Ask anything about games, prices, and reviews in this database. The assistant translates your question
    into SQL, queries the live database, and gives you a natural-language answer.
  </p>

  <!-- Example chips -->
  <div class="chatbot-examples">
    <span class="chatbot-examples-label">Try asking:</span>
    <button class="example-chip" onclick="sendExample(this)">What is the lowest price for ARK Survival Ascended?</button>
    <button class="example-chip" onclick="sendExample(this)">Which game has the highest positive reviews?</button>
    <button class="example-chip" onclick="sendExample(this)">Show me all Action games</button>
    <button class="example-chip" onclick="sendExample(this)">Which game had the biggest price drop?</button>
  </div>

  <div class="chatbot-card">
    <div class="chatbot-messages" id="chatMessages">
      <div class="chat-message bot">
        <div class="chat-avatar bot-avatar">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
          </svg>
        </div>
        <div class="chat-bubble">
          Hello! I'm your Steam data assistant. Ask me anything about game prices, reviews, or comparisons and I'll query the database for you.
        </div>
      </div>
    </div>

    <div class="chatbot-input-area">
      <div id="typingIndicator" class="typing-indicator" style="display:none">
        <span></span><span></span><span></span>
        <span style="margin-left:6px;font-size:12px;color:var(--text-secondary)">Thinking…</span>
      </div>
      <div class="chatbot-input-row">
        <input type="text" id="chatInput" placeholder="Ask about any game, price, or review…" autocomplete="off"
               onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendMessage();}">
        <button class="btn-primary chatbot-send" id="sendBtn" onclick="sendMessage()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <div class="chatbot-notice">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0;color:var(--yellow)">
      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span>
      The AI assistant requires the Python RAG server to be running locally.
      Start it with: <code>cd rag &amp;&amp; python chatbot_api.py</code>
      (default port <code>8000</code>).
    </span>
  </div>
</div>
<script>
var API_URL = 'http://localhost:8000/chat';

function appendMessage(text, role) {
  var container = document.getElementById('chatMessages');
  var div = document.createElement('div');
  div.className = 'chat-message ' + role;

  var avatar = document.createElement('div');
  avatar.className = 'chat-avatar ' + role + '-avatar';
  avatar.innerHTML = role === 'bot'
    ? '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>'
    : '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>';

  var bubble = document.createElement('div');
  bubble.className = 'chat-bubble';
  bubble.textContent = text;

  if (role === 'user') {
    div.appendChild(bubble);
    div.appendChild(avatar);
  } else {
    div.appendChild(avatar);
    div.appendChild(bubble);
  }

  container.appendChild(div);
  container.scrollTop = container.scrollHeight;
  return bubble;
}

function setTyping(show) {
  document.getElementById('typingIndicator').style.display = show ? 'flex' : 'none';
  document.getElementById('sendBtn').disabled  = show;
  document.getElementById('chatInput').disabled= show;
}

function sendMessage() {
  var input = document.getElementById('chatInput');
  var question = input.value.trim();
  if (!question) return;

  appendMessage(question, 'user');
  input.value = '';
  setTyping(true);

  fetch(API_URL, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({question: question})
  })
  .then(function(r) {
    if (!r.ok) throw new Error('Server responded with ' + r.status);
    return r.json();
  })
  .then(function(d) {
    setTyping(false);
    appendMessage(d.answer || 'No answer returned.', 'bot');
  })
  .catch(function(err) {
    setTyping(false);
    appendMessage(
      'Could not connect to the AI server. Make sure the Python RAG server is running on port 8000.\n\nError: ' + err.message,
      'bot'
    );
  });
}

function sendExample(btn) {
  document.getElementById('chatInput').value = btn.textContent;
  sendMessage();
}
</script>
</body>
</html>
