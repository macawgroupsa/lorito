<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesList extends Model
{
    protected $fillable = ['sales_point_id', 'play_id', 'name', 'commission_percentage', 'active'];
    protected function casts(): array { return ['commission_percentage' => 'decimal:2', 'active' => 'boolean']; }
    public function point(): BelongsTo { return $this->belongsTo(SalesPoint::class, 'sales_point_id'); }
    public function play(): BelongsTo { return $this->belongsTo(Play::class); }
    public function sales(): HasMany { return $this->hasMany(Sale::class); }
}
