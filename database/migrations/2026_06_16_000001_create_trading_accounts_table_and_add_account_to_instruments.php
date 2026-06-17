<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('broker_name')->nullable();
            $table->string('market')->default('NSE / F&O');
            $table->string('currency', 10)->default('INR');
            $table->boolean('is_default')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->foreignId('trading_account_id')
                ->nullable()
                ->after('user_id')
                ->constrained('trading_accounts')
                ->nullOnDelete();
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->dropUnique('instruments_unique');
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->unique(
                ['trading_account_id', 'symbol', 'expiry', 'strike', 'put_call', 'asset_type'],
                'instruments_unique'
            );
        });

        $users = DB::table('users')->orderBy('id')->get();

        foreach ($users as $user) {
            $accountId = DB::table('trading_accounts')->insertGetId([
                'user_id' => $user->id,
                'name' => 'Main Trading Account',
                'broker_name' => 'Indian Markets',
                'market' => 'NSE / F&O',
                'currency' => 'INR',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('instruments')
                ->where('user_id', $user->id)
                ->update(['trading_account_id' => $accountId]);
        }
    }

    public function down(): void
    {
        Schema::table('instruments', function (Blueprint $table) {
            $table->dropUnique('instruments_unique');
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trading_account_id');
        });

        Schema::table('instruments', function (Blueprint $table) {
            $table->unique(['symbol', 'expiry', 'strike', 'put_call', 'asset_type'], 'instruments_unique');
        });

        Schema::dropIfExists('trading_accounts');
    }
};
