@extends('layouts')

@section('page_title', 'Jeyanco AI')

@section('content')
<div class="ai-page-wrapper">

    {{-- ── PAGE HEADER ────────────────────────────────── --}}
    <x-page-header :title="__('Jeyanco Intelligence')">
        <x-slot:actions>
            <span class="ai-status-badge">
                <span class="ai-status-dot"></span>
                Connected to DB
            </span>
            <button id="togglePromptsBtn" class="ai-btn ai-btn-outline" title="{{ __('Toggle Quick Prompts') }}">
                <i data-lucide="layout-list" style="width:15px;height:15px;"></i>
                <span class="d-none d-md-inline">{{ __('Prompts') }}</span>
            </button>
            <button id="newChatBtn" class="ai-btn ai-btn-primary">
                <i data-lucide="plus" style="width:15px;height:15px;"></i>
                <span class="d-none d-md-inline">{{ __('New Chat') }}</span>
            </button>
        </x-slot:actions>
    </x-page-header>

    {{-- ── MAIN CONTENT: SIDEBAR + CHAT ───────────────── --}}
    <div class="ai-main" id="aiMain">

        {{-- QUICK PROMPTS SIDEBAR --}}
        <aside class="prompts-panel" id="promptsPanel">
            @include('partials.ai-prompts')
        </aside>

        {{-- CHAT WINDOW --}}
        <div class="ai-chat-wrap">
            <div class="chat-container">

                <div class="chat-messages" id="chatBox">
                    <div class="message ai">
                        <div class="avatar-icon">
                            <i data-lucide="bot" style="width:20px;height:20px;"></i>
                        </div>
                        <div class="bubble">
                            <strong>{{ __('Mabuhay, Admin!') }}</strong> {{ __('I\'m Jeyanco AI — your intelligent assistant for payroll, attendance, and workforce analytics.') }}<br><br>
                            Use the <strong>{{ __('Quick Actions') }}</strong> {{ __('panel on the left or type any question below.') }}
                        </div>
                    </div>
                </div>

                <div class="chat-input-area">
                    <form id="aiForm" class="input-wrapper">
                        <input
                            type="text"
                            id="userInput"
                            class="chat-input"
                            placeholder="{{ __('Ask something (e.g. Show payroll report)') }}"
                            autocomplete="off">
                        <button type="submit" class="send-btn" title="{{ __('Send') }}">
                            <i data-lucide="send" style="width:18px;height:18px;"></i>
                        </button>
                    </form>
                    <p class="chat-input-hint">
                        Press <kbd>{{ __('Enter') }}</kbd> {{ __('to send  ·  Powered by Jeyanco Intelligence') }}
                    </p>
                </div>

            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    lucide.createIcons();

    const aiForm      = document.getElementById('aiForm');
    const chatBox     = document.getElementById('chatBox');
    const userInput   = document.getElementById('userInput');
    const newChatBtn  = document.getElementById('newChatBtn');
    const toggleBtn   = document.getElementById('togglePromptsBtn');
    const promptsPanel = document.getElementById('promptsPanel');
    const aiMain      = document.getElementById('aiMain');

    // ── PROMPTS PANEL TOGGLE ──────────────────────────
    let panelOpen = true;

    function setPanelState(open) {
        panelOpen = open;
        if (open) {
            promptsPanel.classList.remove('collapsed');
            aiMain.classList.remove('prompts-hidden');
        } else {
            promptsPanel.classList.add('collapsed');
            aiMain.classList.add('prompts-hidden');
        }
    }

    toggleBtn.addEventListener('click', () => setPanelState(!panelOpen));

    // ── CATEGORY NAV ──────────────────────────────────
    // Inside this page's own panel: the chat's full-screen view carries the
    // same prompts (partials/ai-prompts) and wires its own.
    promptsPanel.querySelectorAll('.cat-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            promptsPanel.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            promptsPanel.querySelectorAll('.prompt-group').forEach(g => g.classList.remove('active'));
            this.classList.add('active');
            const group = promptsPanel.querySelector(`.prompt-group[data-group="${this.dataset.cat}"]`);
            if (group) group.classList.add('active');
        });
    });

    // ── PROMPT CHIPS ──────────────────────────────────
    promptsPanel.querySelectorAll('.prompt-chip').forEach(chip => {
        chip.addEventListener('click', function () {
            const msg = this.dataset.msg;
            userInput.value = msg;
            // On mobile, close panel so user sees the chat
            if (window.innerWidth <= 768) setPanelState(false);
            submitMessage(msg);
            userInput.value = '';
        });
    });

    // ── LOAD HISTORY ──────────────────────────────────
    window.addEventListener('load', async function () {
        try {
            const res  = await fetch('/ai/history', { headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
            const data = await res.json();
            if (data.messages && data.messages.length > 0) {
                chatBox.innerHTML = '';
                data.messages.forEach(m => appendMsg(m.type, m.message));
            }
        } catch (err) { console.error('History error:', err); }
    });

    // ── SEND MESSAGE ──────────────────────────────────
    aiForm.addEventListener('submit', function (e) {
        e.preventDefault();
        const text = userInput.value.trim();
        if (!text) return;
        userInput.value = '';
        submitMessage(text);
    });

    async function submitMessage(text) {
        appendMsg('user', text);
        const typingId = showTyping();

        try {
            const res = await fetch('/ai/chat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ message: text })
            });

            removeTyping(typingId);

            if (!res.ok) throw new Error(`HTTP ${res.status}`);
            const data = await res.json();
            appendMsg('ai', data.reply ?? data.message ?? 'No response received.');

        } catch (err) {
            removeTyping(typingId);
            appendMsg('ai', '❌ Error: ' + err.message);
        }
    }

    // ── NEW CHAT ──────────────────────────────────────
    newChatBtn.addEventListener('click', function () {
        chatBox.innerHTML = '';
        appendMsg('ai', '<strong>New session started.</strong> How can I help you?');
        userInput.focus();
    });

    // ── MESSAGE RENDERING ─────────────────────────────
    function appendMsg(type, text) {
        const icon = type === 'ai' ? 'bot' : 'user';
        const safeText = type === 'ai' ? formatAiText(text) : escapeHtml(text);
        const el = document.createElement('div');
        el.className = `message ${type}`;
        el.innerHTML = `
            <div class="avatar-icon">
                <i data-lucide="${icon}" style="width:18px;height:18px;"></i>
            </div>
            <div class="bubble">${safeText}</div>`;
        chatBox.appendChild(el);
        lucide.createIcons();
        chatBox.scrollTop = chatBox.scrollHeight;
    }

    let typingCounter = 0;
    function showTyping() {
        const id = 'typing-' + (++typingCounter);
        const el = document.createElement('div');
        el.className = 'message ai';
        el.id = id;
        el.innerHTML = `
            <div class="avatar-icon">
                <i data-lucide="bot" style="width:18px;height:18px;"></i>
            </div>
            <div class="bubble typing-indicator">
                <span></span><span></span><span></span>
            </div>`;
        chatBox.appendChild(el);
        lucide.createIcons();
        chatBox.scrollTop = chatBox.scrollHeight;
        return id;
    }

    function removeTyping(id) {
        const el = document.getElementById(id);
        if (el) el.remove();
    }

    // ── HELPERS ───────────────────────────────────────
    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatAiText(text) {
        // Preserve line breaks and bold headers
        return escapeHtml(text)
            .replace(/━+/g, '<hr class="ai-divider">')
            .replace(/\n/g, '<br>');
    }

    // ── CLEAR OLD MESSAGES (every 5 min) ─────────────
    setInterval(async function () {
        try {
            await fetch('/ai/clear-old', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
        } catch (e) {}
    }, 300000);

    // ── KEYBOARD: focus input on "/" ──────────────────
    document.addEventListener('keydown', function (e) {
        // Not while typing somewhere else, such as the floating chat.
        if (e.key === '/' && !e.target.closest('input, textarea, select, [contenteditable]')) {
            e.preventDefault();
            userInput.focus();
        }
    });
});
</script>
@endpush
