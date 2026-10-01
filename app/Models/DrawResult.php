<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrawResult extends Model
{
    protected $fillable = ['play_id', 'draw_date', 'winning_number', 'entered_by', 'entered_at'];
    protected function casts(): array { return ['draw_date' => 'date', 'entered_at' => 'datetime']; }
    public function play(): BelongsTo { return $this->belongsTo(Play::class); }
}
