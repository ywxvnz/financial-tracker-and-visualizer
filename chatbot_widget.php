<!-- chatbot_widget.php - FULL WITH AUTOFILL -->
<style>
/* 🔘 Chatbot Toggle Button */
#chatbot-btn {
    position: fixed;
    bottom: 30px;
    left: 30px;
    background: #007bff;
    color: white;
    padding: 12px 18px;
    border-radius: 50px;
    font-weight: bold;
    border: none;
    z-index: 1000;
    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* 🪟 Chatbot Main Panel */
#chatbot-window {
    display: none;
    position: fixed;
    bottom: 90px;
    left: 30px;
    background: linear-gradient(to bottom, #ffffff, #f4f8ff);
    border: 1px solid #ddd;
    padding: 15px;
    width: 330px;
    height: 600px;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
    z-index: 1000;
    flex-direction: column;
    overflow: hidden;
    transition: opacity 0.3s ease;
    opacity: 0;
    pointer-events: none;
}
#chatbot-window.active {
    display: flex;
    opacity: 1;
    pointer-events: auto;
}

/* 🧭 Header */
#chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
    padding-bottom: 5px;
    border-bottom: 1px solid #eee;
}
#chat-header h3 {
    margin: 0;
    color: #1d2a62;
}
#chat-close-btn {
    background: none;
    border: none;
    font-size: 1.2em;
    cursor: pointer;
    color: #555;
}

/* 🗂 Tabs */
.prompt-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 8px;
}
.prompt-tabs button {
    flex: 1 1 auto;
    font-size: 12px;
    padding: 6px 10px;
    border-radius: 12px;
    border: none;
    background-color: #eee;
    cursor: pointer;
    white-space: nowrap;
    font-weight: 500;
    transition: background-color 0.3s ease, color 0.3s ease;
}
.prompt-tabs button:hover {
    background-color: #007bff;
    color: white;
}

/* 🧩 Prompt Panel */
.prompt-group {
    display: none;
    flex-wrap: wrap;
    justify-content: flex-start; /* align buttons to the left */
    gap: 6px;
    padding: 10px 0 0 0;
    margin: 0;
    max-height: 120px;
    overflow-y: auto;
    border-radius: 10px;
    background-color: #f4f8ff;
    box-shadow: inset 0 0 6px rgba(0, 0, 0, 0.03);
}
.prompt-group.active {
    display: flex;
}
#prompt-group-tips {
    background-color: #e8f4fc;
}
#prompt-group-loans {
    background-color: #e8f4fc;
}
#prompt-group-balance {
    background-color: #e8f4fc;
}
/* 🌟 Modern Scrollbars */
.prompt-group::-webkit-scrollbar,
#chat-messages::-webkit-scrollbar,
.autocomplete-list::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.prompt-group::-webkit-scrollbar-track,
#chat-messages::-webkit-scrollbar-track,
.autocomplete-list::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
}

.prompt-group::-webkit-scrollbar-thumb,
#chat-messages::-webkit-scrollbar-thumb,
.autocomplete-list::-webkit-scrollbar-thumb {
    background-color: rgba(0, 123, 255, 0.4); /* light blue */
    border-radius: 10px;
    border: 1px solid rgba(0, 123, 255, 0.2);
}

.prompt-group::-webkit-scrollbar-thumb:hover,
#chat-messages::-webkit-scrollbar-thumb:hover,
.autocomplete-list::-webkit-scrollbar-thumb:hover {
    background-color: rgba(0, 123, 255, 0.6);
}

/* 📌 Prompt Buttons */
.chat-prompt-btn {
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 18px;
    padding: 7px 12px;
    font-size: 13px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    white-space: nowrap;
    max-width: 100%;
    text-align: center;
    flex-shrink: 0;
}
.chat-prompt-btn:hover {
    background-color: #0056b3;
}
/* 💬 Chat Messages */
#chat-messages {
    flex-grow: 1;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #f0f0f0;
    border-radius: 8px;
    background-color: #e8f4fc;
    margin-bottom: 10px;
    display: flex;
    flex-direction: column;
    min-height: 250px;
}
.message-bubble {
    padding: 8px 12px;
    border-radius: 15px;
    margin-bottom: 8px;
    max-width: 80%;
    word-wrap: break-word;
    animation: fadeIn 0.3s ease;
}
.user-message {
    background-color: #e0f7fa;
    align-self: flex-end;
}
.bot-message {
    background-color: #f0f0f0;
    align-self: flex-start;
}
.message-bubble::after {
    content: attr(data-time);
    display: block;
    font-size: 10px;
    color: #999;
    margin-top: 2px;
    text-align: right;
}
.message-bubble:hover {
    background-color: #e7f1ff;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: translateY(0); }
}

/* ✏️ Input Container */
#chat-input-container {
    position: relative;
    display: flex;
    flex-direction: column;
}
#chat-input {
    width: 100%;
    padding: 10px 45px 10px 15px;
    border: 1px solid #ccc;
    border-radius: 20px;
    outline: none;
    font-size: 14px;
}
#chat-send-btn {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    background-color: #007bff;
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    font-size: 16px;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
}

/* ⌨️ Autofill List */
.autocomplete-list {
    position: absolute;
    bottom: 45px;
    left: 0;
    right: 0;
    background: #ffffff;
    border: 1px solid #ccc;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    max-height: 160px;
    overflow-y: auto;
    padding: 4px 0;
    z-index: 999;
    font-size: 14px;
}
.autocomplete-list li {
    padding: 10px 14px;
    cursor: pointer;
    color: #333;
    transition: background-color 0.2s ease;
    list-style: none;
    border-bottom: 1px solid #f1f1f1;
}
.autocomplete-list li:last-child {
    border-bottom: none;
}
.autocomplete-list li:hover {
    background-color: #f0f7ff;
    color: #007bff;
    font-weight: 500;
}
.autocomplete-list::-webkit-scrollbar {
    width: 6px;
}
.autocomplete-list::-webkit-scrollbar-thumb {
    background-color: #ccc;
    border-radius: 10px;
}

/* ⬇️ Other UI */
#chatbot-window.active {
    opacity: 1;
    pointer-events: auto;
}
#chatbot-btn.hidden {
    display: none;
}
.typing-indicator {
    display: flex;
    gap: 4px;
    margin-top: 8px;
    align-self: flex-start;
}
.typing-indicator span {
    width: 6px;
    height: 6px;
    background: #aaa;
    border-radius: 50%;
    animation: blink 1s infinite ease-in-out;
}
.typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
.typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

@keyframes blink {
    0%, 80%, 100% { opacity: 0; }
    40% { opacity: 1; }
}

</style>


<button id="chatbot-btn">💬 HelperBot</button>

<div id="chatbot-window">
    <div id="chat-header">
        <h3>Financial Assistant</h3>
        <button id="chat-close-btn">✖</button>
    </div>

    <div id="chat-prompts">
        <div class="prompt-tabs">
            <button onclick="showPromptGroup('loans')">Loans</button>
            <button onclick="showPromptGroup('balance')">Balance</button>
            <button onclick="showPromptGroup('expenses')">Expenses</button>
            <button onclick="showPromptGroup('savings')">Savings & Budget</button>
            <button onclick="showPromptGroup('reminders')">Reminders</button>
            <button onclick="showPromptGroup('overview')">Overview</button>
            <button onclick="showPromptGroup('tips')">Tips</button>

        </div>

        <div id="prompt-group-loans" class="prompt-group active">
            <button class="chat-prompt-btn" data-query="loan due date">📅 Loan Due Dates</button>
            <button class="chat-prompt-btn" data-query="loan balance">📊 Loan Summary</button>
            <button class="chat-prompt-btn" data-query="can I afford my loans">🤔 Can I Afford Loans?</button>
            <button class="chat-prompt-btn" data-query="should I pay my loan now?">🗓️ Should I Pay My Loan Now?</button>
        </div>

        <div id="prompt-group-balance" class="prompt-group">
            <button class="chat-prompt-btn" data-query="low balance">💸 Low Balance Alert</button>
            <button class="chat-prompt-btn" data-query="current balance">💼 Check My Balance</button>
        </div>

        <div id="prompt-group-expenses" class="prompt-group">
            <button class="chat-prompt-btn" data-query="show my biggest expenses this month">💰 Top Expenses</button>
            <button class="chat-prompt-btn" data-query="should I cut back on spending">🧾 Spending Advice</button>
            <button class="chat-prompt-btn" data-query="which category costs me the most">📂 Top Category</button>
        </div>

        <div id="prompt-group-savings" class="prompt-group">
            <button class="chat-prompt-btn" data-query="how much can I save this month?">💸 How Much Can I Save?</button>
            <button class="chat-prompt-btn" data-query="how much of my budget is already used?">📊 Budget Usage</button>
        </div>

        <div id="prompt-group-reminders" class="prompt-group">
            <button class="chat-prompt-btn" data-query="what do I need to do today?">📝 What do I need to do today?</button>
        </div>
        <div id="prompt-group-overview" class="prompt-group">
            <button class="chat-prompt-btn" data-query="show my financial summary">📊 Show My Financial Summary</button>
            <button class="chat-prompt-btn" data-query="is my spending healthy">🧠 Is My Spending Healthy?</button>
        </div>
        <div id="prompt-group-tips" class="prompt-group">
            <button class="chat-prompt-btn" data-query="give me a money-saving tip">💡 Money-Saving Tip</button>
            <button class="chat-prompt-btn" data-query="recommend a budget strategy">📊 Budget Strategy</button>
            <button class="chat-prompt-btn" data-query="how can I improve my finances?">📈 Improve My Finances</button>
        </div>



    </div>

    <div id="chat-messages">
        <div class="message-bubble bot-message">Hello! How can I help you today?</div>
    </div>

    <div id="chat-input-container">
    <div class="input-wrapper">
        <input type="text" id="chat-input" placeholder="Type your message...">
        <button id="chat-send-btn">➤</button>
    </div>
    <ul id="chat-autofill" class="autocomplete-list"></ul>
</div>

</div>

<script>
const chatbotBtn = document.getElementById('chatbot-btn');
const chatbotWindow = document.getElementById('chatbot-window');
const chatCloseBtn = document.getElementById('chat-close-btn');
const chatMessages = document.getElementById('chat-messages');
const chatInput = document.getElementById('chat-input');
const chatSendBtn = document.getElementById('chat-send-btn');
const autofillList = document.getElementById("chat-autofill");

const typingSuggestions = [
  // Loans
  "loan balance",
  "loan due date",
  "can I afford my loans",
  "should I pay my loan now?",

  // Balance
  "low balance",
  "check my balance",
  "current balance",
  "is my balance below threshold",

  // Expenses
  "top expenses",
  "show my biggest expenses this month",
  "should I cut back on spending",
  "which category costs me the most",
  "what is my top spending category",
  "how much have I spent this month",

  // Savings & Budget
  "how much can I save this month?",
  "how much of my budget is already used?",
  "budget usage",
  "how much should I save",
  "suggest a budget plan",

  // Reminders
  "what do I need to do today?",
  "do I have loans due today",
  "send me reminders",
  "remind me to pay",

  // Overview
  "show my financial summary",
  "is my spending healthy",
  "summarize my budget",
  "show monthly report",

  // Tips
  "give me a money-saving tip",
  "recommend a budget strategy",
  "how can I improve my finances?",
  "financial advice",

  // Greetings / misc
  "hello",
  "hi",
  "help",
  "what can you do",
  "open assistant",
  "close chatbot"
];


chatbotBtn.addEventListener('click', () => {
    chatbotWindow.classList.add('active');
    chatbotBtn.classList.add('hidden');
    setTimeout(() => chatInput.focus(), 100);
});

chatCloseBtn.addEventListener('click', () => {
    chatbotWindow.classList.remove('active');
    chatbotBtn.classList.remove('hidden');
});


chatSendBtn.addEventListener('click', sendMessage);
chatInput.addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendMessage();
});

function sendMessage() {
    const userMessage = chatInput.value.trim();
    if (!userMessage) return;
    appendMessage(userMessage, 'user');
    chatInput.value = '';
    autofillList.innerHTML = '';
    getBotResponse(userMessage);
}

function appendMessage(text, sender) {
    const msg = document.createElement('div');
    msg.classList.add('message-bubble', sender === 'user' ? 'user-message' : 'bot-message');
    const now = new Date();
    const time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    msg.setAttribute('data-time', time);
    msg.textContent = text;
    chatMessages.appendChild(msg);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

async function getBotResponse(userMessage) {
    const typing = document.createElement('div');
    typing.classList.add('typing-indicator');
    typing.innerHTML = `<span></span><span></span><span></span>`;
    chatMessages.appendChild(typing);
    chatMessages.scrollTop = chatMessages.scrollHeight;

    try {
        const res = await fetch('chatbot_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query: userMessage })
        });
        const data = await res.json();
        typing.remove();
        appendMessage(data.response, 'bot');
    } catch {
        typing.remove();
        appendMessage("Oops! Something went wrong.", 'bot');
    }
}

document.querySelectorAll('.chat-prompt-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const query = btn.dataset.query;
        appendMessage(query, 'user');
        getBotResponse(query);
    });
});

function showPromptGroup(group) {
    document.querySelectorAll('.prompt-group').forEach(g => g.classList.remove('active'));
    document.getElementById(`prompt-group-${group}`).classList.add('active');
}

chatInput.addEventListener('input', () => {
    const input = chatInput.value.toLowerCase().trim();
    autofillList.innerHTML = '';
    if (input.length < 2) return;

    const matches = typingSuggestions.filter(item => item.includes(input));
    matches.forEach(match => {
        const li = document.createElement('li');
        li.textContent = match;
        li.onclick = () => {
            chatInput.value = match;
            autofillList.innerHTML = '';
            chatInput.focus();
        };
        autofillList.appendChild(li);
    });
});

document.addEventListener('click', (e) => {
    if (!autofillList.contains(e.target) && e.target !== chatInput) {
        autofillList.innerHTML = '';
    }
});
</script>
