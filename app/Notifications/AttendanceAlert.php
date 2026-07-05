<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class AttendanceAlert extends Notification
{
    private static array $meta = [
        'invalid_clock_in' => [
            'icon'  => 'fa-user-clock',
            'color' => '#dc2626',
            'link'  => '/attendance',
        ],
        'low_attendance' => [
            'icon'  => 'fa-user-slash',
            'color' => '#f97316',
            'link'  => '/attendance',
        ],
        'overtime' => [
            'icon'  => 'fa-business-time',
            'color' => '#8b5cf6',
            'link'  => '/attendance',
        ],
    ];

    public function __construct(
        private string $subtype,
        private string $title,
        private string $message
    ) {}

    public function via(): array
    {
        return ['database'];
    }

    public function toDatabase(): array
    {
        $m = self::$meta[$this->subtype] ?? self::$meta['invalid_clock_in'];

        return [
            'key'     => $this->subtype . '_' . today()->toDateString(),
            'subtype' => $this->subtype,
            'title'   => $this->title,
            'message' => $this->message,
            'link'    => $m['link'],
            'icon'    => $m['icon'],
            'color'   => $m['color'],
        ];
    }

    /** Fire only once per subtype per day (de-duplicate across read and unread). */
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
}
