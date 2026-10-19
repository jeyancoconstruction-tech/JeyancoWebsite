<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A complaint a worker posted at the kiosk, and the office's answer. */
class Complaint extends Model
{
    public const OPEN     = 'open';
    public const RESOLVED = 'resolved';

    /** What a complaint can be about, as the kiosk's buttons and the web's filter name them. */
    public const CATEGORIES = [
        'pay'        => 'Pay / Sahod',
        'attendance' => 'Attendance / DTR',
        'safety'     => 'Safety',
        'equipment'  => 'Tools & equipment',
        'treatment'  => 'Treatment at work',
        'other'      => 'Other',
    ];

    /** How many one worker may post in a day — enough for anyone, too few to flood the office. */
    public const DAILY_LIMIT = 5;

    protected $fillable = [
        'employee_id', 'site_id', 'kiosk_id', 'category', 'message',
        'status', 'response', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function kiosk()
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? ucfirst((string) $this->category);
    }

    public function isOpen(): bool
    {
        return $this->status !== self::RESOLVED;
    }
}
