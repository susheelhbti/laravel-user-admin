<?php

namespace Susheelhbti\LaravelUserAdmin\Services;

use Susheelhbti\LaravelUserAdmin\Events\UserAdminEvents;

/**
 * Evaluates configurable conditional authentication rules.
 *
 * Rules are defined in config/user_admin.php under 'auth_rules'.
 * Each rule has: condition (callable or array) + action.
 *
 * Built-in actions: require_2fa, block, suspend, require_captcha, allow
 *
 * Example config:
 *   'auth_rules' => [
 *       ['condition' => ['type' => 'failed_logins', 'threshold' => 3], 'action' => 'require_captcha'],
 *       ['condition' => ['type' => 'failed_logins', 'threshold' => 10], 'action' => 'suspend'],
 *       ['condition' => ['type' => 'time_window', 'start' => '09:00', 'end' => '17:00', 'invert' => true], 'action' => 'block'],
 *       ['condition' => ['type' => 'role', 'roles' => ['guest']], 'action' => 'require_2fa'],
 *   ],
 */
class ConditionalAuthService
{
    /**
     * Evaluate all rules for the given context.
     * Returns ['action' => string, 'triggered_rule' => array|null]
     */
    public function evaluate(array $context): array
    {
        $rules = config('user_admin.auth_rules', []);

        foreach ($rules as $rule) {
            if ($this->matches($rule['condition'], $context)) {
                $this->applyAction($rule['action'], $context);
                return ['action' => $rule['action'], 'triggered_rule' => $rule];
            }
        }

        return ['action' => 'allow', 'triggered_rule' => null];
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function matches(array $condition, array $ctx): bool
    {
        return match ($condition['type'] ?? '') {
            'failed_logins' => ($ctx['failed_login_count'] ?? 0) >= ($condition['threshold'] ?? 3),

            'time_window'   => $this->checkTimeWindow(
                $condition['start'] ?? '00:00',
                $condition['end']   ?? '23:59',
                $condition['invert'] ?? false,
                $condition['timezone'] ?? config('app.timezone', 'UTC')
            ),

            'role'          => !empty($ctx['user']) && collect($condition['roles'] ?? [])
                                ->intersect($ctx['user']->otpRoles->pluck('slug'))
                                ->isNotEmpty(),

            'ip_country'    => in_array($ctx['geo']['country_code'] ?? '', $condition['countries'] ?? []),

            default         => false,
        };
    }

    private function checkTimeWindow(string $start, string $end, bool $invert, string $tz): bool
    {
        $now       = now($tz);
        $startTime = $now->copy()->setTimeFromTimeString($start);
        $endTime   = $now->copy()->setTimeFromTimeString($end);
        $inWindow  = $now->between($startTime, $endTime);

        return $invert ? !$inWindow : $inWindow;
    }

    private function applyAction(string $action, array $ctx): void
    {
        if ($action === 'suspend' && !empty($ctx['user'])) {
            $ctx['user']->update(['status' => 'suspended']);
            $ctx['user']->tokens()->delete();

            UserAdminEvents::fire(UserAdminEvents::USER_AUTO_SUSPENDED, [
                'user_id' => $ctx['user']->id,
                'reason'  => 'auth_rule',
            ]);
        }

        UserAdminEvents::fire(UserAdminEvents::LOGIN_BLOCKED_TIME_WINDOW, [
            'action'  => $action,
            'user_id' => $ctx['user']->id ?? null,
            'ip'      => $ctx['ip'] ?? null,
        ]);
    }
}
