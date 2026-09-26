{{-- The quick prompts: categories down the side, their prompts beside them.
     Shared by the Jeyanco AI page and the chat's full-screen view, so the
     two never offer different lists. Each includer wires the clicks itself,
     inside its own panel. --}}
<div class="prompts-panel-inner">
    <p class="prompts-panel-label">{{ __('QUICK ACTIONS') }}</p>

    {{-- Category Navigation --}}
    <div class="category-nav">
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
    <div class="prompts-body">

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
