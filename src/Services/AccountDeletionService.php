<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;
use Illuminate\Support\Facades\Mail;
use Susheelhbti\LaravelUserAdmin\Mail\AccountDeletionRequestedMail;

class AccountDeletionService
{
    public function request(object $user): \DateTime
    {
        $graceDays  = config('user_admin.account_deletion_grace_days', 7);
        $scheduledAt = now()->addDays($graceDays);

        $user->update([
            'deletion_requested_at' => now(),
            'deletion_scheduled_at' => $scheduledAt,
        ]);

        // Revoke all active tokens so they can't keep using the account
        $user->tokens()->delete();

        try {
            Mail::to($user->email)->send(new AccountDeletionRequestedMail($user, $scheduledAt));
            UserAdminEvents::fire(UserAdminEvents::EMAIL_SENT, ['type' => 'account_deletion_requested', 'user_id' => $user->id]);
        } catch (\Throwable $e) {
            UserAdminEvents::fire(UserAdminEvents::EMAIL_FAILED, ['error' => $e->getMessage()]);
        }

        UserAdminEvents::fire(UserAdminEvents::ACCOUNT_DELETION_REQUESTED, [
            'user_id'      => $user->id,
            'email'        => $user->email,
            'scheduled_at' => $scheduledAt->toISOString(),
        ]);

        return $scheduledAt;
    }

    public function cancel(object $user): void
    {
        $user->update([
            'deletion_requested_at' => null,
            'deletion_scheduled_at' => null,
        ]);

        UserAdminEvents::fire(UserAdminEvents::ACCOUNT_DELETION_CANCELLED, [
            'user_id' => $user->id,
            'email'   => $user->email,
        ]);
    }

    /**
     * Called by the scheduled command to purge accounts past their grace period.
     */
    public function purgeExpired(): int
    {
        $model = config('user_admin.user_model', \App\Models\User::class);

        $users = $model::whereNotNull('deletion_scheduled_at')
            ->where('deletion_scheduled_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($users as $user) {
            $email   = $user->email;
            $userId  = $user->id;

            $user->tokens()->delete();
            $user->forceDelete();
            $count++;

            UserAdminEvents::fire(UserAdminEvents::ACCOUNT_DELETION_COMPLETED, [
                'user_id' => $userId,
                'email'   => $email,
            ]);
        }

        return $count;
    }
}
