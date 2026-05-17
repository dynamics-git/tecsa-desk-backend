<?php

namespace App\Support\Auth;

use App\Models\AuthPasswordPolicy;
use App\Models\SecurityAuditLog;
use App\Models\User;
use App\Models\UserPasswordHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class PasswordSecurityService
{
    /**
     * @return array<string, mixed>
     */
    public function policy(): array
    {
        $policy = AuthPasswordPolicy::query()->first();

        if ($policy === null) {
            return $this->defaultPolicy();
        }

        return [
            'minLength' => (int) $policy->min_length,
            'requireUppercase' => (bool) $policy->require_uppercase,
            'requireLowercase' => (bool) $policy->require_lowercase,
            'requireNumber' => (bool) $policy->require_number,
            'requireSymbol' => (bool) $policy->require_symbol,
            'disallowCommonPasswords' => (bool) $policy->disallow_common_passwords,
            'historyCount' => (int) $policy->history_count,
            'maxAgeDays' => (int) $policy->max_age_days,
            'lockoutThreshold' => (int) $policy->lockout_threshold,
            'lockoutDurationMinutes' => (int) $policy->lockout_duration_minutes,
            'allowPasswordGenerate' => (bool) $policy->allow_password_generate,
            'allowManualPasswordSet' => (bool) $policy->allow_manual_password_set,
            'forceChangeOnFirstLoginDefault' => (bool) $policy->force_change_on_first_login_default,
            'updatedAt' => $policy->updated_at?->toIso8601ZuluString(),
            'updatedBy' => $policy->updated_by !== null ? (string) $policy->updated_by : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updatePolicy(array $data, ?int $actorId): array
    {
        $policy = AuthPasswordPolicy::query()->firstOrCreate([], []);

        $policy->fill([
            'min_length' => $data['minLength'] ?? $policy->min_length ?? 12,
            'require_uppercase' => $data['requireUppercase'] ?? $policy->require_uppercase ?? true,
            'require_lowercase' => $data['requireLowercase'] ?? $policy->require_lowercase ?? true,
            'require_number' => $data['requireNumber'] ?? $policy->require_number ?? true,
            'require_symbol' => $data['requireSymbol'] ?? $policy->require_symbol ?? true,
            'disallow_common_passwords' => $data['disallowCommonPasswords'] ?? $policy->disallow_common_passwords ?? true,
            'history_count' => $data['historyCount'] ?? $policy->history_count ?? 5,
            'max_age_days' => $data['maxAgeDays'] ?? $policy->max_age_days ?? 90,
            'lockout_threshold' => $data['lockoutThreshold'] ?? $policy->lockout_threshold ?? 5,
            'lockout_duration_minutes' => $data['lockoutDurationMinutes'] ?? $policy->lockout_duration_minutes ?? 15,
            'allow_password_generate' => $data['allowPasswordGenerate'] ?? $policy->allow_password_generate ?? true,
            'allow_manual_password_set' => $data['allowManualPasswordSet'] ?? $policy->allow_manual_password_set ?? true,
            'force_change_on_first_login_default' => $data['forceChangeOnFirstLoginDefault'] ?? $policy->force_change_on_first_login_default ?? true,
            'updated_by' => $actorId,
        ]);
        $policy->save();

        return $this->policy();
    }

    public function generatePassword(): string
    {
        $policy = $this->policy();
        $length = max(8, (int) ($policy['minLength'] ?? 12));

        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%^&*_-+=';

        $requiredParts = [];

        if ((bool) $policy['requireUppercase']) {
            $requiredParts[] = $upper[random_int(0, strlen($upper) - 1)];
        }
        if ((bool) $policy['requireLowercase']) {
            $requiredParts[] = $lower[random_int(0, strlen($lower) - 1)];
        }
        if ((bool) $policy['requireNumber']) {
            $requiredParts[] = $digits[random_int(0, strlen($digits) - 1)];
        }
        if ((bool) $policy['requireSymbol']) {
            $requiredParts[] = $symbols[random_int(0, strlen($symbols) - 1)];
        }

        $pool = $upper.$lower.$digits.$symbols;

        while (count($requiredParts) < $length) {
            $requiredParts[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($requiredParts);

        return implode('', $requiredParts);
    }

    public function passwordExpired(User $user): bool
    {
        if ((bool) $user->password_never_expires) {
            return false;
        }

        if ($user->password_expires_at !== null && Carbon::parse($user->password_expires_at)->isPast()) {
            return true;
        }

        $policy = $this->policy();
        $maxAgeDays = (int) ($policy['maxAgeDays'] ?? 90);
        if ($maxAgeDays <= 0 || $user->password_last_changed_at === null) {
            return false;
        }

        return Carbon::parse($user->password_last_changed_at)->addDays($maxAgeDays)->isPast();
    }

    /**
     * @return string|null
     */
    public function validatePassword(string $plainPassword, User $user, bool $enforceHistory = true): ?string
    {
        $policy = $this->policy();

        if (strlen($plainPassword) < (int) ($policy['minLength'] ?? 12)) {
            return 'PASSWORD_POLICY_VIOLATION';
        }

        if ((bool) ($policy['requireUppercase'] ?? true) && ! preg_match('/[A-Z]/', $plainPassword)) {
            return 'PASSWORD_POLICY_VIOLATION';
        }

        if ((bool) ($policy['requireLowercase'] ?? true) && ! preg_match('/[a-z]/', $plainPassword)) {
            return 'PASSWORD_POLICY_VIOLATION';
        }

        if ((bool) ($policy['requireNumber'] ?? true) && ! preg_match('/\d/', $plainPassword)) {
            return 'PASSWORD_POLICY_VIOLATION';
        }

        if ((bool) ($policy['requireSymbol'] ?? true) && ! preg_match('/[^a-zA-Z\d]/', $plainPassword)) {
            return 'PASSWORD_POLICY_VIOLATION';
        }

        if ((bool) ($policy['disallowCommonPasswords'] ?? true)) {
            $common = ['password', 'password123', 'qwerty123', 'admin123', 'welcome123'];
            if (in_array(strtolower($plainPassword), $common, true)) {
                return 'PASSWORD_POLICY_VIOLATION';
            }
        }

        if ($enforceHistory && $this->passwordHistoryReuse($user, $plainPassword, (int) ($policy['historyCount'] ?? 5))) {
            return 'PASSWORD_HISTORY_REUSE';
        }

        return null;
    }

    public function passwordHistoryReuse(User $user, string $plainPassword, int $historyCount): bool
    {
        if (Hash::check($plainPassword, $user->password)) {
            return true;
        }

        if ($historyCount <= 0) {
            return false;
        }

        $history = UserPasswordHistory::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit($historyCount)
            ->pluck('password_hash');

        foreach ($history as $hash) {
            if (Hash::check($plainPassword, (string) $hash)) {
                return true;
            }
        }

        return false;
    }

    public function applyPassword(User $user, string $plainPassword, ?int $actorId, string $source, bool $forceChange): void
    {
        $user->password = $plainPassword;
        $user->must_change_password = $forceChange;
        $user->password_last_changed_at = Carbon::now('UTC');
        $user->failed_login_attempts = 0;
        $user->locked_until = null;
        $user->security_version = (int) $user->security_version + 1;
        $user->save();

        UserPasswordHistory::query()->create([
            'user_id' => $user->id,
            'password_hash' => $user->getAuthPassword(),
            'changed_by' => $actorId,
            'change_source' => $source,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function audit(?int $actorId, ?int $targetUserId, string $action, Request $request, ?string $reason = null, array $metadata = []): int
    {
        $record = SecurityAuditLog::query()->create([
            'actor_id' => $actorId,
            'target_user_id' => $targetUserId,
            'action' => $action,
            'source_ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
            'reason' => $reason,
            'metadata' => $metadata,
        ]);

        return (int) $record->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPolicy(): array
    {
        return [
            'minLength' => 12,
            'requireUppercase' => true,
            'requireLowercase' => true,
            'requireNumber' => true,
            'requireSymbol' => true,
            'disallowCommonPasswords' => true,
            'historyCount' => 5,
            'maxAgeDays' => 90,
            'lockoutThreshold' => 5,
            'lockoutDurationMinutes' => 15,
            'allowPasswordGenerate' => true,
            'allowManualPasswordSet' => true,
            'forceChangeOnFirstLoginDefault' => true,
            'updatedAt' => null,
            'updatedBy' => null,
        ];
    }
}
