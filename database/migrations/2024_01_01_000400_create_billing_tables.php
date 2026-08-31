<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Money is stored in minor units. Nothing in this schema is a float.
            $table->bigInteger('balance_minor_units')->default(0);
            $table->bigInteger('frozen_minor_units')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24)->index();
            $table->bigInteger('amount_minor_units');
            $table->string('reference_type', 32)->nullable();
            $table->string('reference_id', 64)->nullable();
            $table->string('note', 500)->nullable();
            // Append-only ledger: rows are created and never updated.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('total_minor_units');
            $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
