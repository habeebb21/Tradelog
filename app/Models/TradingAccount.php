<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class TradingAccount extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'broker_name',
        'market',
        'currency',
        'starting_balance',
        'equity_override',
        'brokerage_percent',
        'is_default',
        'notes',
    ];

    protected $casts = [
        'starting_balance' => 'decimal:2',
        'equity_override'  => 'decimal:2',
        'brokerage_percent' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }

    public function positions(): HasManyThrough
    {
        return $this->hasManyThrough(Position::class, Instrument::class);
    }
}
