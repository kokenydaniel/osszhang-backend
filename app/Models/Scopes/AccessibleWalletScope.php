<?php

namespace App\Models\Scopes;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class AccessibleWalletScope implements Scope
{
    public function __construct(private readonly ?User $user) {}

    public function apply(Builder $builder, Model $model): void
    {
        if ($this->user === null || $this->user->household_id === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->accessibleTo($this->user);
    }
}
