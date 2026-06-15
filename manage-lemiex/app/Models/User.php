<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'email',
        'username',
        'role_id',
        'status',
        'email_verified_at',
        'last_login',
        'api_key',
    ];

    protected $hidden = [
        'remember_token',
        'api_key',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function authProviders(): HasMany
    {
        return $this->hasMany(AuthProvider::class);
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function partnerStores(): HasMany
    {
        return $this->hasMany(PartnerStore::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'seller_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    public function debits(): HasMany
    {
        return $this->hasMany(Debit::class);
    }

    public function supports(): HasMany
    {
        return $this->hasMany(Support::class);
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'email' => $this->email,
            'userName' => $this->userName,
            'role' => $this->role->name,
        ];
    }


    public function getUser($email)
    {
        $user = User::where('email', $email)->first();
        return $user;
    }

    /**
     * Get user by email or username
     */
    public function getUserByEmailOrUsername($loginId)
    {
        return User::where('email', $loginId)
            ->orWhere('username', $loginId)
            ->first();
    }

    public function getProviderByUser($id)
    {
        $authProvider = AuthProvider::where('user_id', $id)
            ->where('provider', 'local')
            ->first();
        return $authProvider;
    }
}
