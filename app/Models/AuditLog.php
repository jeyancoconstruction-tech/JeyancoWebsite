<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of who did what. Nothing in the application updates or
 * deletes a row, and the screen over it is read-only for everyone.
 */
class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'module', 'action', 'description',
        'subject_type', 'subject_id', 'ip_address', 'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeModule(Builder $q, ?string $module): Builder
    {
        return $module ? $q->where('module', $module) : $q;
    }

    /**
     * Write an entry. Deliberately swallows its own failures: a log that
     * cannot be written must never be the reason a payroll approval fails.
     */
    public static function record(
        string $module,
        string $action,
        string $description = '',
        ?Model $subject = null
    ): void {
        try {
            $user    = auth()->user();
            $request = request();

            static::create([
                'user_id'      => $user?->id,
                'user_name'    => $user?->name ?? $user?->username,
                'module'       => $module,
                'action'       => $action,
                'description'  => $description,
                'subject_type' => $subject ? class_basename($subject) : null,
                'subject_id'   => $subject?->getKey(),
                'ip_address'   => $request?->ip(),
                'user_agent'   => substr((string) $request?->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** The colour a module's rows carry in the log, reusing the app's palette. */
    public function getToneAttribute(): string
    {
        return match (strtolower($this->action)) {
            'created', 'approved', 'finalized', 'restored' => 'ok',
            'deleted', 'rejected', 'cancelled'             => 'danger',
            'updated', 'recalculated', 'reopened'          => 'warn',
            default                                        => 'muted',
        };
    }
}
