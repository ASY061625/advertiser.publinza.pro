<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the cart and the checkout need on top of what the schema already had.
 *
 * The article columns are columns rather than one JSON blob because the
 * checkout's whole content step is "how many of these are done" — a count over
 * `article_word_count IS NOT NULL` is a query, and the same question against a
 * blob is a PHP loop over every row in the cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table): void {
            // The promo lives on the cart, not in the session: a cart that
            // survives logout and a discount that does not is a cart that
            // silently gets more expensive on the next device.
            $table->foreignId('promo_code_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->boolean('express')->default(false)->after('unit_price_cents');

            // Warnings are advisory, so dismissing one has to stick — otherwise
            // the same "you placed here in March" reappears on every load and
            // the buyer learns to ignore the whole strip.
            $table->json('dismissed_warnings')->nullable()->after('addons');

            // The article, staged in the cart so the content step survives a
            // reload. It moves to `articles` when the order is placed.
            $table->string('article_title', 190)->nullable()->after('dismissed_warnings');
            $table->longText('article_body_html')->nullable()->after('article_title');
            $table->unsignedInteger('article_word_count')->nullable()->after('article_body_html');
            $table->string('article_file_path', 512)->nullable()->after('article_word_count');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('promo_code_id')->nullable()->after('discount_cents')->constrained()->nullOnDelete();
            // Snapshotted, not joined to the profile: an invoice has to keep
            // saying what it said when it was issued, and a company that moves
            // does not get to rewrite last quarter's receipts.
            $table->json('billing_details')->nullable()->after('paid_at');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->after('user_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn('order_id');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn('billing_details');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn([
                'express', 'dismissed_warnings', 'article_title',
                'article_body_html', 'article_word_count', 'article_file_path',
            ]);
        });

        Schema::table('carts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('promo_code_id');
        });
    }
};
