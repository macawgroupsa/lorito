<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesPoint extends Model
{
    protected $fillable = ['name', 'contact_name', 'phone', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function lists(): HasMany { return $this->hasMany(SalesList::class); }
}
