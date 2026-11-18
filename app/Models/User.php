<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Identity\AuthMail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * @property string|null $locale
 * @property string|null $two_factor_secret
 * @property list<string>|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property int|null $two_factor_last_step
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'phone_reminder_opt_ins'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = ['status' => 'active', 'timezone' => 'UTC'];

    /**
     * The name to show in the current UI language: the explicit display name when the person set one, otherwise the name stored for
     * the active locale, then the other language's name, then the account name. Writes still go to the raw column.
     *
     * @return Attribute<non-empty-string|null, never>
     */
    protected function displayName(): Attribute
    {
        return Attribute::get(function (mixed $value, array $attributes): ?string {
            $own = $attributes['display_name'] ?? null;
            if (is_string($own) && trim($own) !== '') {
                return $own;
            }
            $primary = app()->getLocale() === 'ar' ? 'name_ar' : 'name_en';
            $secondary = $primary === 'name_ar' ? 'name_en' : 'name_ar';
            foreach ([$primary, $secondary, 'name'] as $column) {
                $candidate = $attributes[$column] ?? null;
                if (is_string($candidate) && trim($candidate) !== '') {
                    return $candidate;
                }
            }

            return null;
        });
    }

    public function preferredLocale(): ?string
    {
        return $this->locale;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $permission = match ($panel->getId()) {
            'admin' => 'panel.admin.access',
            'instructor' => 'panel.instructor.access',
            'finance' => 'panel.finance.access',
            default => null,
        };

        return $this->getAttribute('status') === 'active'
            && $permission !== null
            && $this->can($permission);
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->sendAuthNotification(new VerifyEmail);
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->sendAuthNotification(new ResetPassword($token));
    }

    private function sendAuthNotification(Notification $notification): void
    {
        if ($this->email === null || ! AuthMail::isSafe()) {
            return;
        }

        try {
            $this->notify($notification);
        } catch (TransportExceptionInterface $exception) {
            report($exception);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_year' => 'integer',
            'birth_month' => 'integer',
            'phone_reminder_opt_ins' => 'encrypted:array',
            'last_login_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
        ];
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->getAttribute('two_factor_confirmed_at') !== null;
    }

    /** ED-016: mandatory for the configured high-privilege roles, optional for everyone else. */
    public function requiresTwoFactor(): bool
    {
        return $this->hasAnyRole(Config::array('identity.two_factor_required_roles'));
    }
}
