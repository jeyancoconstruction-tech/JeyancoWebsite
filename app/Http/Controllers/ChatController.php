<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Rule-Based Chat Handler
     * Uses same logic as AIController for consistency
     */
    public function chat(Request $request)
    {
        $message = trim($request->input('message'));
        $lower = strtolower($message);

        if (empty($message)) {
            return response()->json(['message' => 'Hello! How can I help you?']);
        }

        // PAYROLL QUERIES
        if (str_contains($lower, 'total payroll') || str_contains($lower, 'total salary')) {
            $total = DB::table('employees')->sum(DB::raw('rate_per_hour'));
            return response()->json(['message' => "📊 Total payroll budget: ₱" . number_format($total, 2)]);
        }

        // EMPLOYEE QUERIES
        if (str_contains($lower, 'total employees') || str_contains($lower, 'how many employees')) {
            $count = DB::table('employees')->count();
            return response()->json(['message' => "👥 There are $count employees in the system."]);
        }

        // SALARY QUERY
        if (str_contains($lower, 'salary of') || str_contains($lower, 'rate of')) {
            preg_match('/(?:salary of|rate of)\s+(.+?)(?:\?|$)/i', $message, $matches);
            if (isset($matches[1])) {
                $name = trim($matches[1]);
                $emp = DB::table('employees')->where('name', 'LIKE', "%$name%")->first();
                if ($emp) {
                    return response()->json(['message' => "💵 {$emp->name}'s rate: ₱" . number_format($emp->rate_per_hour, 2) . "/hour"]);
                }
            }
            return response()->json(['message' => "Which employee would you like to check?"]);
        }

        // ATTENDANCE QUERIES
        if (str_contains($lower, 'attendance') || str_contains($lower, 'present')) {
            $today = now()->toDateString();
            $with_records = DB::table('attendances')->whereDate('date', $today)->count();
            return response()->json(['message' => "✔️ $with_records attendance records today."]);
        }

        if (str_contains($lower, 'system info') || str_contains($lower, 'information')) {
            $employees = DB::table('employees')->count();
            $projects = DB::table('projects')->count();
            $info = "📱 System Information:\n👥 Employees: $employees\n📋 Projects: $projects";
            return response()->json(['message' => $info]);
        }

        // HELP
        if (str_contains($lower, 'help') || str_contains($lower, 'commands')) {
            $help = "❓ Available Commands:\n";
            $help .= "- 'Total payroll'\n";
            $help .= "- 'Total employees'\n";
            $help .= "- 'Salary of [name]'\n";
            $help .= "- 'Attendance'\n";
            $help .= "- 'System info'";
            return response()->json(['message' => $help]);
        }

        // DEFAULT FALLBACK
        return response()->json(['message' => "I didn't understand that. Try 'help'."]);
    }
}