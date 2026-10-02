<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A copy of an admin's alert, sent to their own address, while notify_email
 * is on. Its switch was System Settings → Notifications → Also send by email
 * (2026-09-27); that section left the page on 2026-10-02 and the saved value
 * stands.
 *
 * The bell keeps the alert either way. The copy goes out after the page has
 * been answered, so a slow mail server never holds up the screen that raised
 * it, and a failed send is logged rather than shown.
 */
class AlertEmail extends Notification
{
    /** The alerts worth a copy. Password resets and the like are mail already. */
    private const ALERTS = [AttendanceAlert::class, KioskAlert::class, PayrollNotification::class];

    public function __construct(private array $alert) {}

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $company = SystemSetting::current()->company_name ?: 'Jeyanco Construction';
        $link    = (string) ($this->alert['link'] ?? '/dashboard');

        return (new MailMessage)
            ->subject((string) ($this->alert['title'] ?? 'Jeyanco Payroll alert'))
            ->greeting((string) ($this->alert['title'] ?? 'Alert'))
            ->line((string) ($this->alert['message'] ?? ''))
            ->action('Open Jeyanco Payroll', url($link))
            ->line('You get these because you are an administrator. The same alert is in the bell at the top of the page.')
            ->salutation("— {$company}");
    }

    /** Listens for alerts written to the bell, and mails admins a copy. */
    public static function copy(NotificationSent $event): void
    {
        $user = $event->notifiable;

        if ($event->channel !== 'database'
            || ! in_array(get_class($event->notification), self::ALERTS, true)
            || ! ($user->is_admin ?? false)
            || blank($user->email ?? null)
            || ! SystemSetting::current()->enabled('notify_email')) {
            return;
        }

        $alert = $event->notification->toDatabase($user);

        app()->terminating(function () use ($user, $alert) {
            try {
                \Illuminate\Support\Facades\Notification::route('mail', $user->email)->notifyNow(new self($alert));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }
}
