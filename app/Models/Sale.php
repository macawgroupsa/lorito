<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    protected $fillable = ['sale_ticket_id', 'sales_list_id', 'play_id', 'sale_date', 'number', 'amount_quetzales', 'commission_percentage', 'pieces_per_quetzal', 'prize_per_quetzal', 'sold_by', 'sold_at'];
    protected function casts(): array { return ['sale_date' => 'date', 'amount_quetzales' => 'decimal:2', 'commission_percentage' => 'decimal:2', 'pieces_per_quetzal' => 'integer', 'prize_per_quetzal' => 'decimal:2', 'sold_at' => 'datetime']; }
    public function list(): BelongsTo { return $this->belongsTo(SalesList::class, 'sales_list_id'); }
    public function play(): BelongsTo { return $this->belongsTo(Play::class); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'sold_by'); }
    public function ticket(): BelongsTo { return $this->belongsTo(SaleTicket::class, 'sale_ticket_id'); }
}
