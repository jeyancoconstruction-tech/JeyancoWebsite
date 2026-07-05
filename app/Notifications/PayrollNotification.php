<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class PayrollNotification extends Notification
{
    private static array $meta = [
        'period_computed' => [
            'icon'  => 'fa-calculator',
            'color' => '#1e3a8a',
            'link'  => '/payroll-records',
        ],
        'high_vale' => [
            'icon'  => 'fa-money-bill-wave',
            'color' => '#d97706',
            'link'  => '/payroll-records',
        ],
        'net_summary' => [
            'icon'  => 'fa-chart-bar',
            'color' => '#059669',
            'link'  => '/payroll-records',
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
        $m = self::$meta[$this->subtype] ?? self::$meta['period_computed'];

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
