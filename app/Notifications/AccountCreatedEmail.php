<?php

namespace App\Notifications;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the new account's verified email once it has been created
 * (Michael, 2026-09-30): who they are on the system, how they sign in and
 * where. The password is never in it. The administrator gives it to them, or
 * they choose their own through Forgot password.
 */
class AccountCreatedEmail extends Notification
{
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $company = SystemSetting::current()->company_name ?: 'Jeyanco Construction';
        $name    = $notifiable->first_name ?: ($notifiable->name ?: $notifiable->username);
        $role    = User::ROLES[$notifiable->role] ?? 'HR';

        $how = match ($notifiable->login_method) {
            User::LOGIN_GOOGLE   => "Sign in with Google, choosing **{$notifiable->email}**.",
            User::LOGIN_PASSWORD => "Sign in with the username **{$notifiable->username}** and the password your administrator gives you.",
            default              => "Sign in with Google as **{$notifiable->email}**, or with the username **{$notifiable->username}** and the password your administrator gives you.",
        };

        $mail = (new MailMessage)
            ->subject('Your Jeyanco Payroll account is ready')
            ->greeting("Hi {$name},")
            ->line("An account on Jeyanco Payroll has been created for you, with **{$role}** access.")
            ->line($how);

        if ($notifiable->must_change_password && $notifiable->login_method !== User::LOGIN_GOOGLE) {
            $mail->line('You will be asked to choose a password of your own the first time you sign in.');
        }

        return $mail
            ->action('Open Jeyanco Payroll', route('login'))
            ->line('Forgot the password? Use **Forgot password** on the sign-in page and a link comes to this address.')
            ->salutation("— {$company}");
    }
}
