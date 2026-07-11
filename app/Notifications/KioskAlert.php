<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class KioskAlert extends Notification
{
    private static array $meta = [
        'kiosk_offline' => [
            'icon'  => 'fa-plug-circle-xmark',
            'color' => '#dc2626',
            'link'  => '/dashboard',
        ],
        'kiosk_online' => [
            'icon'  => 'fa-plug-circle-check',
            'color' => '#16a34a',
            'link'  => '/dashboard',
        ],
        'kiosk_geofence' => [
            'icon'  => 'fa-location-crosshairs',
            'color' => '#dc2626',
            'link'  => '/dashboard',
        ],
        'kiosk_geofence_ok' => [
            'icon'  => 'fa-location-dot',
            'color' => '#16a34a',
            'link'  => '/dashboard',
        ],
    ];

    public function __construct(
        private string $subtype,
        private string $title,
        private string $message,
        private string $uniqueKey,
    ) {}

    public function via(): array
    {
        return ['database'];
    }

    public function toDatabase(): array
    {
        $m = self::$meta[$this->subtype] ?? self::$meta['kiosk_offline'];

        return [
            'key'     => $this->uniqueKey,
            'subtype' => $this->subtype,
            'title'   => $this->title,
            'message' => $this->message,
            'link'    => $m['link'],
            'icon'    => $m['icon'],
            'color'   => $m['color'],
        ];
    }

    /**
     * Fire a kiosk state-transition alert. Callers already de-duplicate by
     * comparing against the last known state, so every call here is a genuine
     * transition and gets its own fresh notification.
     */
    public static function fire($user, string $subtype, string $title, string $message): void
    {
        $key = $subtype . '_' . now()->timestamp . '_' . Str::random(6);

        $user->notify(new self($subtype, $title, $message, $key));
    }
}
