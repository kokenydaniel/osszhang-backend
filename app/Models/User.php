<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Laravel\Cashier\Billable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    protected $fillable = [
        'first_name',
        'last_name',
        'username',
        'password',
        'must_change_password',
        'household_id',
        'role',
        'permissions',
        'lifetime_admin',
        'is_active',
        'last_login_at',
    ];
    protected $hidden = ['password', 'remember_token'];

    use HasApiTokens, Billable, HasFactory, Notifiable;

    public function household()
    {
        return $this->belongsTo(Household::class);
    }

    public function ownedWallets()
    {
        return $this->hasMany(Wallet::class, 'owner_id');
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'permissions' => 'array',
            'role' => 'string',
            'lifetime_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }
}
