<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Play extends Model
{
    protected $fillable = ['name', 'draw_time', 'lock_minutes', 'pieces_per_quetzal', 'prize_per_quetzal', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'prize_per_quetzal' => 'decimal:2'];
    }

    public function lists(): HasMany { return $this->hasMany(SalesList::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
    public function results(): HasMany { return $this->hasMany(DrawResult::class); }
}
