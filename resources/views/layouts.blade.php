<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('page_title') | Jeyanco Payroll</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {{-- Apply saved theme BEFORE paint to prevent a flash of the wrong theme --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('jeyanco-theme');
                // Enterprise dark is the default experience; a saved choice still wins.
                var theme = stored || 'dark';
                document.documentElement.setAttribute('data-bs-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'dark');
            }
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    {{-- Cache-bust each stylesheet with its file mtime so a new deploy always
         serves fresh CSS instead of a stale browser-cached copy. --}}
    @php $cssv = fn ($f) => asset($f) . '?v=' . (@filemtime(public_path($f)) ?: '1'); @endphp
    <link rel="stylesheet" href="{{ $cssv('layouts.css') }}">
    <link rel="stylesheet" href="{{ $cssv('dashboard.css') }}">
    <link rel="stylesheet" href="{{ $cssv('payroll.css') }}">
    <link rel="stylesheet" href="{{ $cssv('attendance.css') }}">
    <link rel="stylesheet" href="{{ $cssv('ai.css') }}">
    <link rel="stylesheet" href="{{ $cssv('analytics.css') }}">
    <link rel="stylesheet" href="{{ $cssv('login.css') }}">
    <link rel="stylesheet" href="{{ $cssv('dark-mode.css') }}">

    {{-- Enterprise design system — loaded LAST so it owns the final visual language --}}
    <link rel="stylesheet" href="{{ $cssv('enterprise.css') }}">

    {{-- Jeyanco brand design tokens — loaded AFTER enterprise so it owns the final palette --}}
    <link rel="stylesheet" href="{{ $cssv('design-tokens.css') }}">

    @stack('styles')

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="bg-light">

<!-- SIDEBAR OVERLAY (mobile) -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">

    <div class="sidebar-top">
        <div class="brand-title mb-4">
            <div class="logo-wrapper">
                <img src="/images/JeyancoLogo.png" class="brand-icon" alt="Logo">
            </div>
            <span class="brand-text typing">Jeyanco Construction</span>
        </div>

        <nav class="nav-menu">

            <div class="menu-section">MAIN</div>
            <a class="nav-link {{ request()->is('dashboard') ? 'active' : '' }}" href="{{ url('/dashboard') }}">
                <i data-lucide="layout-dashboard"></i> <span>Dashboard</span>
            </a>

            <div class="menu-section">WORKFORCE</div>
            <a class="nav-link {{ request()->is('attendance*') ? 'active' : '' }}" href="{{ url('/attendance') }}">
                <i data-lucide="calendar-check"></i> <span>Attendance</span>
            </a>
            <a class="nav-link {{ request()->is('employees*') && !request()->routeIs('employees.register') ? 'active' : '' }}" href="{{ url('/employees') }}">
                <i data-lucide="users"></i> <span>Employees</span>
            </a>
            @php $pendingKiosk = \App\Models\Employee::pending()->count(); @endphp
            <a class="nav-link {{ request()->routeIs('employees.register') ? 'active' : '' }}" href="{{ route('employees.register') }}">
                <i data-lucide="user-plus"></i> <span>Register &amp; Manage</span>
                @if($pendingKiosk > 0)
                    <span class="nav-pending-badge" title="{{ $pendingKiosk }} worker(s) detected by the kiosk awaiting registration">{{ $pendingKiosk }}</span>
                @endif
            </a>

            <div class="menu-section">PROJECT</div>
            <a class="nav-link {{ request()->is('sites*') ? 'active' : '' }}" href="{{ route('sites.index') }}">
                <i data-lucide="map-pin"></i> <span>Sites</span>
            </a>

            <div class="menu-section">PAYROLL</div>
            <a class="nav-link {{ request()->is('payroll*') || request()->is('reports*') || request()->is('payslip*') ? 'active' : '' }}" href="{{ url('/payroll-records') }}">
                <i data-lucide="receipt"></i> <span>Payroll Records</span>
            </a>

            <div class="menu-section">INSIGHTS</div>
            <a class="nav-link {{ request()->is('analytics*') ? 'active' : '' }}" href="{{ url('/analytics') }}">
                <i data-lucide="bar-chart-3"></i> <span>Analytics</span>
            </a>
            <a class="nav-link {{ request()->is('ai-assistant*') ? 'active' : '' }}" href="{{ url('/ai-assistant') }}">
                <i data-lucide="bot"></i> <span>Jeyanco AI</span>
            </a>

            <div class="menu-section">SYSTEM</div>
            <a class="nav-link {{ request()->is('settings*') ? 'active' : '' }}" href="{{ route('settings.index') }}">
                <i data-lucide="settings"></i> <span>Settings</span>
            </a>

        </nav>
    </div>

</div>

<div class="main-content">

    <div class="topbar d-flex justify-content-between align-items-center px-4">

        <div class="d-flex align-items-center gap-3">
            <button class="sidebar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
                <i data-lucide="menu"></i>
            </button>
            <div class="status-pill d-none d-lg-flex">
                <div class="dot pulse"></div>
                <span>SYSTEM LIVE</span>
            </div>
            <div class="v-divider"></div>
            <div>
                <h4 class="page-main-title">@yield('page_title', 'Dashboard')</h4>
                <div class="topbar-breadcrumb">
                    <span>Jeyanco</span> <i data-lucide="chevron-right"></i> <span class="active">Control Panel</span>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-4">
            <div class="search-container d-none d-md-flex" style="position: relative;">
                <i data-lucide="search"></i>
                <input type="text" id="global-search-input" placeholder="Search data..." autocomplete="off">
                <div id="search-suggestions" class="search-suggestions-dropdown"></div>
                <kbd>/</kbd>
            </div>

            <button class="theme-switch" id="themeToggle" type="button" role="switch" aria-label="Toggle dark mode" title="Toggle dark / light mode">
                <span class="ts-knob">
                    <i data-lucide="sun" class="ts-sun"></i>
                    <i data-lucide="moon" class="ts-moon"></i>
                </span>
            </button>

            {{-- ── Notification Bell ──────────────────────────────────────── --}}
            <div class="notif-wrapper" id="notifWrapper">
                <i data-lucide="bell"></i>
                <span class="notif-badge" id="notifBadge" style="display:none;"></span>

                {{-- Dropdown panel --}}
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dd-header">
                        <span class="notif-dd-title">Notifications</span>
                        <div class="notif-dd-actions">
                            <button class="notif-dd-mark-all" id="notifMarkAll" type="button">Mark all read</button>
                            <button class="notif-dd-delete-all" id="notifDeleteAll" type="button">Delete all</button>
                        </div>
                    </div>
                    <div class="notif-dd-list" id="notifList">
                        <div class="notif-dd-empty">Loading…</div>
                    </div>
                </div>
            </div>

            <div class="dropdown">
                <div class="profile-capsule" data-bs-toggle="dropdown">
                    <div class="avatar-box">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()->name ?? 'Admin') }}&background=6366f1&color=fff&bold=true" alt="User">
                    </div>
                    <div class="profile-info d-none d-md-block">
                        <span class="u-name">{{ auth()->user()->name ?? 'ADMIN123' }}</span>
                        <span class="u-role">Project Manager</span>
                    </div>
                    <i data-lucide="chevron-down" class="chevron"></i>
                </div>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button class="dropdown-item text-danger" type="submit"><i data-lucide="power"></i> Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- PAGE CONTENT -->
    <div class="container-fluid py-4">
        @yield('content')
    </div>

</div>

<!-- FLOATING CHATBOT -->
<button id="chatbot-fab" class="chatbot-fab" title="Chat with Jeyanco AI">
    <i class="fas fa-robot"></i>
    <span class="fab-pulse-ring"></span>
</button>

<div id="chatbot-window" class="chatbot-window">
    <div class="chatbot-header">
        <div class="chatbot-header-left">
            <div class="chatbot-avatar-wrap">
                <i class="fas fa-robot"></i>
                <span class="cb-status-dot"></span>
            </div>
            <div>
                <span class="chatbot-name">Jeyanco AI</span>
                <span class="chatbot-status-text">Online &bull; Ready to help</span>
            </div>
        </div>
        <div class="chatbot-header-btns">
            <button id="chatbot-new-btn" class="cb-icon-btn" title="New Chat"><i class="fas fa-plus"></i></button>
            <button id="chatbot-minimize-btn" class="cb-icon-btn" title="Close"><i class="fas fa-times"></i></button>
        </div>
    </div>

    <div id="chatbot-messages" class="chatbot-messages">
        <div class="cb-welcome">
            <div class="cb-welcome-icon"><i class="fas fa-robot"></i></div>
            <div class="cb-welcome-text">
                <p><strong>Mabuhay!</strong> I'm Jeyanco AI</p>
                <p>Ask me about payroll, attendance, employees, and workforce data.</p>
            </div>
        </div>
        <div class="cb-quick-chips" id="cb-quick-chips">
            <button class="cb-chip" data-msg="Total employees">&#128101; Employees</button>
            <button class="cb-chip" data-msg="Dashboard overview">&#128202; Overview</button>
            <button class="cb-chip" data-msg="Attendance today">&#9989; Attendance</button>
            <button class="cb-chip" data-msg="help">&#10067; Help</button>
        </div>
    </div>

    <div class="chatbot-footer">
        <div class="cb-input-row">
            <input type="text" id="chatbot-input" class="cb-input" placeholder="Ask something..." autocomplete="off">
            <button id="chatbot-send" class="cb-send-btn"><i class="fas fa-paper-plane"></i></button>
        </div>
        <p class="cb-hint">Press Enter to send &nbsp;&middot;&nbsp; Powered by Jeyanco Intelligence</p>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Initialize lucide icons
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    document.addEventListener('shown.bs.modal', function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // Floating Chatbot
    try {
        const fab = document.getElementById('chatbot-fab');
        const chatWindow = document.getElementById('chatbot-window');
        const minimizeBtn = document.getElementById('chatbot-minimize-btn');
        const newChatBtn = document.getElementById('chatbot-new-btn');
        const chatbotInput = document.getElementById('chatbot-input');
        const chatbotSend = document.getElementById('chatbot-send');
        const messagesContainer = document.getElementById('chatbot-messages');

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        function getTime() {
            return new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }

        function appendUserMsg(text) {
            const d = document.createElement('div');
            d.className = 'chatbot-msg user';
            d.innerHTML = `<div class="msg-content"><div class="msg-bubble">${escapeHtml(text)}</div><div class="msg-time">${getTime()}</div></div><div class="msg-avatar"><i class="fas fa-user"></i></div>`;
            messagesContainer.appendChild(d);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function showTyping() {
            const d = document.createElement('div');
            d.className = 'chatbot-msg ai';
            d.id = 'cb-typing';
            d.innerHTML = `<div class="msg-avatar"><i class="fas fa-robot"></i></div><div class="typing-dots"><span></span><span></span><span></span></div>`;
            messagesContainer.appendChild(d);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function removeTyping() {
            const el = document.getElementById('cb-typing');
            if (el) el.remove();
        }

        function appendAiMsg(text) {
            const d = document.createElement('div');
            d.className = 'chatbot-msg ai';
            d.innerHTML = `<div class="msg-avatar"><i class="fas fa-robot"></i></div><div class="msg-content"><div class="msg-bubble">${escapeHtml(text)}</div><div class="msg-time">${getTime()}</div></div>`;
            messagesContainer.appendChild(d);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function sendMessage(message) {
            appendUserMsg(message);
            showTyping();
            fetch('ai/chat', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') },
                body: JSON.stringify({ message: message })
            })
            .then(r => r.json())
            .then(data => { removeTyping(); appendAiMsg(data.reply ?? 'Sorry, I could not process that.'); })
            .catch(err => { console.error('Chatbot error:', err); removeTyping(); appendAiMsg('Sorry, I encountered an error. Please try again.'); });
        }

        function attachChipListeners() {
            document.querySelectorAll('.cb-chip').forEach(chip => {
                chip.addEventListener('click', function() {
                    const msg = this.getAttribute('data-msg');
                    const chips = document.getElementById('cb-quick-chips');
                    if (chips) chips.remove();
                    sendMessage(msg);
                });
            });
        }

        function resetChat() {
            messagesContainer.innerHTML = `
                <div class="cb-welcome">
                    <div class="cb-welcome-icon"><i class="fas fa-robot"></i></div>
                    <div class="cb-welcome-text">
                        <p><strong>Mabuhay!</strong> I'm Jeyanco AI</p>
                        <p>Ask me about payroll, attendance, employees, and workforce data.</p>
                    </div>
                </div>
                <div class="cb-quick-chips" id="cb-quick-chips">
                    <button class="cb-chip" data-msg="Total employees">&#128101; Employees</button>
                    <button class="cb-chip" data-msg="Dashboard overview">&#128202; Overview</button>
                    <button class="cb-chip" data-msg="Attendance today">&#9989; Attendance</button>
                    <button class="cb-chip" data-msg="help">&#10067; Help</button>
                </div>`;
            attachChipListeners();
        }

        if (fab && chatWindow) {
            attachChipListeners();

            fab.addEventListener('click', function() {
                chatWindow.classList.toggle('open');
                if (chatWindow.classList.contains('open')) chatbotInput.focus();
            });

            minimizeBtn.addEventListener('click', function() {
                chatWindow.classList.remove('open');
            });

            newChatBtn.addEventListener('click', resetChat);

            chatbotSend.addEventListener('click', function() {
                const msg = chatbotInput.value.trim();
                if (msg) { chatbotInput.value = ''; sendMessage(msg); }
            });

            chatbotInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    const msg = chatbotInput.value.trim();
                    if (msg) { chatbotInput.value = ''; sendMessage(msg); }
                }
            });
        }
    } catch (error) {
        console.error('Chatbot initialization error:', error);
    }

    // ===== GLOBAL SEARCH FUNCTIONALITY =====
    const globalSearchInput = document.getElementById('global-search-input');
    const suggestionsDropdown = document.getElementById('search-suggestions');
    let searchTimeout;

    if (globalSearchInput) {
        // Show suggestions on input
        globalSearchInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                suggestionsDropdown.innerHTML = '';
                suggestionsDropdown.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`/search/suggestions?q=${encodeURIComponent(query)}`)
                   .then(async response => {
                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.reply || 'Server error');
                        }

                        return data;
                    })
                    .then(data => {
                        if (data.length > 0) {
                            let html = '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; z-index: 1000;">';
                            
                            let currentCategory = '';
                            data.forEach(item => {
                                if (item.category !== currentCategory) {
                                    if (currentCategory !== '') {
                                        html += '<div style="border-top: 1px solid #f0f0f0;"></div>';
                                    }
                                    html += `<div style="padding: 8px 12px; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; background: #f8fafc;">${item.category}</div>`;
                                    currentCategory = item.category;
                                }
                                
                                html += `<a href="${item.url}" style="display: block; padding: 10px 12px; color: inherit; text-decoration: none; transition: background 0.2s;" onmouseover="this.style.background='#f0f0f0'" onmouseout="this.style.background='transparent'">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <i data-lucide="${item.icon}" style="width: 16px; height: 16px; color: #1e3a8a;"></i>
                                        <span>${item.text}</span>
                                    </div>
                                </a>`;
                            });
                            
                            html += `<div style="border-top: 1px solid #f0f0f0; padding: 8px 12px;">
                                <a href="/search?q=${encodeURIComponent(query)}" style="display: block; color: #1e3a8a; text-decoration: none; font-weight: 600; font-size: 12px; transition: all 0.2s;" onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
                                    <i data-lucide="arrow-right" style="width: 14px; height: 14px; display: inline; margin-right: 6px;"></i>View All Results
                                </a>
                            </div>`;
                            
                            html += '</div>';
                            
                            suggestionsDropdown.innerHTML = html;
                            suggestionsDropdown.style.display = 'block';
                            if (typeof lucide !== 'undefined') {
                                lucide.createIcons();
                            }
                        } else {
                            suggestionsDropdown.innerHTML = '<div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; text-align: center; color: #999;">No results found</div>';
                            suggestionsDropdown.style.display = 'block';
                        }
                    })
                    .catch(error => console.error('Search error:', error));
            }, 300);
        });

        // Submit search on Enter
        globalSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const query = this.value.trim();
                if (query.length >= 2) {
                    window.location.href = `/search?q=${encodeURIComponent(query)}`;
                }
            }
        });

        // Close suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target !== globalSearchInput && !globalSearchInput.contains(e.target)) {
                suggestionsDropdown.style.display = 'none';
            }
        });

        // Focus search with "/" key
        document.addEventListener('keydown', function(e) {
            if ((e.key === '/' || e.key === 'k') && (e.ctrlKey || e.metaKey) && !globalSearchInput.matches(':focus')) {
                e.preventDefault();
                globalSearchInput.focus();
            }
        });
    }

    // Add CSS for search suggestions
    const searchStyle = document.createElement('style');
    searchStyle.textContent = `
        .search-suggestions-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 4px;
            display: none;
            z-index: 1000;
        }

        .search-suggestions-dropdown a {
            cursor: pointer;
        }
    `;
    document.head.appendChild(searchStyle);

    // Ensure lucide icons are always rendered at the end
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    // Mobile sidebar toggle
    (function() {
        const toggle  = document.getElementById('sidebar-toggle');
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        if (!toggle || !sidebar || !overlay) return;

        function openSidebar() {
            sidebar.classList.add('active');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            sidebar.classList.remove('active');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }

        toggle.addEventListener('click', function() {
            sidebar.classList.contains('active') ? closeSidebar() : openSidebar();
        });
        overlay.addEventListener('click', closeSidebar);

        // Close on nav-link click (mobile UX)
        sidebar.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 1024) closeSidebar();
            });
        });
    })();

    // Theme toggle (dark / light) — init already ran in <head>
    (function() {
        const html   = document.documentElement;
        const toggle = document.getElementById('themeToggle');
        if (!toggle) return;

        function syncAria() {
            toggle.setAttribute('aria-checked', html.getAttribute('data-bs-theme') === 'dark' ? 'true' : 'false');
        }
        syncAria();

        toggle.addEventListener('click', function() {
            const next = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';

            // Enable smooth color transition only during the switch
            html.classList.add('theme-transition');
            html.setAttribute('data-bs-theme', next);
            try { localStorage.setItem('jeyanco-theme', next); } catch (e) {}
            syncAria();

            window.setTimeout(function() { html.classList.remove('theme-transition'); }, 350);
        });
    })();
</script>  

@stack('scripts')

{{-- ── Notification Bell — CSS ─────────────────────────────────────────────── --}}
<style>
/* Wrapper — position context for dropdown */
.notif-wrapper {
    position: relative;
    width: 38px; height: 38px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 10px; cursor: pointer;
    border: 1px solid #e2e8f0; background: #f8fafc;
    color: #475569; transition: background .15s, border-color .15s;
    flex-shrink: 0;
}
.notif-wrapper:hover,
.notif-wrapper.open { background: #f1f5f9; border-color: #cbd5e1; }
.notif-wrapper.open { color: #1e3a8a; border-color: #bfdbfe; background: #eff6ff; }

.notif-badge {
    position: absolute; top: 5px; right: 5px;
    min-width: 16px; height: 16px; border-radius: 8px;
    background: #dc2626; color: #fff;
    font-size: 10px; font-weight: 700; line-height: 16px;
    text-align: center; padding: 0 4px;
    border: 2px solid #fff;
    animation: notifPop .25s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

/* Dropdown panel */
.notif-dropdown {
    display: none; position: absolute;
    top: calc(100% + 10px); right: 0;
    width: 340px; max-height: 480px;
    background: #fff; border: 1px solid #e2e8f0;
    border-radius: 14px; box-shadow: 0 16px 48px rgba(0,0,0,.13);
    z-index: 9000; overflow: hidden;
    flex-direction: column;
}
.notif-wrapper.open .notif-dropdown { display: flex; }

.notif-dd-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 14px 16px 12px; border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}
.notif-dd-title {
    font-size: 13px; font-weight: 700; color: #0f172a;
}
.notif-dd-actions { display: flex; align-items: center; gap: 10px; }
.notif-dd-mark-all {
    font-size: 11px; font-weight: 600; color: #1e40af;
    background: none; border: none; cursor: pointer; padding: 0;
    transition: color .15s;
}
.notif-dd-mark-all:hover { color: #1e3a8a; }
.notif-dd-delete-all {
    font-size: 11px; font-weight: 600; color: #dc2626;
    background: none; border: none; cursor: pointer; padding: 0;
    transition: color .15s;
}
.notif-dd-delete-all:hover { color: #b91c1c; }

.notif-dd-list {
    overflow-y: auto; flex: 1;
}
.notif-dd-list::-webkit-scrollbar { width: 4px; }
.notif-dd-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }

/* Notification item */
.notif-item {
    display: flex; align-items: flex-start; gap: 11px;
    padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #f8fafc;
    transition: background .1s; text-decoration: none;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: #fafbff; }
.notif-item.unread:hover { background: #f0f4ff; }

.notif-icon-wrap {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; font-size: 14px; color: #fff;
}
.notif-item-body { flex: 1; min-width: 0; }
.notif-item-title {
    font-size: 13px; font-weight: 600; color: #0f172a;
    margin: 0 0 2px; line-height: 1.3;
}
.notif-item-msg {
    font-size: 12px; color: #64748b; margin: 0 0 4px;
    white-space: normal; line-height: 1.4;
}
.notif-item-time { font-size: 11px; color: #94a3b8; }
.notif-unread-dot {
    width: 7px; height: 7px; background: #3b82f6;
    border-radius: 50%; flex-shrink: 0; margin-top: 5px;
}

/* Empty / loading states */
.notif-dd-empty {
    padding: 32px 16px; text-align: center;
    font-size: 13px; color: #94a3b8;
}

/* Dark mode */
[data-bs-theme="dark"] .notif-wrapper {
    background: #151d2e; border-color: #283449; color: #9fb0c7;
}
[data-bs-theme="dark"] .notif-wrapper:hover,
[data-bs-theme="dark"] .notif-wrapper.open {
    background: #1c2740; border-color: #38465e; color: #e8edf5;
}
[data-bs-theme="dark"] .notif-badge { border-color: #151d2e; }
[data-bs-theme="dark"] .notif-dropdown {
    background: #151d2e; border-color: #283449;
    box-shadow: 0 16px 48px rgba(0,0,0,.45);
}
[data-bs-theme="dark"] .notif-dd-header { border-bottom-color: #1c2740; }
[data-bs-theme="dark"] .notif-dd-title  { color: #e8edf5; }
[data-bs-theme="dark"] .notif-item      { border-bottom-color: #1a2336; }
[data-bs-theme="dark"] .notif-item:hover { background: #1c2740; }
[data-bs-theme="dark"] .notif-item.unread { background: #172554; }
[data-bs-theme="dark"] .notif-item.unread:hover { background: #1e3a8a22; }
[data-bs-theme="dark"] .notif-item-title { color: #e8edf5; }
[data-bs-theme="dark"] .notif-item-msg  { color: #9fb0c7; }
[data-bs-theme="dark"] .notif-item-time { color: #6b7d96; }
[data-bs-theme="dark"] .notif-dd-empty  { color: #475569; }
[data-bs-theme="dark"] .notif-dd-list::-webkit-scrollbar-thumb { background: #283449; }

@keyframes notifPop {
    from { transform: scale(0); opacity: 0; }
    to   { transform: scale(1); opacity: 1; }
}

/* Sidebar pending-kiosk badge */
.nav-pending-badge {
    margin-left: auto;
    min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 10px; background: #f59e0b; color: #fff;
    font-size: 11px; font-weight: 700; line-height: 20px; text-align: center;
}
</style>

{{-- ── Notification Bell — JS ───────────────────────────────────────────────── --}}
<script>
(function () {
    const csrf      = document.querySelector('meta[name="csrf-token"]').content;
    const wrapper   = document.getElementById('notifWrapper');
    const badge     = document.getElementById('notifBadge');
    const list      = document.getElementById('notifList');
    const markAllBtn    = document.getElementById('notifMarkAll');
    const deleteAllBtn  = document.getElementById('notifDeleteAll');

    // ── Toggle open/close ──────────────────────────────────────────────────
    wrapper.addEventListener('click', function (e) {
        // Don't toggle if clicking a notification item or the mark-all button
        if (e.target.closest('.notif-item') || e.target.closest('.notif-dd-mark-all') || e.target.closest('.notif-dd-delete-all')) return;
        const isOpen = wrapper.classList.toggle('open');
        if (isOpen) render();
    });

    document.addEventListener('click', function (e) {
        if (!wrapper.contains(e.target)) wrapper.classList.remove('open');
    });

    // ── Fetch notifications ────────────────────────────────────────────────
    async function fetchNotifications() {
        try {
            const r    = await fetch('/notifications', {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf }
            });
            return await r.json();
        } catch { return null; }
    }

    // ── Render into dropdown ───────────────────────────────────────────────
    async function render() {
        list.innerHTML = '<div class="notif-dd-empty">Loading…</div>';
        const data = await fetchNotifications();
        if (!data) {
            list.innerHTML = '<div class="notif-dd-empty">Could not load notifications.</div>';
            return;
        }
        updateBadge(data.unread_count);
        if (data.notifications.length === 0) {
            list.innerHTML = '<div class="notif-dd-empty"><i class="fas fa-bell-slash" style="font-size:20px;margin-bottom:8px;display:block;"></i>No notifications yet</div>';
            return;
        }
        list.innerHTML = data.notifications.map(n => `
            <div class="notif-item ${n.read ? '' : 'unread'}" data-id="${n.id}" data-link="${escH(n.link)}">
                <div class="notif-icon-wrap" style="background:${escH(n.color)};">
                    <i class="fas ${escH(n.icon)}"></i>
                </div>
                <div class="notif-item-body">
                    <p class="notif-item-title">${escH(n.title)}</p>
                    <p class="notif-item-msg">${escH(n.message)}</p>
                    <span class="notif-item-time">${escH(n.created_at)}</span>
                </div>
                ${n.read ? '' : '<div class="notif-unread-dot"></div>'}
            </div>`).join('');

        // Click → mark read + navigate
        list.querySelectorAll('.notif-item').forEach(el => {
            el.addEventListener('click', async function () {
                const id   = this.dataset.id;
                const link = this.dataset.link;
                if (!this.classList.contains('read-pending')) {
                    this.classList.add('read-pending');
                    await fetch(`/notifications/${id}/read`, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                    }).catch(() => {});
                }
                wrapper.classList.remove('open');
                if (link && link !== '#') window.location.href = link;
            });
        });
    }

    // ── Badge update ───────────────────────────────────────────────────────
    function updateBadge(count) {
        if (count > 0) {
            badge.textContent    = count > 99 ? '99+' : count;
            badge.style.display  = 'flex';
            badge.style.alignItems = 'center';
            badge.style.justifyContent = 'center';
        } else {
            badge.style.display = 'none';
        }
    }

    // ── Mark all read ──────────────────────────────────────────────────────
    markAllBtn.addEventListener('click', async function () {
        await fetch('/notifications/read-all', {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        }).catch(() => {});
        render();
    });

    // ── Delete all ─────────────────────────────────────────────────────────
    deleteAllBtn.addEventListener('click', async function () {
        if (!confirm('Delete all notifications? This cannot be undone.')) return;
        await fetch('/notifications/delete-all', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
        }).catch(() => {});
        render();
    });

    // ── Poll unread count every 60 s ───────────────────────────────────────
    async function pollBadge() {
        const data = await fetchNotifications();
        if (data) updateBadge(data.unread_count);
    }

    pollBadge();   // initial load
    setInterval(pollBadge, 60000);

    function escH(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
})();
</script>

</body>
</html>