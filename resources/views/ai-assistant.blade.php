@extends('layouts')

@section('page_title', 'Jeyanco AI')

@section('content')
<div class="ai-page-wrapper">

    {{-- ── PAGE HEADER ────────────────────────────────── --}}
    <div class="ai-page-header">
        <div class="ai-header-left">
            <div class="ai-header-icon">
                <i class="fas fa-robot"></i>
            </div>
            <div>
                <h4 class="ai-header-title">{{ __('Jeyanco Intelligence') }}</h4>
                <p class="ai-header-sub">{{ __('Payroll, attendance, and workforce analytics — ask anything.') }}</p>
            </div>
        </div>
        <div class="ai-header-actions">
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
        </div>
    </div>

    {{-- ── MAIN CONTENT: SIDEBAR + CHAT ───────────────── --}}
    <div class="ai-main" id="aiMain">

        {{-- QUICK PROMPTS SIDEBAR --}}
        <aside class="prompts-panel" id="promptsPanel">
            <div class="prompts-panel-inner">
                <p class="prompts-panel-label">{{ __('QUICK ACTIONS') }}</p>

                {{-- Category Navigation --}}
                <div class="category-nav" id="categoryNav">
                    <button class="cat-btn active" data-cat="workforce">
                        <span class="cat-icon"><i data-lucide="hard-hat" style="width:16px;height:16px;"></i></span> {{ __('Workforce') }}
                    </button>
                    <button class="cat-btn" data-cat="payroll">
                        <span class="cat-icon"><i data-lucide="wallet" style="width:16px;height:16px;"></i></span> {{ __('Payroll') }}
                    </button>
                    <button class="cat-btn" data-cat="attendance">
                        <span class="cat-icon"><i data-lucide="calendar-check" style="width:16px;height:16px;"></i></span> {{ __('Attendance') }}
                    </button>
                    <button class="cat-btn" data-cat="reports">
                        <span class="cat-icon"><i data-lucide="bar-chart-3" style="width:16px;height:16px;"></i></span> {{ __('Reports') }}
                    </button>
                    <button class="cat-btn" data-cat="security">
                        <span class="cat-icon"><i data-lucide="shield" style="width:16px;height:16px;"></i></span> {{ __('Security') }}
                    </button>
                    <button class="cat-btn" data-cat="settings">
                        <span class="cat-icon"><i data-lucide="settings" style="width:16px;height:16px;"></i></span> {{ __('Settings') }}
                    </button>
                    <button class="cat-btn" data-cat="system">
                        <span class="cat-icon"><i data-lucide="monitor" style="width:16px;height:16px;"></i></span> {{ __('System') }}
                    </button>
                </div>

                {{-- Prompt Chips per Category --}}
                <div class="prompts-body" id="promptsBody">

                    <div class="prompt-group active" data-group="workforce">
                        <p class="prompt-group-title">{{ __('Workforce Management') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="Total employees">{{ __('Total employees') }}</button>
                            <button class="prompt-chip" data-msg="List all employees">{{ __('List all employees') }}</button>
                            <button class="prompt-chip" data-msg="Employees by site">{{ __('By site') }}</button>
                            <button class="prompt-chip" data-msg="Employees by labor type">{{ __('By labor type') }}</button>
                            <button class="prompt-chip" data-msg="Who is the highest paid?">{{ __('Highest paid') }}</button>
                            <button class="prompt-chip" data-msg="Who is the lowest paid?">{{ __('Lowest paid') }}</button>
                            <button class="prompt-chip" data-msg="Total vale balance">{{ __('Total vale balance') }}</button>
                            <button class="prompt-chip" data-msg="Average rate">{{ __('Average hourly rate') }}</button>
                        </div>
                    </div>

                    <div class="prompt-group" data-group="payroll">
                        <p class="prompt-group-title">{{ __('Payroll & Finance') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="Total payroll">{{ __('Total payroll budget') }}</button>
                            <button class="prompt-chip" data-msg="Average rate">{{ __('Average hourly rate') }}</button>
                            <button class="prompt-chip" data-msg="Daily payroll estimate">{{ __('Daily payroll estimate') }}</button>
                            <button class="prompt-chip" data-msg="Show payroll report">{{ __('Payroll report') }}</button>
                            <button class="prompt-chip" data-msg="Who is the highest paid?">{{ __('Highest paid employee') }}</button>
                            <button class="prompt-chip" data-msg="Total vale balance">{{ __('Outstanding vale total') }}</button>
                        </div>
                    </div>

                    <div class="prompt-group" data-group="attendance">
                        <p class="prompt-group-title">{{ __('Attendance & Monitoring') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="Attendance today">{{ __('Attendance today') }}</button>
                            <button class="prompt-chip" data-msg="Weekly attendance">{{ __('This week\'s attendance') }}</button>
                            <button class="prompt-chip" data-msg="Who is absent today?">{{ __('Absent today') }}</button>
                            <button class="prompt-chip" data-msg="Time in/out status">{{ __('Time in/out status') }}</button>
                            <button class="prompt-chip" data-msg="Show attendance report">{{ __('Attendance report') }}</button>
                        </div>
                    </div>

                    <div class="prompt-group" data-group="reports">
                        <p class="prompt-group-title">{{ __('Reports & Analytics') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="Dashboard overview">{{ __('Dashboard overview') }}</button>
                            <button class="prompt-chip" data-msg="Show payroll report">{{ __('Payroll report') }}</button>
                            <button class="prompt-chip" data-msg="Show attendance report">{{ __('Attendance report') }}</button>
                            <button class="prompt-chip" data-msg="Employee statistics">{{ __('Employee statistics') }}</button>
                            <button class="prompt-chip" data-msg="Employees by site">{{ __('Employees by site') }}</button>
                            <button class="prompt-chip" data-msg="Daily payroll estimate">{{ __('Daily cost estimate') }}</button>
                        </div>
                    </div>

                    <div class="prompt-group" data-group="security">
                        <p class="prompt-group-title">{{ __('Security & Access') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="Security overview">{{ __('Security overview') }}</button>
                            <button class="prompt-chip" data-msg="Total users">{{ __('Total system users') }}</button>
                            <button class="prompt-chip" data-msg="Admin users">{{ __('Admin accounts') }}</button>
                            <button class="prompt-chip" data-msg="List all users">{{ __('List all users') }}</button>
                        </div>
                    </div>

                    <div class="prompt-group" data-group="settings">
                        <p class="prompt-group-title">{{ __('Settings & Configuration') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="System settings">{{ __('System settings') }}</button>
                            <button class="prompt-chip" data-msg="All deduction rates">{{ __('All deduction rates') }}</button>
                            <button class="prompt-chip" data-msg="SSS rate">{{ __('SSS rate') }}</button>
                            <button class="prompt-chip" data-msg="Pagibig rate">{{ __('Pag-IBIG rate') }}</button>
                            <button class="prompt-chip" data-msg="Philhealth rate">{{ __('PhilHealth rate') }}</button>
                        </div>
                    </div>

                    <div class="prompt-group" data-group="system">
                        <p class="prompt-group-title">{{ __('System & Support') }}</p>
                        <div class="chip-grid">
                            <button class="prompt-chip" data-msg="System status">{{ __('System status') }}</button>
                            <button class="prompt-chip" data-msg="Database summary">{{ __('Database summary') }}</button>
                            <button class="prompt-chip" data-msg="Active sites">{{ __('Active sites') }}</button>
                            <button class="prompt-chip" data-msg="Total sites">{{ __('Total sites') }}</button>
                            <button class="prompt-chip" data-msg="Labor types">{{ __('Labor types') }}</button>
                            <button class="prompt-chip" data-msg="Help">{{ __('Show all commands') }}</button>
                        </div>
                    </div>

                </div>
            </div>
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
    document.querySelectorAll('.cat-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.prompt-group').forEach(g => g.classList.remove('active'));
            this.classList.add('active');
            const group = document.querySelector(`.prompt-group[data-group="${this.dataset.cat}"]`);
            if (group) group.classList.add('active');
        });
    });

    // ── PROMPT CHIPS ──────────────────────────────────
    document.querySelectorAll('.prompt-chip').forEach(chip => {
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
        if (e.key === '/' && !userInput.matches(':focus')) {
            e.preventDefault();
            userInput.focus();
        }
    });
});
</script>
@endpush
