<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The six-digit code that proves an email address is real before an account
 * is created with it, or before an account's email is changed to it
 * (Michael, 2026-09-30). The admin types the code into the Create Account
 * form, read to them by the person who owns the inbox.
 */
class EmailVerificationCode extends Notification
{
    public function __construct(public readonly string $code, private int $minutes) {}

    public function via(): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $company = SystemSetting::current()->company_name ?: 'Jeyanco Construction';

        return (new MailMessage)
            ->subject("{$this->code} is your Jeyanco Payroll verification code")
            ->greeting('Your verification code')
            ->line("An account on Jeyanco Payroll is being set up with this email address. Give this code to the administrator creating it:")
            ->line("**{$this->code}**")
            ->line("The code expires in {$this->minutes} minutes.")
            ->line('If you are not expecting a Jeyanco Payroll account, you can ignore this email; nothing is created without the code.')
            ->salutation("— {$company}");
    }
}
