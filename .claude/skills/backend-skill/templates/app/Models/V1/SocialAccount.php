<?php

namespace App\Models\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A social provider identity linked to a user (optional Social Login
 * feature — references/authentication.md, Social Login (Socialite)).
 *
 * The unique (provider, provider_user_id) pair is the only account lookup
 * key; email alone never resolves an identity. When provider tokens are
 * stored (config/social.php, store_provider_tokens), add the nullable token
 * columns to the migration and declare them in the model's `$encrypted`
 * array so they are encrypted at rest.
 */
class SocialAccount extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
