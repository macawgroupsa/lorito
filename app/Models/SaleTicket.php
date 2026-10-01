<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleTicket extends Model
{
    protected $fillable = ['ticket_code', 'sales_point_id', 'sold_by', 'customer_name', 'total_quetzales', 'sale_date', 'issued_at'];

    protected function casts(): array
    {
        return ['total_quetzales' => 'decimal:2', 'sale_date' => 'date', 'issued_at' => 'datetime'];
    }

    public function items(): HasMany { return $this->hasMany(Sale::class, 'sale_ticket_id'); }
    public function point(): BelongsTo { return $this->belongsTo(SalesPoint::class, 'sales_point_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'sold_by'); }
}
