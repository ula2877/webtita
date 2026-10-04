<?php

namespace App\Policies;

use App\Models\Arrear;
use App\Models\User;

class ArrearPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isPetugas();
    }

    public function view(User $user, Arrear $arrear): bool
    {
        return $user->isAdmin() || $user->id === $arrear->petugas_id;
    }

    public function collect(User $user, Arrear $arrear): bool
    {
        return $user->isPetugas() && $user->id === $arrear->petugas_id;
    }
}
