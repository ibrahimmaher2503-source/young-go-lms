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
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Spatie\Permission\Traits\HasRoles;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/** @property string|null $locale */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    protected $attributes = ['status' => 'active', 'timezone' => 'UTC'];

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
            'last_login_at' => 'datetime',
        ];
    }
}
