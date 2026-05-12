<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;

class UserLifecycleService
{
    // ── Idle / Archival ───────────────────────────────────────────────────────

    /**
     * Archive users inactive for more than $days days.
     * Called by the ArchiveInactiveUsers artisan command.
     */
    public function archiveInactive(int $days = 180): int
    {
        $model      = config('user_admin.user_model', \App\Models\User::class);
        $cutoff     = now()->subDays($days);
        $warnCutoff = now()->subDays((int) ($days * 0.9)); // fire idle warning at 90% of threshold
        $count      = 0;

        $model::where('status', 'active')
            ->where(function ($q) use ($cutoff) {
                $q->where('last_login_at', '<', $cutoff)
                  ->orWhereNull('last_login_at');
            })
            ->whereNull('deletion_requested_at')
            ->chunk(200, function ($users) use (&$count, $warnCutoff, $cutoff) {
                foreach ($users as $user) {
                    // Bug #9 fix — fire USER_IDLE_WARNING for users approaching archive threshold
                    $lastLogin = $user->last_login_at;
                    if ($lastLogin && $lastLogin->lt($warnCutoff) && $lastLogin->gt($cutoff)) {
                        UserAdminEvents::fire(UserAdminEvents::USER_IDLE_WARNING, [
                            'user_id'        => $user->id,
                            'email'          => $user->email,
                            'last_login_at'  => $lastLogin->toISOString(),
                            'days_inactive'  => (int) $lastLogin->diffInDays(now()),
                        ]);
                        continue;
                    }

                    $user->update(['status' => 'archived']);
                    $user->tokens()->delete();
                    UserAdminEvents::fire(UserAdminEvents::USER_ARCHIVED, [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Expire accounts whose account_expires_at has passed.
     */
    public function expireAccounts(): int
    {
        $model = config('user_admin.user_model', \App\Models\User::class);
        $count = 0;

        $model::where('status', 'active')
            ->whereNotNull('account_expires_at')
            ->where('account_expires_at', '<=', now())
            ->chunk(200, function ($users) use (&$count) {
                foreach ($users as $user) {
                    $user->update(['status' => 'expired']);
                    $user->tokens()->delete();
                    UserAdminEvents::fire(UserAdminEvents::USER_EXPIRED, [
                        'user_id' => $user->id,
                        'email'   => $user->email,
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    // ── Tags ──────────────────────────────────────────────────────────────────

    public function addTag(object $user, string $tag): void
    {
        $tags = $user->tags ?? [];
        if (!in_array($tag, $tags)) {
            $user->update(['tags' => array_values(array_unique(array_merge($tags, [$tag])))]);
            UserAdminEvents::fire(UserAdminEvents::USER_TAG_ADDED, ['user_id' => $user->id, 'tag' => $tag]);
        }
    }

    public function removeTag(object $user, string $tag): void
    {
        $tags = array_values(array_filter($user->tags ?? [], fn ($t) => $t !== $tag));
        $user->update(['tags' => $tags]);
        UserAdminEvents::fire(UserAdminEvents::USER_TAG_REMOVED, ['user_id' => $user->id, 'tag' => $tag]);
    }

    // ── Metadata ──────────────────────────────────────────────────────────────

    public function setMetadata(object $user, array $data): void
    {
        $user->update(['metadata' => array_merge($user->metadata ?? [], $data)]);
        UserAdminEvents::fire(UserAdminEvents::USER_METADATA_UPDATED, ['user_id' => $user->id, 'keys' => array_keys($data)]);
    }

    // ── Profile completion score ──────────────────────────────────────────────

    /**
     * Compute 0-100 profile completeness score based on configurable required fields.
     */
    public function profileScore(object $user): array
    {
        $fields    = config('user_admin.profile_score.fields', [
            'name'      => 20,
            'email'     => 20,
            'phone'     => 20,
            'avatar'    => 20,
            'bio'       => 20,
        ]);

        $score     = 0;
        $filled    = [];
        $missing   = [];

        foreach ($fields as $field => $points) {
            $val = data_get($user, $field) ?? data_get($user->metadata ?? [], $field);
            if (!empty($val)) {
                $score += $points;
                $filled[] = $field;
            } else {
                $missing[] = $field;
            }
        }

        $score = min(100, $score);

        if (($user->profile_score ?? null) !== $score) {
            $user->update(['profile_score' => $score]);
            UserAdminEvents::fire(UserAdminEvents::PROFILE_SCORE_CHANGED, [
                'user_id'   => $user->id,
                'old_score' => $user->profile_score,
                'new_score' => $score,
            ]);
        }

        return ['score' => $score, 'filled' => $filled, 'missing' => $missing];
    }
}
