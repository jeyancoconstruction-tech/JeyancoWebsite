<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class SettingsChanged extends Notification
{
    private static array $meta = [
        'rates_updated' => [
            'icon'  => 'fa-sliders-h',
            'color' => '#6d28d9',
            'link'  => '/settings',
        ],
        'labor_type_deleted' => [
            'icon'  => 'fa-briefcase',
            'color' => '#dc2626',
            'link'  => '/settings?tab=labor',
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
        $m = self::$meta[$this->subtype] ?? self::$meta['rates_updated'];

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
