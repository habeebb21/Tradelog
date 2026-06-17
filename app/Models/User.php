<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TradingAccount;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'ib_flex_token',
        'ib_query_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function instruments(): HasMany
    {
        return $this->hasMany(Instrument::class);
    }

    public function diaryEntries(): HasMany
    {
        return $this->hasMany(DiaryEntry::class);
    }

    public function tradingAccounts(): HasMany
    {
        return $this->hasMany(TradingAccount::class);
    }

    public function defaultTradingAccount(): ?TradingAccount
    {
        return $this->tradingAccounts()->where('is_default', true)->first()
            ?? $this->tradingAccounts()->orderBy('id')->first();
    }

    /**
     * Resolve the active trading account for account-scoped pages.
     * The "all" session value is reserved for trade-log filtering only.
     */
    public function activeTradingAccount(): ?TradingAccount
    {
        $activeAccountId = session('active_trading_account_id');

        if ($activeAccountId && $activeAccountId !== 'all') {
            $account = $this->tradingAccounts()->where('id', (int) $activeAccountId)->first();
            if ($account) {
                return $account;
            }
        }

        return $this->defaultTradingAccount();
    }

    public function activeTradingAccountId(): ?int
    {
        return $this->activeTradingAccount()?->id;
    }

    public function ensureDefaultTradingAccount(): TradingAccount
    {
        $account = $this->defaultTradingAccount();

        if ($account) {
            return $account;
        }

        return $this->tradingAccounts()->create([
            'name' => 'Main Trading Account',
            'broker_name' => 'Indian Markets',
            'market' => 'NSE / F&O',
            'currency' => 'INR',
            'is_default' => true,
        ]);
    }
}
