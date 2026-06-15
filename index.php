<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Angela – AI Assistant</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0d0d0d;
            --surface: #1a1a1a;
            --border: #2a2a2a;
            --text: #e5e5e5;
            --text-muted: #888;
            --accent: #7c5cfc;
            --accent-hover: #6a48e6;
            --user-bg: #2a2a3e;
            --assistant-bg: #1e1e1e;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', sans-serif;
            background: var(--bg);
            color: var(--text);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }

        header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 24px;
            border-bottom: 1px solid var(--border);
            background: var(--surface);
        }

        header .logo {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: var(--accent);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            color: #fff;
        }

        header h1 {
            font-size: 18px;
            font-weight: 600;
        }

        header .model-tag {
            font-size: 12px;
            color: var(--text-muted);
            background: var(--border);
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: auto;
        }

        #chat {
            flex: 1;
            overflow-y: auto;
            padding: 24px 0;
        }

        .message {
            max-width: 720px;
            margin: 0 auto;
            padding: 12px 24px;
            display: flex;
            gap: 14px;
            line-height: 1.6;
        }

        .message .avatar {
            width: 30px;
            height: 30px;
            min-width: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            margin-top: 2px;
        }

        .message.user .avatar { background: var(--user-bg); color: var(--accent); }
        .message.assistant .avatar { background: var(--accent); color: #fff; }

        .message .content {
            flex: 1;
            white-space: pre-wrap;
            word-break: break-word;
        }

        .message .content p { margin-bottom: 8px; }
        .message .content p:last-child { margin-bottom: 0; }

        .message .content code {
            background: var(--border);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .message .content pre {
            background: #111;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
            overflow-x: auto;
            margin: 8px 0;
        }

        .message .content pre code {
            background: none;
            padding: 0;
        }

        #input-area {
            border-top: 1px solid var(--border);
            background: var(--surface);
            padding: 16px 24px;
        }

        #input-area form {
            max-width: 720px;
            margin: 0 auto;
            display: flex;
            gap: 10px;
        }

        #input-area textarea {
            flex: 1;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 16px;
            color: var(--text);
            font-family: inherit;
            font-size: 14px;
            resize: none;
            min-height: 46px;
            max-height: 200px;
            line-height: 1.5;
        }

        #input-area textarea::placeholder { color: var(--text-muted); }
        #input-area textarea:focus { outline: none; border-color: var(--accent); }

        #input-area button {
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 10px;
            padding: 0 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            white-space: nowrap;
        }

        #input-area button:hover { background: var(--accent-hover); }
        #input-area button:disabled { opacity: 0.4; cursor: not-allowed; }

        .typing-indicator {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: var(--accent);
            border-radius: 50%;
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0%, 100% { opacity: 0.2; }
            50% { opacity: 1; }
        }

        .welcome {
            text-align: center;
            padding: 80px 24px;
            color: var(--text-muted);
        }

        .welcome h2 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .welcome p { font-size: 14px; }
    </style>
</head>
<body>

<header>
    <div class="logo">A</div>
    <h1>Angela</h1>
    <span class="model-tag">llama3-8b</span>
</header>

<div id="chat">
    <div class="welcome">
        <h2>Hello, I'm Angela</h2>
        <p>Your AI assistant powered by LLaMA 3. Ask me anything.</p>
    </div>
</div>

<div id="input-area">
    <form id="form">
        <textarea id="prompt" rows="1" placeholder="Send a message..." autofocus></textarea>
        <button type="submit" id="send-btn">Send</button>
    </form>
</div>

<script>
const BACKEND_URL = 'backend/index.php';

const chat      = document.getElementById('chat');
const form      = document.getElementById('form');
const prompt    = document.getElementById('prompt');
const sendBtn   = document.getElementById('send-btn');

let messages = [];
let streaming = false;

function scrollBottom() {
    chat.scrollTop = chat.scrollHeight;
}

function appendMessage(role, text) {
    const welcome = chat.querySelector('.welcome');
    if (welcome) welcome.remove();

    const div = document.createElement('div');
    div.className = 'message ' + role;

    const avatar = document.createElement('div');
    avatar.className = 'avatar';
    avatar.textContent = role === 'user' ? 'U' : 'A';

    const content = document.createElement('div');
    content.className = 'content';
    content.textContent = text;

    div.appendChild(avatar);
    div.appendChild(content);
    chat.appendChild(div);
    scrollBottom();

    return content;
}

function setStreaming(val) {
    streaming = val;
    sendBtn.disabled = val;
    prompt.disabled = val;
    if (!val) prompt.focus();
}

async function sendMessage(text) {
    messages.push({ role: 'user', content: text });
    appendMessage('user', text);

    const contentEl = appendMessage('assistant', '');
    const indicator = document.createElement('span');
    indicator.className = 'typing-indicator';
    contentEl.appendChild(indicator);

    setStreaming(true);

    try {
        const resp = await fetch(BACKEND_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ messages: messages })
        });

        if (!resp.ok) {
            contentEl.textContent = 'Error: ' + resp.status + ' ' + resp.statusText;
            setStreaming(false);
            return;
        }

        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let fullText = '';
        indicator.remove();

        while (true) {
            const { done, value } = await reader.read();
            if (done) break;

            const chunk = decoder.decode(value, { stream: true });
            const lines = chunk.split('\n');

            for (const line of lines) {
                if (!line.startsWith('data: ')) continue;
                const data = line.slice(6).trim();
                if (data === '[DONE]') continue;

                try {
                    const json = JSON.parse(data);
                    const delta = json.choices?.[0]?.delta?.content;
                    if (delta) {
                        fullText += delta;
                        contentEl.textContent = fullText;
                        scrollBottom();
                    }
                } catch (e) { /* skip non-JSON lines */ }
            }
        }

        messages.push({ role: 'assistant', content: fullText });
    } catch (err) {
        indicator.remove();
        contentEl.textContent = 'Connection error: ' + err.message;
    }

    setStreaming(false);
}

form.addEventListener('submit', function (e) {
    e.preventDefault();
    const text = prompt.value.trim();
    if (!text || streaming) return;
    prompt.value = '';
    prompt.style.height = 'auto';
    sendMessage(text);
});

prompt.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        form.dispatchEvent(new Event('submit'));
    }
});

prompt.addEventListener('input', function () {
    this.style.height = 'auto';
    this.style.height = Math.min(this.scrollHeight, 200) + 'px';
});
</script>

</body>
</html>
