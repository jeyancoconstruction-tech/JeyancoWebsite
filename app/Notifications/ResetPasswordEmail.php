<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The Forgot password email, in the company's own words.
 *
 * Laravel's stock one — "Reset Password Notification", "Hello!", "Regards,
 * Laravel" — is word for word what phishing kits copy, and Gmail was filing it
 * under Spam. This one names the person, their username and the company, and
 * says what to do if they did not ask for it; the link itself is still built
 * by Laravel, one use, expiring as auth.passwords says.
 */
class ResetPasswordEmail extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $company = SystemSetting::current()->company_name ?: 'Jeyanco Construction';
        $minutes = config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);
        $name    = $notifiable->first_name ?: ($notifiable->name ?: $notifiable->username);

        return (new MailMessage)
            ->subject('Reset your Jeyanco Payroll password')
            ->greeting("Hi {$name},")
            ->line("Someone asked to reset the password for your Jeyanco Payroll account, **{$notifiable->username}**.")
            ->action('Choose a new password', $this->resetUrl($notifiable))
            ->line("The link works once and expires in {$minutes} minutes.")
            ->line('If you did not ask for this, you can ignore this email — your password stays the same.')
            ->salutation("— {$company}");
    }
}
