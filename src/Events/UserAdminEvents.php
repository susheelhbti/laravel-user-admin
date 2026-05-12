<?php

namespace Susheelhbti\LaravelUserAdmin\Events;

/**
 * All event name constants fired by laravel-user-admin.
 *
 * Listen via standard Laravel event system:
 *
 *   Event::listen(UserAdminEvents::LOGIN_SUCCESS, function (string $event, array $data) {
 *       logger("Login: {$data['email']} from {$data['ip']}");
 *   });
 *
 * Every payload always contains:
 *   - event       : the event name constant
 *   - fired_at    : ISO timestamp
 *   - request_id  : X-Request-ID header or auto-generated
 */
final class UserAdminEvents
{
    // ── OTP ───────────────────────────────────────────────────────────────────
    const OTP_SENT              = 'user_admin.otp.sent';
    const OTP_VERIFIED          = 'user_admin.otp.verified';
    const OTP_FAILED            = 'user_admin.otp.failed';

    // ── Auth ──────────────────────────────────────────────────────────────────
    const LOGIN_SUCCESS             = 'user_admin.auth.login_success';
    const LOGIN_FAILED              = 'user_admin.auth.login_failed';
    const LOGIN_BLOCKED_GEO         = 'user_admin.auth.login_blocked_geo';
    const LOGIN_BLOCKED_IP          = 'user_admin.auth.login_blocked_ip';
    const LOGIN_BLOCKED_TIME_WINDOW = 'user_admin.auth.login_blocked_time_window';
    const LOGIN_ANOMALY_DETECTED    = 'user_admin.auth.login_anomaly_detected';    // distance anomaly
    const LOGIN_NEW_DEVICE          = 'user_admin.auth.login_new_device';          // approval email sent
    const LOGIN_APPROVAL_REQUIRED   = 'user_admin.auth.login_approval_required';
    const LOGIN_APPROVED            = 'user_admin.auth.login_approved';
    const LOGOUT                    = 'user_admin.auth.logout';
    const TOKEN_REFRESHED           = 'user_admin.auth.token_refreshed';
    const TOKEN_REUSE_DETECTED      = 'user_admin.auth.token_reuse_detected';

    // ── 2FA ───────────────────────────────────────────────────────────────────
    const TFA_ENABLED               = 'user_admin.tfa.enabled';
    const TFA_DISABLED              = 'user_admin.tfa.disabled';
    const TFA_FAILED                = 'user_admin.tfa.failed';
    const TFA_BACKUP_CODE_USED      = 'user_admin.tfa.backup_code_used';
    const SECURITY_QUESTION_SET     = 'user_admin.tfa.security_question_set';
    const SECURITY_QUESTION_PASSED  = 'user_admin.tfa.security_question_passed';
    const SECURITY_QUESTION_FAILED  = 'user_admin.tfa.security_question_failed';

    // ── Devices / Sessions ────────────────────────────────────────────────────
    const DEVICE_TRUSTED            = 'user_admin.device.trusted';
    const DEVICE_REVOKED            = 'user_admin.device.revoked';
    const DEVICE_FINGERPRINT_CHANGE = 'user_admin.device.fingerprint_change';
    const SESSIONS_TERMINATED       = 'user_admin.sessions.terminated';

    // ── Users ─────────────────────────────────────────────────────────────────
    const USER_CREATED              = 'user_admin.user.created';
    const USER_UPDATED              = 'user_admin.user.updated';
    const USER_DELETED              = 'user_admin.user.deleted';
    const USER_SUSPENDED            = 'user_admin.user.suspended';
    const USER_UNSUSPENDED          = 'user_admin.user.unsuspended';
    const USER_ARCHIVED             = 'user_admin.user.archived';
    const USER_UNARCHIVED           = 'user_admin.user.unarchived';
    const USER_EXPIRED              = 'user_admin.user.expired';
    const USER_INVITE_SENT          = 'user_admin.user.invite_sent';
    const USER_INVITE_ACCEPTED      = 'user_admin.user.invite_accepted';
    const USER_AUTO_SUSPENDED       = 'user_admin.user.auto_suspended';           // after N failed logins
    const USER_TAG_ADDED            = 'user_admin.user.tag_added';
    const USER_TAG_REMOVED          = 'user_admin.user.tag_removed';
    const USER_METADATA_UPDATED     = 'user_admin.user.metadata_updated';
    const USER_IDLE_WARNING         = 'user_admin.user.idle_warning';
    const PROFILE_SCORE_CHANGED     = 'user_admin.user.profile_score_changed';

    // ── Teams / Organizations ─────────────────────────────────────────────────
    const TEAM_CREATED              = 'user_admin.team.created';
    const TEAM_UPDATED              = 'user_admin.team.updated';
    const TEAM_DELETED              = 'user_admin.team.deleted';
    const TEAM_MEMBER_ADDED         = 'user_admin.team.member_added';
    const TEAM_MEMBER_REMOVED       = 'user_admin.team.member_removed';
    const TEAM_OWNERSHIP_TRANSFERRED = 'user_admin.team.ownership_transferred';

    // ── API Keys ──────────────────────────────────────────────────────────────
    const API_KEY_CREATED           = 'user_admin.api_key.created';
    const API_KEY_ROTATED           = 'user_admin.api_key.rotated';
    const API_KEY_REVOKED           = 'user_admin.api_key.revoked';
    const API_KEY_EXPIRED           = 'user_admin.api_key.expired';

    // ── Webhooks ──────────────────────────────────────────────────────────────
    const WEBHOOK_DELIVERED         = 'user_admin.webhook.delivered';
    const WEBHOOK_FAILED            = 'user_admin.webhook.failed';
    const WEBHOOK_RETRIED           = 'user_admin.webhook.retried';

    // ── GDPR / Data ───────────────────────────────────────────────────────────
    const GDPR_EXPORT_REQUESTED     = 'user_admin.gdpr.export_requested';
    const GDPR_EXPORT_READY         = 'user_admin.gdpr.export_ready';
    const GDPR_ERASURE_REQUESTED    = 'user_admin.gdpr.erasure_requested';
    const GDPR_CONSENT_UPDATED      = 'user_admin.gdpr.consent_updated';

    // ── Account deletion ──────────────────────────────────────────────────────
    const ACCOUNT_DELETION_REQUESTED  = 'user_admin.account.deletion_requested';
    const ACCOUNT_DELETION_COMPLETED  = 'user_admin.account.deletion_completed';
    const ACCOUNT_DELETION_CANCELLED  = 'user_admin.account.deletion_cancelled';

    // ── Admin ─────────────────────────────────────────────────────────────────
    const ADMIN_ACTION              = 'user_admin.admin.action';
    const IMPERSONATION_STARTED     = 'user_admin.admin.impersonation_started';
    const IMPERSONATION_STOPPED     = 'user_admin.admin.impersonation_stopped';

    // ── Bulk ──────────────────────────────────────────────────────────────────
    const BULK_USERS_IMPORTED       = 'user_admin.bulk.imported';
    const BULK_USERS_SUSPENDED      = 'user_admin.bulk.suspended';
    const BULK_USERS_DELETED        = 'user_admin.bulk.deleted';
    const BULK_ROLE_ASSIGNED        = 'user_admin.bulk.role_assigned';

    // ── Email ─────────────────────────────────────────────────────────────────
    const EMAIL_SENT                = 'user_admin.email.sent';
    const EMAIL_FAILED              = 'user_admin.email.failed';

    // ── Analytics ─────────────────────────────────────────────────────────────
    const METRICS_SCRAPED           = 'user_admin.analytics.metrics_scraped';

    /**
     * Fire a named event with a standard payload envelope.
     * All listeners receive (string $eventName, array $payload).
     */
    public static function fire(string $event, array $payload = []): void
    {
        event($event, array_merge([
            'event'      => $event,
            'fired_at'   => now()->toISOString(),
            'request_id' => request()->header('X-Request-ID', uniqid('req_', true)),
        ], $payload));
    }

    /** Return every defined event constant as an array. */
    public static function all(): array
    {
        return (new \ReflectionClass(static::class))->getConstants();
    }
}
