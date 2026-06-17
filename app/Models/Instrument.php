<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instrument extends Model
{
    protected $fillable = [
        'user_id',
        'trading_account_id',
        'symbol',
        'underlying_symbol',
        'asset_type',
        'expiry',
        'strike',
        'put_call',
        'multiplier',
        'currency',
        'current_price',
        'mark_mode',
    ];

    protected $casts = [
        'expiry'      => 'date',
        'strike'      => 'decimal:2',
        'multiplier'  => 'integer',
        'current_price' => 'decimal:4',
        'mark_mode'   => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tradingAccount(): BelongsTo
    {
        return $this->belongsTo(TradingAccount::class);
    }

    public function fills(): HasMany
    {
        return $this->hasMany(Fill::class);
    }

    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    public function isOption(): bool
    {
        return $this->asset_type === 'OPT';
    }

    public function isStock(): bool
    {
        return $this->asset_type === 'STK';
    }
}
