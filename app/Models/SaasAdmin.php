<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class SaasAdmin extends Authenticatable
{
    protected $connection = 'central';

    protected $table = 'saas_admins';

    protected $hidden = ['password'];

    protected $fillable = ['name', 'email', 'password', 'role'];
}
