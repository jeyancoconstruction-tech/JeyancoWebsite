<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class HolidayReminder extends Notification
{
    private static array $meta = [
        'upcoming_holiday' => [
            'icon'  => 'fa-calendar-day',
            'color' => '#d97706',
            'link'  => '/settings?tab=holiday',
        ],
        'calendar_synced' => [
            'icon'  => 'fa-calendar-check',
            'color' => '#16a34a',
            'link'  => '/settings?tab=holiday',
        ],
    ];

    public function __construct(
        private string $subtype,
        private string $title,
        private string $message,
        private ?string $keySuffix = null
    ) {}

    public function via(): array
    {
        return ['database'];
    }

    public function toDatabase(): array
    {
        $m = self::$meta[$this->subtype] ?? self::$meta['upcoming_holiday'];

        return [
            'key'     => $this->subtype . '_' . ($this->keySuffix ?? today()->toDateString()),
            'subtype' => $this->subtype,
            'title'   => $this->title,
            'message' => $this->message,
            'link'    => $m['link'],
            'icon'    => $m['icon'],
            'color'   => $m['color'],
        ];
    }

    /**
     * Fire at most once per subtype+keySuffix combination (read or unread).
     * Pass $keySuffix to pin the key to something other than today (e.g. the
     * holiday date itself) so the same event never re-notifies after being read.
     */
    public static function fireOnce($user, string $subtype, string $title, string $message, ?string $keySuffix = null): void
    {
        $key = $subtype . '_' . ($keySuffix ?? today()->toDateString());

        $exists = $user->notifications()
            ->where('type', self::class)
            ->where('data->key', $key)
            ->exists();

        if (! $exists) {
            $user->notify(new self($subtype, $title, $message, $keySuffix));
        }
    }
}
