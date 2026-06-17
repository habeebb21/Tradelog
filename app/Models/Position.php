<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;

class Position extends Model
{
    protected $fillable = [
        'instrument_id',
        'open_datetime',
        'close_datetime',
        'quantity',
        'cost_basis',
        'realized_pnl',
        'notes',
    ];

    protected $casts = [
        'open_datetime' => 'datetime',
        'close_datetime' => 'datetime',
        'quantity' => 'decimal:2',
        'cost_basis' => 'decimal:4',
        'realized_pnl' => 'decimal:2',
    ];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function fills(): HasManyThrough
    {
        return $this->hasManyThrough(Fill::class, Instrument::class, 'id', 'instrument_id', 'instrument_id', 'id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TradeTag::class, 'position_trade_tag');
    }

    public function isOpen(): bool
    {
        return $this->close_datetime === null;
    }

    public function isClosed(): bool
    {
        return $this->close_datetime !== null;
    }

    /**
     * Open position with mark_mode enabled — treated as-if closed at mark price for display.
     * mark_mode=false means price is saved for floating P&L reference only (Open badge).
     */
    public function isMarked(): bool
    {
        return $this->isOpen()
            && $this->markPrice() !== null
            && (bool) ($this->instrument?->mark_mode ?? false);
    }

    /**
     * Hypothetical realized P&L if position were closed at current mark price.
     * Deducts both entry and exit brokerage (same rate, mark price for exit).
     */
    public function markRealizedPnL(): ?float
    {
        $markPrice = $this->markPrice();
        if ($markPrice === null) {
            return null;
        }

        $entryPrice     = (float) $this->cost_basis;
        $quantity       = (float) $this->quantity;
        $multiplier     = (float) ($this->instrument->multiplier ?? 1);
        $entryBrokerage = $this->entryBrokerageAmount();
        $exitBrokerage  = $this->exitBrokerageAmount($markPrice);
        $grossPnl       = ($markPrice - $entryPrice) * $this->direction() * $quantity * $multiplier;

        return round($grossPnl - $entryBrokerage - $exitBrokerage, 2);
    }

    public function isProfitable(): bool
    {
        return $this->realized_pnl !== null && $this->realized_pnl > 0;
    }

    public function isLoss(): bool
    {
        return $this->realized_pnl !== null && $this->realized_pnl < 0;
    }

    public function markPrice(): ?float
    {
        $price = $this->instrument?->current_price;

        return is_numeric($price) ? (float) $price : null;
    }

    public function tradeSide(): string
    {
        $entryFill = $this->entryFill();

        return $entryFill && $entryFill->isSell() ? 'SELL' : 'BUY';
    }

    public function brokerageRate(): float
    {
        return (float) ($this->instrument?->tradingAccount?->brokerage_percent ?? 0);
    }

    public function brokerageAmount(float $turnover): float
    {
        $rate = $this->brokerageRate();

        if ($rate <= 0) {
            return 0.0;
        }

        return round($turnover * ($rate / 100), 2);
    }

    public function entryAveragePrice(): ?float
    {
        $side = $this->tradeSide();
        $fills = $this->tradeFills()
            ->filter(fn (Fill $fill) => $fill->side === $side && $fill->datetime <= $this->open_datetime);

        return $this->weightedAverageFillPrice($fills);
    }

    public function exitAveragePrice(): ?float
    {
        if ($this->isOpen()) {
            return null;
        }

        // Find the exit fill: it's the fill after open_datetime up to close_datetime
        $fills = $this->tradeFills()->filter(function (Fill $fill) {
            return $fill->datetime > $this->open_datetime
                && $fill->datetime <= $this->close_datetime
                && (float) $fill->price > 0;  // ignore zero-price fills (bad data)
        });

        return $this->weightedAverageFillPrice($fills);
    }

    public function entryBrokerageAmount(): float
    {
        $entryPrice = $this->entryAveragePrice() ?? (float) $this->cost_basis;
        $turnover = $entryPrice * (float) $this->quantity * (float) ($this->instrument?->multiplier ?? 1);

        return $this->brokerageAmount($turnover);
    }

    public function exitBrokerageAmount(?float $markPrice = null): float
    {
        $exitPrice = $this->isClosed()
            ? $this->exitAveragePrice()
            : $markPrice;

        if ($exitPrice === null) {
            return 0.0;
        }

        $turnover = $exitPrice * (float) $this->quantity * (float) ($this->instrument?->multiplier ?? 1);

        return $this->brokerageAmount($turnover);
    }

    public function totalBrokerageAmount(?float $markPrice = null): float
    {
        return round($this->entryBrokerageAmount() + $this->exitBrokerageAmount($markPrice), 2);
    }

    protected function tradeFills(): Collection
    {
        if ($this->relationLoaded('fills')) {
            return $this->fills->sortBy('datetime')->values();
        }

        return $this->fills()->orderBy('datetime')->get();
    }

    protected function entryFill(): ?Fill
    {
        return $this->tradeFills()
            ->filter(fn (Fill $fill) => $fill->datetime <= $this->open_datetime)
            ->sortByDesc('datetime')
            ->first()
            ?? $this->tradeFills()->first();
    }

    protected function weightedAverageFillPrice(Collection $fills): ?float
    {
        if ($fills->isEmpty()) {
            return null;
        }

        $totalQuantity = (float) $fills->sum('quantity');

        if ($totalQuantity <= 0) {
            return null;
        }

        $totalValue = (float) $fills->sum(fn (Fill $fill) => $fill->price * $fill->quantity);

        return round($totalValue / $totalQuantity, 4);
    }

    public function direction(): int
    {
        return $this->tradeSide() === 'SELL' ? -1 : 1;
    }

    public function floatingPnL(): ?float
    {
        if ($this->isClosed()) {
            return null;
        }

        $markPrice = $this->markPrice();
        if ($markPrice === null) {
            return null;
        }

        $entryPrice = (float) $this->cost_basis;
        $quantity = (float) $this->quantity;
        $multiplier = (float) ($this->instrument->multiplier ?? 1);
        $entryBrokerage = $this->entryBrokerageAmount();

        return round(($markPrice - $entryPrice) * $this->direction() * $quantity * $multiplier - $entryBrokerage, 2);
    }
}
