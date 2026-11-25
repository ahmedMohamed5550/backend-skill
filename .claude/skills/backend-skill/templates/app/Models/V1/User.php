<?php

namespace App\Models\V1;

use Database\Factories\V1\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * The single User model of the project.
 *
 * The scaffold installs this approved template and removes the Laravel
 * skeleton's default app/Models/User.php, so App\Models\V1\User is the only
 * User model. Never create or restore App\Models\User: authentication
 * (Passport), authorization (Spatie), config/auth.php, seeders, and factories
 * all reference this V1 model (see references/models.md and
 * references/architecture.md).
 *
 * The base Illuminate\Foundation\Auth\User class already carries the
 * MustVerifyEmail and CanResetPassword traits; implementing the
 * MustVerifyEmail contract enables Laravel's native email verification flow
 * (sendEmailVerificationNotification, hasVerifiedEmail, markEmailAsVerified),
 * and the CanResetPassword machinery drives the native password reset
 * (references/authentication.md, Email Verification and Password Reset).
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
