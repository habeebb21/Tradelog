<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop old unique constraint
        Schema::table('balances', function (Blueprint $table) {
            $table->dropUnique('unique_initial_balance');
        });

        // 2. Add trading_account_id column
        Schema::table('balances', function (Blueprint $table) {
            $table->foreignId('trading_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('trading_accounts')
                ->cascadeOnDelete();
        });

        // 3. Populate existing balances with default trading accounts
        $balances = DB::table('balances')->get();
        foreach ($balances as $balance) {
            $defaultAccount = DB::table('trading_accounts')
                ->where('user_id', $balance->user_id)
                ->where('is_default', true)
                ->first()
                ?? DB::table('trading_accounts')
                ->where('user_id', $balance->user_id)
                ->first();

            if ($defaultAccount) {
                DB::table('balances')
                    ->where('id', $balance->id)
                    ->update(['trading_account_id' => $defaultAccount->id]);
            }
        }

        // 4. Add new unique index per trading account for the initial balance
        Schema::table('balances', function (Blueprint $table) {
            $table->unique(['trading_account_id', 'type'], 'unique_account_initial_balance')
                  ->where('type', 'initial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('balances', function (Blueprint $table) {
            $table->dropUnique('unique_account_initial_balance');
        });

        Schema::table('balances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trading_account_id');
        });

        Schema::table('balances', function (Blueprint $table) {
            $table->unique(['user_id', 'type'], 'unique_initial_balance')
                  ->where('type', 'initial');
        });
    }
};
