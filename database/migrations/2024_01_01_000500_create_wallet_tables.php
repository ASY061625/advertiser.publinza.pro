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
            // Two buckets. `available` is spendable; `frozen` is committed to
            // open orders. Both unsigned: the schema itself refuses an overdraw,
            // so a bug in the balance arithmetic fails loudly instead of
            // silently writing a negative balance.
            $table->unsignedBigInteger('available_cents')->default(0);
            $table->unsignedBigInteger('frozen_cents')->default(0);
            $table->char('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32)->index();
            // Signed: a charge is negative, a deposit positive. Stored as a
            // plain bigint rather than unsigned for that reason.
            $table->bigInteger('amount_cents');
            // The available balance immediately after this row was written.
            $table->unsignedBigInteger('balance_after_cents');
            // Not in the original column list, but without it a freeze/unfreeze
            // row cannot be reconciled: those move money between buckets and
            // leave the available balance unchanged.
            $table->unsignedBigInteger('frozen_after_cents');
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description', 255)->nullable();
            // Append-only: a correction is a new row, never an edit.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->unsignedBigInteger('subtotal_cents');
            $table->unsignedBigInteger('tax_cents')->default(0);
            $table->unsignedBigInteger('total_cents');
            $table->char('currency', 3)->default('USD');
            $table->string('status', 32)->default('draft')->index();
            $table->json('billing_details')->nullable();
            $table->string('pdf_path', 512)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32)->default('stripe');
            // Provider token only. Card numbers never reach this database.
            $table->string('provider_reference', 190);
            $table->string('brand', 32)->nullable();
            $table->char('last_four', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
            $table->index(['user_id', 'is_default']);
        });

        Schema::create('promo_codes', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 48)->unique();
            $table->string('description', 255)->nullable();
            // Exactly one of these is set, enforced in the model.
            $table->unsignedSmallInteger('percent_off')->nullable();
            $table->unsignedBigInteger('amount_off_cents')->nullable();
            $table->unsignedBigInteger('minimum_spend_cents')->default(0);
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedInteger('redemptions_count')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('promo_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('promo_code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->unsignedBigInteger('discount_cents');
            $table->timestamp('created_at')->useCurrent();

            // A code is redeemable once per advertiser.
            $table->unique(['promo_code_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_redemptions');
        Schema::dropIfExists('promo_codes');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
