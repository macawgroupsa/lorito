<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $connection = 'central';
    protected $fillable = ['name', 'slug', 'owner_email', 'database_name', 'subscription_status', 'subscription_plan', 'subscription_amount', 'trial_ends_at', 'subscription_ends_at', 'subscription_notes'];
    protected function casts(): array { return ['trial_ends_at' => 'datetime', 'subscription_ends_at' => 'datetime']; }

    public function isAvailable(): bool
    {
        if ($this->subscription_status === 'suspended') return false;
        if ($this->subscription_status === 'trial') return $this->trial_ends_at && $this->trial_ends_at->isFuture();
        if ($this->subscription_status === 'active') return ! $this->subscription_ends_at || $this->subscription_ends_at->isFuture();
        return false;
    }
}
