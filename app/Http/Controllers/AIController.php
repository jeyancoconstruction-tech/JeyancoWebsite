<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ChatMessage;

class AIController extends Controller
{
    /**
     * Chat endpoint - handles incoming messages
     */
    public function chat(Request $request)
    {
        try {
            $message = trim($request->message ?? '');
            $user = auth()->user();

            if (empty($message)) {
                return response()->json(['reply' => 'Hello! How can I help you today?']);
            }

            // Save user message
            ChatMessage::create([
                'user_id' => $user->id,
                'message' => $message,
                'type' => 'user'
            ]);

            // Generate reply
            $reply = $this->generateReply($message);

            // Save AI response
            ChatMessage::create([
                'user_id' => $user->id,
                'message' => $reply,
                'type' => 'ai'
            ]);

            return response()->json(['reply' => $reply]);

        } catch (\Exception $e) {
            Log::error('AI Chat Error: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return response()->json(['reply' => 'Sorry, I encountered an error. Please try again.']);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  UNDERSTANDING LAYER
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalize a raw message so many phrasings collapse to the same words.
     *
     *  - lowercases and strips punctuation
     *  - maps singular → plural and synonyms to canonical words
     *    ("worker"/"staff" → "employees", "how many employee" → "employees", …)
     *
     * This is what lets "how many employee?" match "how many employees".
     */
    private function normalize(string $text): string
    {
        $t = strtolower(trim($text));

        // Turn any punctuation into spaces, then collapse runs of whitespace.
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);
        $t = trim($t);
        if ($t === '') return $t;

        // canonical word => list of variants / synonyms / common typos
        $map = [
            'employees'  => ['employee', 'employ', 'employe', 'employee', 'emp', 'emps',
                             'worker', 'workers', 'staff', 'staffs', 'personnel',
                             'manpower', 'laborer', 'laborers', 'crew', 'people'],
            'salary'     => ['salaries', 'wage', 'wages', 'paycheck', 'compensation', 'earnings'],
            'attendance' => ['attendances', 'attendence', 'attendnce'],
            'sites'      => ['site', 'branch', 'branches'],
            'users'      => ['user', 'account', 'accounts'],
            'projects'   => ['project'],
            'settings'   => ['setting', 'configuration', 'config'],
            'deduction'  => ['deductions'],
            'total'      => ['count', 'number', 'num'],
            'list'       => ['show', 'display', 'view'],
            'absent'     => ['absents', 'absence', 'absentee', 'absentees'],
            'pagibig'    => ['pag ibig', 'pag-ibig'],
        ];

        foreach ($map as $canonical => $variants) {
            foreach ($variants as $v) {
                $t = preg_replace('/\b' . preg_quote($v, '/') . '\b/u', $canonical, $t);
            }
        }

        return $t;
    }

    /** True when every needle appears in the haystack. */
    private function all(string $hay, array $needles): bool
    {
        foreach ($needles as $n) {
            if (!str_contains($hay, $n)) return false;
        }
        return true;
    }

    /** True when any needle appears in the haystack. */
    private function any(string $hay, array $needles): bool
    {
        foreach ($needles as $n) {
            if (str_contains($hay, $n)) return true;
        }
        return false;
    }

    /**
     * Active workforce query (excludes kiosk "pending" detections and
     * soft-deleted / archived workers) — the correct base for head counts,
     * payroll totals and attendance.
     */
    private function employees()
    {
        return DB::table('employees')
            ->whereNull('deleted_at')
            ->where('status', 'active');
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  REPLY GENERATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate a reply based on user message.
     */
    private function generateReply($message)
    {
        $n = $this->normalize($message);          // normalized text for matching
        $has = fn(...$w) => $this->all($n, $w);    // all words present
        $any = fn(...$w) => $this->any($n, $w);    // any word present

        if ($n === '') {
            return "Please type a question. Type 'help' to see what I can answer.";
        }

        // ===== SMALL TALK / IDENTITY =====
        if ($any('hello', 'hi ', 'hey', 'kumusta', 'kamusta', 'mabuhay', 'good morning', 'good afternoon', 'good evening') || $n === 'hi') {
            return "Mabuhay! 👋 I'm Jeyanco AI. Ask me anything about your employees, payroll, attendance, sites or settings. Type 'help' for a list.";
        }
        if ($any('thank', 'salamat', 'thanks')) {
            return "You're welcome! 😊 Anything else you'd like to know?";
        }
        if ($any('who are you', 'what are you', 'your name')) {
            return "I'm Jeyanco AI — your workforce & payroll assistant. I read your system data and answer questions about employees, payroll, attendance, sites, users and settings.";
        }
        if ($any('what can you', 'what do you', 'how to use', 'how do i use')) {
            return $this->helpText();
        }

        // ===== PAYROLL =====
        if ($has('total', 'payroll') || $has('total', 'salary') || $any('payroll budget', 'salary budget')) {
            $sumHourly = $this->employees()->sum('rate_per_hour');
            $monthly   = $sumHourly * 160; // ~160 working hours / month
            return "PAYROLL BUDGET\n━━━━━━━━━━━━━━━\n"
                . "Sum of hourly rates: ₱" . number_format($sumHourly, 2) . "/hr\n"
                . "Est. monthly budget: ₱" . number_format($monthly, 2) . "\n"
                . "(160 hrs/month per worker)";
        }

        if ($any('average rate', 'average salary', 'avg rate', 'avg salary', 'mean rate')) {
            $avg = $this->employees()->avg('rate_per_hour');
            return "Average hourly rate: ₱" . number_format((float) $avg, 2) . "/hour";
        }

        if ($any('highest', 'highest paid', 'top paid', 'most paid', 'biggest salary')) {
            $maxRate = $this->employees()->max('rate_per_hour');
            if ($maxRate === null) return "No employees found.";
            $emps = $this->employees()->where('rate_per_hour', $maxRate)->orderBy('name')->get();
            $rate = "₱" . number_format($maxRate, 2) . "/hour";
            if ($emps->count() === 1) {
                return "Highest paid: {$emps[0]->name} ({$rate})";
            }
            $list = "Highest paid ({$rate}) — {$emps->count()} employees:\n";
            foreach ($emps as $e) $list .= "  • {$e->name}\n";
            return rtrim($list);
        }

        if ($any('lowest', 'minimum rate', 'lowest paid', 'least paid', 'cheapest')) {
            $minRate = $this->employees()->min('rate_per_hour');
            if ($minRate === null) return "No employees found.";
            $emps = $this->employees()->where('rate_per_hour', $minRate)->orderBy('name')->get();
            $rate = "₱" . number_format($minRate, 2) . "/hour";
            if ($emps->count() === 1) {
                return "Lowest paid: {$emps[0]->name} ({$rate})";
            }
            $list = "Lowest paid ({$rate}) — {$emps->count()} employees:\n";
            foreach ($emps as $e) $list .= "  • {$e->name}\n";
            return rtrim($list);
        }

        if ($any('daily payroll', 'daily rate', 'daily budget', 'daily estimate', 'daily cost')) {
            $today = now()->toDateString();
            $present = DB::table('attendances')->whereDate('date', $today)->distinct('employee_id')->count('employee_id');
            $avgRate = $this->employees()->avg('rate_per_hour') ?? 0;
            $estimated = $present * $avgRate * 8;
            return "DAILY PAYROLL ESTIMATE\n━━━━━━━━━━━━━━━\n"
                . "Present today:   $present workers\n"
                . "Avg rate:        ₱" . number_format((float) $avgRate, 2) . "/hr\n"
                . "Est. daily cost: ₱" . number_format($estimated, 2) . "\n(Assumes 8-hour shift)";
        }

        if ($any('payroll report', 'payroll summary')) {
            $count = $this->employees()->count();
            $sum   = $this->employees()->sum('rate_per_hour');
            $avg   = $this->employees()->avg('rate_per_hour');
            return "PAYROLL REPORT\n━━━━━━━━━━━━━━━\n"
                . "Total employees:      $count\n"
                . "Est. monthly budget:  ₱" . number_format($sum * 160, 2) . "\n"
                . "Average hourly rate:  ₱" . number_format((float) $avg, 2);
        }

        if ($any('total vale', 'outstanding vale', 'vale balance', 'total loans', 'cash advance')) {
            $totalVale = $this->employees()->sum('vale');
            $withVale  = $this->employees()->where('vale', '>', 0)->count();
            return "VALE SUMMARY\n━━━━━━━━━━━━━━━\n"
                . "Total outstanding:  ₱" . number_format($totalVale, 2) . "\n"
                . "Employees with vale: $withVale";
        }

        // Salary / rate / vale of a specific person (also handles "how much …")
        if ($any('salary of', 'rate of', 'wage of', 'pay of', 'vale of', 'how much')) {
            $emp = $this->findEmployee($message);
            if ($emp) {
                $wantsVale = $any('vale', 'loan', 'advance');
                if ($wantsVale) {
                    return "{$emp->name}'s vale balance: ₱" . number_format($emp->vale, 2);
                }
                return "{$emp->name}'s rate: ₱" . number_format($emp->rate_per_hour, 2) . "/hour"
                    . ($emp->position ? " ({$emp->position})" : "");
            }
            // Fall through to generic lookup / fallback if no name resolved.
        }

        // ===== EMPLOYEE LOOKUP BY NAME ("who is X", "info about X") =====
        // Exclude attendance-style questions ("who is absent/present/here") so
        // those fall through to the attendance handlers below.
        if ($any('who is', 'info about', 'information about', 'details of', 'details about',
                 'tell me about', 'profile of', 'about employee', 'find employee', 'search employee')
            && ! $any('absent', 'present', 'here', 'clocked', 'time in', 'time out', 'online', 'working today')) {
            $emp = $this->findEmployee($message);
            if ($emp) return $this->employeeCard($emp);
            return "I couldn't find that employee. Try the exact name, or ask 'list all employees'.";
        }

        // ===== EMPLOYEE COUNTS & LISTS =====
        if (($has('total', 'employees') || $has('how many', 'employees') || $n === 'employees')
            && ! $any('site', 'labor', 'position')) {
            $count = $this->employees()->count();
            return "There are $count active employees in the system.";
        }

        if ($any('pending employees', 'pending worker', 'unregistered', 'awaiting registration', 'kiosk detected')) {
            $pending = DB::table('employees')->whereNull('deleted_at')->where('status', 'pending')->count();
            return "Pending employees (detected by a kiosk, not yet fully registered): $pending\nReview them on the Register & Manage page.";
        }

        if ($any('archived employees', 'archived worker', 'former employees', 'ex employees', 'left the company')) {
            $archived = DB::table('employees')->whereNull('deleted_at')->where('status', 'archived')->count();
            return "Archived employees (left the company, records preserved): $archived";
        }

        if ($has('employees', 'site') || $any('employees by site', 'employees per site', 'staff per site')) {
            $sites = DB::table('sites')
                ->leftJoin('employees', function ($j) {
                    $j->on('sites.id', '=', 'employees.site_id')
                      ->whereNull('employees.deleted_at')
                      ->where('employees.status', 'active');
                })
                ->groupBy('sites.id', 'sites.name')
                ->select('sites.name', DB::raw('count(employees.id) as count'))
                ->orderBy('sites.name')
                ->get();
            if ($sites->count() > 0) {
                $list = "EMPLOYEES BY SITE\n━━━━━━━━━━━━━━━\n";
                foreach ($sites as $s) {
                    $list .= "• {$s->name}: {$s->count} employee" . ($s->count != 1 ? 's' : '') . "\n";
                }
                return rtrim($list);
            }
            return "No sites found.";
        }

        if ($any('by labor', 'labor type') && $any('employees', 'how many', 'total', 'per', 'by')) {
            $types = DB::table('labor_types')
                ->leftJoin('employees', function ($j) {
                    $j->on('labor_types.id', '=', 'employees.labor_type_id')
                      ->whereNull('employees.deleted_at')
                      ->where('employees.status', 'active');
                })
                ->groupBy('labor_types.id', 'labor_types.name')
                ->select('labor_types.name', DB::raw('count(employees.id) as count'))
                ->orderBy('labor_types.name')
                ->get();
            if ($types->count() > 0) {
                $list = "EMPLOYEES BY LABOR TYPE\n━━━━━━━━━━━━━━━\n";
                foreach ($types as $t) {
                    $list .= "• {$t->name}: {$t->count} employee" . ($t->count != 1 ? 's' : '') . "\n";
                }
                return rtrim($list);
            }
            return "No labor types found.";
        }

        if ($any('list employees', 'all employees', 'list all employees', 'show employees',
                 'employee list', 'list list employees')) {
            $employees = $this->employees()->orderBy('name')->select('name', 'position', 'rate_per_hour')->limit(15)->get();
            $total = $this->employees()->count();
            if ($employees->count() > 0) {
                $list = "EMPLOYEE LIST (showing " . $employees->count() . " of $total)\n━━━━━━━━━━━━━━━\n";
                foreach ($employees as $emp) {
                    $list .= "• {$emp->name} — " . ($emp->position ?: 'N/A') . " @ ₱" . number_format($emp->rate_per_hour, 2) . "/hr\n";
                }
                if ($total > 15) $list .= "…and " . ($total - 15) . " more.";
                return rtrim($list);
            }
            return "No employees found.";
        }

        // ===== ATTENDANCE =====
        if ($any('absent', 'not present', 'who is absent', 'no show')) {
            $today = now()->toDateString();
            $total = $this->employees()->count();
            $presentIds = DB::table('attendances')->whereDate('date', $today)->pluck('employee_id')->unique();
            $absent = $this->employees()->whereNotIn('id', $presentIds)->count();
            $present = $total - $absent;
            return "ABSENCE SUMMARY — " . now()->format('M d, Y') . "\n━━━━━━━━━━━━━━━\n"
                . "Absent:  $absent employees\nPresent: $present employees\nTotal:   $total employees";
        }

        if ($any('weekly attendance', 'this week attendance', 'week attendance')) {
            $weekStart = now()->startOfWeek()->toDateString();
            $weekEnd   = now()->endOfWeek()->toDateString();
            $weekCount = DB::table('attendances')->whereBetween('date', [$weekStart, $weekEnd])->count();
            $total     = $this->employees()->count();
            $daysPassed = max(now()->dayOfWeek, 1);
            $avgDaily  = round($weekCount / $daysPassed);
            return "WEEKLY ATTENDANCE\n━━━━━━━━━━━━━━━\n"
                . "Week:          $weekStart to $weekEnd\n"
                . "Total records: $weekCount\n"
                . "Avg per day:   $avgDaily / $total employees";
        }

        if ($any('attendance report', 'attendance summary')) {
            $today = now()->toDateString();
            $weekStart = now()->startOfWeek()->toDateString();
            $todayCount = DB::table('attendances')->whereDate('date', $today)->distinct('employee_id')->count('employee_id');
            $weekCount  = DB::table('attendances')->whereDate('date', '>=', $weekStart)->count();
            $total = $this->employees()->count();
            $rate  = $total > 0 ? round(($todayCount / $total) * 100, 1) : 0;
            return "ATTENDANCE REPORT\n━━━━━━━━━━━━━━━\n"
                . "Today:      $todayCount / $total ($rate%)\n"
                . "This week:  $weekCount records";
        }

        if ($any('time in', 'time out', 'clocked out', 'clock out', 'still working')) {
            $today = now()->toDateString();
            $withOut  = DB::table('attendances')->whereDate('date', $today)->whereNotNull('time_out')->count();
            $noOut    = DB::table('attendances')->whereDate('date', $today)->whereNull('time_out')->count();
            return "TIME OUT STATUS (today)\n━━━━━━━━━━━━━━━\nClocked out: $withOut\nStill in:    $noOut";
        }

        // Present today / general attendance (kept last among attendance so
        // more specific ones above win first).
        if ($any('attendance', 'present', 'who is here', 'clocked in', 'time today') || $has('who is', 'in')) {
            $today = now()->toDateString();
            $total = $this->employees()->count();
            $records = DB::table('attendances')->whereDate('date', $today)->distinct('employee_id')->count('employee_id');
            $rate = $total > 0 ? round(($records / $total) * 100, 1) : 0;
            return "ATTENDANCE (Today " . now()->format('M d, Y') . ")\n━━━━━━━━━━━━━━━\n"
                . "Present: $records / $total employees\nRate:    $rate%";
        }

        // ===== SITES =====
        if ($has('total', 'sites') || $has('how many', 'sites')) {
            return "There are " . DB::table('sites')->count() . " sites in the system.";
        }
        if ($any('list sites', 'all sites', 'active sites', 'list list sites')) {
            $sites = DB::table('sites')->orderBy('name')->get();
            if ($sites->count() > 0) {
                $list = "SITES ({$sites->count()})\n━━━━━━━━━━━━━━━\n";
                foreach ($sites as $s) {
                    $loc = property_exists($s, 'location') && $s->location ? " — {$s->location}" : '';
                    $list .= "• {$s->name}{$loc}\n";
                }
                return rtrim($list);
            }
            return "No sites found.";
        }

        // ===== LABOR TYPES =====
        if ($any('labor type', 'labor rates', 'daily rates')) {
            $types = DB::table('labor_types')->orderBy('name')->get();
            if ($types->count() > 0) {
                $list = "LABOR TYPES ({$types->count()})\n━━━━━━━━━━━━━━━\n";
                foreach ($types as $t) {
                    $list .= "• {$t->name} — ₱" . number_format($t->daily_rate ?? 0, 2) . "/day\n";
                }
                return rtrim($list);
            }
            return "No labor types found.";
        }

        // ===== USERS / SECURITY =====
        if ($has('total', 'users') || $has('how many', 'users')) {
            return "Total system users: " . DB::table('users')->count();
        }
        if ($any('admin users', 'admins', 'administrators')) {
            $admins  = DB::table('users')->where('is_admin', 1)->count();
            $regular = DB::table('users')->where('is_admin', 0)->count();
            return "Admin users:   $admins\nRegular users: $regular";
        }
        if ($any('list users', 'all users')) {
            $users = DB::table('users')->select('name', 'email', 'is_admin')->orderBy('name')->get();
            if ($users->count() > 0) {
                $list = "SYSTEM USERS ({$users->count()})\n━━━━━━━━━━━━━━━\n";
                foreach ($users as $u) {
                    $list .= "• {$u->name} ({$u->email}) " . ($u->is_admin ? '[Admin]' : '[User]') . "\n";
                }
                return rtrim($list);
            }
            return "No users found.";
        }
        if ($any('security overview', 'security summary', 'access control')) {
            $total = DB::table('users')->count();
            $admins = DB::table('users')->where('is_admin', 1)->count();
            return "SECURITY OVERVIEW\n━━━━━━━━━━━━━━━\n"
                . "Total accounts: $total\nAdministrators: $admins\nStandard users: " . ($total - $admins);
        }

        // ===== SETTINGS / DEDUCTIONS =====
        $needsSettings = $any('system settings', 'payroll settings', 'deduction', 'all rates', 'deduction rate',
                              'sss', 'philhealth', 'pagibig', 'contribution', 'ot multiplier',
                              'overtime rate', 'holiday rate', 'bonus', 'rest day')
                       || $has('settings', 'system');

        if ($needsSettings) {
            $s = DB::table('settings')->first();
            if (!$s) {
                return "No settings configured yet. Open Settings → Payroll Settings to set them up.";
            }
            $sss  = number_format($s->sss ?? 0, 2);
            $phil = number_format($s->philhealth ?? 0, 2);
            $pag  = number_format($s->pagibig ?? 0, 2);

            $general = $any('all', 'deduction', 'system settings', 'settings') && ! $any('only', 'just');

            if (! $general && $any('sss')) return "SSS contribution rate: {$sss}%";
            if (! $general && $any('philhealth')) return "PhilHealth contribution rate: {$phil}%";
            if (! $general && $any('pagibig')) return "PAG-IBIG contribution rate: {$pag}%";

            if ($any('deduction', 'all rates') && ! $any('system settings', 'payroll settings')) {
                return "DEDUCTION RATES\n━━━━━━━━━━━━━━━\nSSS:        {$sss}%\nPhilHealth: {$phil}%\nPAG-IBIG:   {$pag}%";
            }

            $daily   = number_format($s->daily_rate ?? 0, 2);
            $ot      = number_format($s->ot_multiplier ?? 1.25, 2);
            $holiday = number_format($s->holiday_multiplier ?? 2, 2);
            $bonus   = number_format($s->bonus ?? 0, 2);
            $rest    = (isset($s->sunday_rest_day_enabled) && $s->sunday_rest_day_enabled) ? 'On (Sunday)' : 'Off';

            return "SYSTEM SETTINGS\n━━━━━━━━━━━━━━━\n"
                . "Daily rate:         ₱{$daily}\n"
                . "OT multiplier:      {$ot}×\n"
                . "Holiday multiplier: {$holiday}×\n"
                . "Bonus / period:     ₱{$bonus}\n"
                . "Sunday rest day:    {$rest}\n"
                . "SSS:                {$sss}%\n"
                . "PhilHealth:         {$phil}%\n"
                . "PAG-IBIG:           {$pag}%";
        }

        // ===== HOLIDAYS =====
        if ($any('holiday', 'holidays')) {
            $upcoming = DB::table('holidays')->whereDate('date', '>=', now()->toDateString())->orderBy('date')->limit(5)->get();
            $total = DB::table('holidays')->count();
            if ($upcoming->count() > 0) {
                $list = "UPCOMING HOLIDAYS (of $total total)\n━━━━━━━━━━━━━━━\n";
                foreach ($upcoming as $h) {
                    $d = \Carbon\Carbon::parse($h->date)->format('M d, Y');
                    $list .= "• $d" . ($h->title ? " — {$h->title}" : '') . "\n";
                }
                return rtrim($list);
            }
            return "No upcoming holidays are configured ($total total on record).";
        }

        // ===== ANALYTICS =====
        if ($any('employee statistics', 'employee stats') || $has('analytics', 'employees')) {
            $total = $this->employees()->count();
            $bySite = DB::table('employees')
                ->leftJoin('sites', 'employees.site_id', '=', 'sites.id')
                ->whereNull('employees.deleted_at')
                ->where('employees.status', 'active')
                ->groupBy('sites.name')
                ->select('sites.name', DB::raw('count(*) as count'))
                ->get();
            $stats = "EMPLOYEE STATISTICS\n━━━━━━━━━━━━━━━\nTotal: $total employees\n";
            if ($bySite->count() > 0) {
                $stats .= "\nBy site:\n";
                foreach ($bySite as $s) {
                    $name = $s->name ?? 'Unassigned';
                    $pct  = $total > 0 ? round(($s->count / $total) * 100, 1) : 0;
                    $stats .= "• {$name}: {$s->count} ($pct%)\n";
                }
            }
            return rtrim($stats);
        }

        // ===== SYSTEM STATUS / DASHBOARD =====
        if ($any('system status', 'system health', 'is the system', 'is it working', 'is it online')) {
            return "SYSTEM STATUS\n━━━━━━━━━━━━━━━\n"
                . "✅ Database:     Online\n"
                . "👥 Employees:    " . $this->employees()->count() . "\n"
                . "📍 Sites:        " . DB::table('sites')->count() . "\n"
                . "👤 Users:        " . DB::table('users')->count() . "\n"
                . "📅 Att. records: " . DB::table('attendances')->count() . " total";
        }

        if ($any('database summary', 'db summary', 'total records')) {
            $e = $this->employees()->count();
            $s = DB::table('sites')->count();
            $a = DB::table('attendances')->count();
            $u = DB::table('users')->count();
            return "DATABASE SUMMARY\n━━━━━━━━━━━━━━━\n"
                . "Employees:     $e\nSites:         $s\nAttendances:   $a\nUsers:         $u\n"
                . "──────────────\nTotal records: " . ($e + $s + $a + $u);
        }

        if ($any('dashboard', 'overview', 'summary', 'system info', 'information')) {
            $e = $this->employees()->count();
            $s = DB::table('sites')->count();
            $u = DB::table('users')->count();
            $today = DB::table('attendances')->whereDate('date', now()->toDateString())->distinct('employee_id')->count('employee_id');
            return "DASHBOARD OVERVIEW\n━━━━━━━━━━━━━━━\n"
                . "👥 Employees:        $e\n"
                . "📍 Sites:            $s\n"
                . "👨‍💼 System users:      $u\n"
                . "✅ Present today:    $today";
        }

        // ===== HELP =====
        if ($any('help', 'commands', 'menu', 'options')) {
            return $this->helpText();
        }

        // ===== SMART FALLBACK =====
        return $this->fallback($n);
    }

    /**
     * Find an employee mentioned in a free-form message.
     * Strips common lead-in phrases, then matches the remaining name.
     */
    private function findEmployee(string $message)
    {
        $name = strtolower($message);
        $strip = [
            'what is', 'whats', "what's", 'how much is', 'how much', 'tell me about',
            'salary of', 'rate of', 'wage of', 'pay of', 'vale of', 'who is',
            'information about', 'info about', 'details of', 'details about',
            'profile of', 'about employee', 'find employee', 'search employee',
            'the salary', 'the rate', 'employee', 'salary', 'rate', 'vale', 'loan',
            'per hour', 'hourly', '?', '.', '!',
        ];
        foreach ($strip as $s) {
            $name = str_replace($s, ' ', $name);
        }
        // Remove filler words as whole tokens only, so names aren't corrupted.
        $fillers = ['does', 'do', 'make', 'makes', 'earn', 'earns', 'get', 'gets',
                    'paid', 'the', 'is', 'of', 'for', 'per', 'about', 'me'];
        foreach ($fillers as $f) {
            $name = preg_replace('/\b' . $f . '\b/', ' ', $name);
        }
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if (strlen($name) < 2) return null;

        return $this->employees()
            ->where('name', 'LIKE', "%$name%")
            ->orderByRaw('CHAR_LENGTH(name)')
            ->first();
    }

    /** Rich detail card for a single employee. */
    private function employeeCard($emp): string
    {
        $site = $emp->site_id ? DB::table('sites')->where('id', $emp->site_id)->value('name') : null;
        $labor = $emp->labor_type_id ? DB::table('labor_types')->where('id', $emp->labor_type_id)->value('name') : null;

        $card = "EMPLOYEE PROFILE\n━━━━━━━━━━━━━━━\n";
        $card .= "Name:       {$emp->name}\n";
        $card .= "Position:   " . ($emp->position ?: 'N/A') . "\n";
        $card .= "Rate:       ₱" . number_format($emp->rate_per_hour, 2) . "/hour\n";
        $card .= "Site:       " . ($site ?: 'Unassigned') . "\n";
        $card .= "Labor type: " . ($labor ?: 'N/A') . "\n";
        $card .= "Vale:       ₱" . number_format($emp->vale, 2) . "\n";
        $card .= "Status:     " . ucfirst($emp->status);
        return $card;
    }

    /** Suggest likely topics based on words the user did use. */
    private function fallback(string $n): string
    {
        $hints = [];
        if ($this->any($n, ['employees', 'salary', 'position'])) {
            $hints[] = "• 'How many employees'  • 'List all employees'  • 'Salary of [name]'";
        }
        if ($this->any($n, ['payroll', 'budget', 'cost', 'money'])) {
            $hints[] = "• 'Total payroll'  • 'Daily payroll estimate'  • 'Payroll report'";
        }
        if ($this->any($n, ['attendance', 'present', 'absent', 'today'])) {
            $hints[] = "• 'Attendance today'  • 'Who is absent'  • 'Weekly attendance'";
        }
        if ($this->any($n, ['sites', 'sss', 'philhealth', 'pagibig', 'deduction', 'settings'])) {
            $hints[] = "• 'List sites'  • 'Deduction rates'  • 'System settings'";
        }

        if ($hints) {
            return "I'm not sure I got that. Did you mean:\n" . implode("\n", $hints);
        }

        return "I didn't quite understand that. I can answer questions about employees, "
            . "payroll, attendance, sites, users and settings.\nType 'help' to see everything I can do.";
    }

    /** The full command menu. */
    private function helpText(): string
    {
        return "AVAILABLE QUESTIONS\n━━━━━━━━━━━━━━━\n\n"
            . "👷 WORKFORCE\n"
            . "  • How many employees\n"
            . "  • List all employees\n"
            . "  • Employees by site / labor type\n"
            . "  • Who is [name] / Salary of [name]\n"
            . "  • Highest / lowest paid\n"
            . "  • Total vale balance\n"
            . "  • Pending / archived employees\n\n"
            . "💰 PAYROLL\n"
            . "  • Total payroll\n"
            . "  • Average rate\n"
            . "  • Daily payroll estimate\n"
            . "  • Payroll report\n\n"
            . "✅ ATTENDANCE\n"
            . "  • Attendance today\n"
            . "  • Who is absent\n"
            . "  • Weekly attendance\n"
            . "  • Time in / out status\n\n"
            . "📍 SITES & LABOR\n"
            . "  • List sites\n"
            . "  • Labor types\n\n"
            . "🔐 USERS & SECURITY\n"
            . "  • Total users / Admin users\n"
            . "  • Security overview\n\n"
            . "⚙️ SETTINGS\n"
            . "  • System settings\n"
            . "  • Deduction rates (SSS / PhilHealth / PAG-IBIG)\n"
            . "  • Holidays\n\n"
            . "🖥️ SYSTEM\n"
            . "  • Dashboard overview\n"
            . "  • System status\n"
            . "  • Database summary";
    }

    /**
     * Get chat history for the last 30 minutes
     */
    public function history()
    {
        try {
            $user = auth()->user();
            $thirtyMinutesAgo = now()->subMinutes(30);

            $messages = ChatMessage::where('user_id', $user->id)
                ->where('created_at', '>=', $thirtyMinutesAgo)
                ->orderBy('created_at', 'asc')
                ->get(['message', 'type', 'created_at']);

            return response()->json(['messages' => $messages]);
        } catch (\Exception $e) {
            Log::error('AI History Error: ' . $e->getMessage());
            return response()->json(['messages' => []]);
        }
    }

    /**
     * Clear old messages (older than 30 minutes)
     */
    public function clearOldMessages()
    {
        try {
            $thirtyMinutesAgo = now()->subMinutes(30);
            ChatMessage::where('created_at', '<', $thirtyMinutesAgo)->delete();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('AI Clear Error: ' . $e->getMessage());
            return response()->json(['status' => 'error']);
        }
    }
}
