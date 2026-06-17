<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance indexes for the most common query patterns:
 *
 * 1. instruments.trading_account_id — every Position query filters through
 *    whereHas('instrument', fn => where('trading_account_id', $id)), so this
 *    index eliminates full instrument table scans.
 *
 * 2. positions (instrument_id, close_datetime) — composite covering index
 *    for the dashboard/metrics queries that join instruments → positions and
 *    then filter/group by close_datetime.
 *
 * 3. positions (close_datetime, realized_pnl) — already exists from the
 *    original migration, kept for reference; skipped here.
 *
 * 4. fills (instrument_id, side) — brokerage calculations filter fills by
 *    instrument_id and side; the composite avoids a second pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        // instruments: fast lookup by trading account
        Schema::table('instruments', function (Blueprint $table) {
            // Guard: only add if not already present (migration may be re-run)
            if (!$this->indexExists('instruments', 'instruments_trading_account_id_index')) {
                $table->index('trading_account_id', 'instruments_trading_account_id_index');
            }
        });

        // positions: composite for account-scoped PnL queries
        Schema::table('positions', function (Blueprint $table) {
            if (!$this->indexExists('positions', 'positions_instrument_close_idx')) {
                $table->index(
                    ['instrument_id', 'close_datetime'],
                    'positions_instrument_close_idx'
                );
            }
        });

        // fills: composite for brokerage + tradeFills() filtering
        Schema::table('fills', function (Blueprint $table) {
            if (!$this->indexExists('fills', 'fills_instrument_side_idx')) {
                $table->index(
                    ['instrument_id', 'side'],
                    'fills_instrument_side_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            $table->dropIndex('instruments_trading_account_id_index');
        });

        Schema::table('positions', function (Blueprint $table) {
            $table->dropIndex('positions_instrument_close_idx');
        });

        Schema::table('fills', function (Blueprint $table) {
            $table->dropIndex('fills_instrument_side_idx');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        try {
            $indexes = \Illuminate\Support\Facades\DB::select(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?",
                [$table, $indexName]
            );
            return count($indexes) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
