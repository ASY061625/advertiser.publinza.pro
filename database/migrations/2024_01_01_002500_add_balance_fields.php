<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table): void {
            /*
             * Auto top-up.
             *
             * Three columns rather than a JSON blob, because the job that
             * sweeps for wallets below their threshold has to be able to ask
             * the database "who is under?" without deserialising every row.
             */
            $table->boolean('auto_topup_enabled')->default(false)->after('currency');
            $table->unsignedBigInteger('auto_topup_threshold_cents')->nullable()->after('auto_topup_enabled');
            $table->unsignedBigInteger('auto_topup_amount_cents')->nullable()->after('auto_topup_threshold_cents');
            // Nullable on delete, not cascade: losing the card must not silently
            // delete the rule. It turns the rule off and says why.
            $table->foreignId('auto_topup_payment_method_id')
                ->nullable()
                ->after('auto_topup_amount_cents')
                ->constrained('payment_methods')
                ->nullOnDelete();

            $table->index(['auto_topup_enabled', 'available_cents']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // The profile already carries company, country and vat_no. These
            // two are what an invoice needs and the profile does not have.
            $table->string('billing_address', 500)->nullable()->after('vat_no');
            // Nullable, and the account email is the fallback. Somebody who has
            // never thought about it should still get their invoices.
            $table->string('billing_email', 190)->nullable()->after('billing_address');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            // What the invoice covers, which is not the same as when it was
            // issued — a monthly statement is issued after the period ends.
            $table->date('period_start')->nullable()->after('status');
            $table->date('period_end')->nullable()->after('period_start');
        });

        /*
         * One row per attempt to add funds, successful or not.
         *
         * Separate from `transactions`: the ledger records money that moved,
         * and a declined card moved none. Keeping failures here means the
         * decline reason survives to be shown, and the ledger stays a record of
         * facts rather than of intentions.
         */
        Schema::create('top_ups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 32);
            $table->unsignedBigInteger('amount_cents');
            // Computed server-side at the moment of payment and stored, so the
            // bonus an advertiser was shown is the bonus they got even if the
            // tiers change tomorrow.
            $table->unsignedBigInteger('bonus_cents')->default(0);
            $table->unsignedBigInteger('fee_cents')->default(0);
            $table->string('status', 32)->default('pending')->index();
            // The code a bank transfer must quote. Unique because it is how an
            // incoming payment is matched to an advertiser.
            $table->string('reference', 32)->unique();
            $table->string('provider_reference', 190)->nullable();
            // Null unless the gateway declined. Drives the copy that tells
            // somebody what to try next.
            $table->string('decline_code', 64)->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('top_ups');

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn(['period_start', 'period_end']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['billing_address', 'billing_email']);
        });

        Schema::table('wallets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('auto_topup_payment_method_id');
            $table->dropIndex(['auto_topup_enabled', 'available_cents']);
            $table->dropColumn([
                'auto_topup_enabled',
                'auto_topup_threshold_cents',
                'auto_topup_amount_cents',
            ]);
        });
    }
};
