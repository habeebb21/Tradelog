<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->decimal('starting_balance', 15, 2)->nullable()->after('currency');
            $table->decimal('brokerage_percent', 8, 4)->default(0)->after('starting_balance');
        });

        $initialBalances = DB::table('balances')
            ->select('trading_account_id', DB::raw('SUM(amount) as total_amount'))
            ->where('type', 'initial')
            ->groupBy('trading_account_id')
            ->get();

        foreach ($initialBalances as $balance) {
            if ($balance->trading_account_id) {
                DB::table('trading_accounts')
                    ->where('id', $balance->trading_account_id)
                    ->update([
                        'starting_balance' => $balance->total_amount,
                    ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('trading_accounts', function (Blueprint $table) {
            $table->dropColumn(['starting_balance', 'brokerage_percent']);
        });
    }
};
