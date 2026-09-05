<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the add-post wizard needs on top of the cart's own schema.
 *
 * The wizard is the same purchase as the cart, entered from the post side, so
 * it converges on `cart_items` rather than growing a parallel table. What it
 * adds is the brief: the cart flow buys a placement and the wizard commissions
 * one, and a commission has instructions attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * A half-finished wizard, kept off the posts table.
         *
         * The same reasoning as `project_drafts`: a `draft` row inside `posts`
         * would land in every query that lists, counts or bills posts, and each
         * of them would have to remember to exclude it. `PostStatus::Draft`
         * already means something different and real — a bought placement
         * waiting on its article — and overloading it would make "draft" two
         * things at once.
         */
        Schema::create('post_drafts', function (Blueprint $table): void {
            // One in-flight draft per advertiser: the wizard is a single
            // journey, and resuming should not present a menu of half-finished
            // posts to choose between.
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            // Schemaless on purpose: a draft is not valid data yet, and forcing
            // partial answers to satisfy the finished post's constraints would
            // mean it could not be saved until it no longer needed saving.
            $table->json('payload');
            $table->timestamps();
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            // The instructions a publisher writes from: the brief itself, the
            // keywords, the tone and the target length. One column because they
            // are one document — nobody queries "posts with a formal tone", and
            // four columns would imply somebody might.
            $table->json('brief')->nullable()->after('article_file_path');
            $table->string('image_path', 512)->nullable()->after('brief');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->json('brief')->nullable()->after('content_mode');
        });

        Schema::table('websites', function (Blueprint $table): void {
            // The lengths a publisher will write to, as a list of word counts.
            // Length only, not price: a publisher who writes 1,500 words for
            // the same fee as 800 is offering a choice, not a second product,
            // and pricing it would mean teaching the whole money path a new
            // kind of line item for a difference the publisher is absorbing.
            $table->json('word_count_tiers')->nullable()->after('min_words');
        });
    }

    public function down(): void
    {
        Schema::table('websites', function (Blueprint $table): void {
            $table->dropColumn('word_count_tiers');
        });

        Schema::table('posts', function (Blueprint $table): void {
            $table->dropColumn('brief');
        });

        Schema::table('cart_items', function (Blueprint $table): void {
            $table->dropColumn(['brief', 'image_path']);
        });

        Schema::dropIfExists('post_drafts');
    }
};
