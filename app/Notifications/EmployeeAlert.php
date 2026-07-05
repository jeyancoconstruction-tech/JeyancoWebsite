<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class EmployeeAlert extends Notification
{
    private static array $meta = [
        'new_employee' => [
            'icon'  => 'fa-user-plus',
            'color' => '#1e3a8a',
            'link'  => '/employees',
        ],
        'missing_fingerprint' => [
            'icon'  => 'fa-fingerprint',
            'color' => '#f97316',
            'link'  => '/employees',
        ],
        'unassigned_site' => [
            'icon'  => 'fa-map-marker-alt',
            'color' => '#d97706',
            'link'  => '/employees',
        ],
        'unassigned_labor_type' => [
            'icon'  => 'fa-briefcase',
            'color' => '#dc2626',
            'link'  => '/employees',
        ],
    ];

    public function __construct(
        private string $subtype,
        private string $title,
        private string $message,
        private ?string $uniqueKey = null
    ) {}

    public function via(): array
    {
        return ['database'];
    }

    public function toDatabase(): array
    {
        $m = self::$meta[$this->subtype] ?? self::$meta['new_employee'];

        return [
            'key'     => $this->uniqueKey ?? ($this->subtype . '_' . today()->toDateString()),
            'subtype' => $this->subtype,
            'title'   => $this->title,
            'message' => $this->message,
            'link'    => $m['link'],
            'icon'    => $m['icon'],
            'color'   => $m['color'],
        ];
    }

    /**
     * Fire for a STANDING condition (e.g. "5 employees missing fingerprints").
     * De-duplicated per subtype per day so the same standing alert isn't spammed.
     */
    public static function fireOnce($user, string $subtype, string $title, string $message): void
    {
        $key = $subtype . '_' . today()->toDateString();

        $exists = $user->notifications()
            ->where('type', self::class)
            ->where('data->key', $key)
            ->exists();

        if (! $exists) {
            $user->notify(new self($subtype, $title, $message));
        }
    }

    /**
     * Fire for a DISCRETE event (e.g. a specific employee was just registered).
     * Always sends a fresh notification with a unique key — never de-duplicated,
     * so every registration produces its own alert.
     */
    public static function fire($user, string $subtype, string $title, string $message): void
    {
        $key = $subtype . '_' . now()->timestamp . '_' . Str::random(6);

        $user->notify(new self($subtype, $title, $message, $key));
    }
}
