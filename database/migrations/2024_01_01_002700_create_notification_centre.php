<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            /*
             * The unread badge and the drawer's Unread tab both run
             * "mine, unread, newest first" on every page load. `morphs()`
             * already indexes (notifiable_type, notifiable_id); this adds the
             * two columns those queries actually filter and sort on, so the
             * count is an index scan rather than a read of every row this
             * account has ever been sent.
             */
            $table->index(
                ['notifiable_type', 'notifiable_id', 'read_at', 'created_at'],
                'notifications_inbox_index',
            );
        });

        /*
         * The 15-minute email window, one row per person per type.
         *
         * Two jobs in one table. `last_sent_at` is the throttle: a type that
         * was mailed inside the window is not mailed again. `pending_count`
         * and `pending_ids` are the batch: what was suppressed while the window
         * was closed, so the flush can send one summary naming all of it rather
         * than dropping it. A throttle without the second half is not batching,
         * it is losing notifications.
         */
        Schema::create('notification_digests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 48);
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('pending_count')->default(0);
            $table->json('pending_ids')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'type']);
            // The flush command's only query: rows with something waiting.
            $table->index(['pending_count', 'last_sent_at']);
        });

        Schema::table('changelog_entries', function (Blueprint $table): void {
            // What the spec calls it, and what it is: New / Improved / Fixed.
            $table->renameColumn('category', 'type');
        });

        Schema::table('changelog_entries', function (Blueprint $table): void {
            $table->string('image_path', 512)->nullable()->after('body');
            /*
             * A major entry earns a numeric badge and a one-time modal on the
             * next visit. Indexed with published_at because the modal lookup
             * runs on every authenticated page load.
             */
            $table->boolean('is_major')->default(false)->after('type');
            $table->index(['is_major', 'published_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // The spec's name for the column, and the clearer one: this is the
            // last time the changelog was *seen*, not an entry that was read.
            $table->renameColumn('changelog_read_at', 'last_seen_changelog_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            /*
             * Kept apart from last_seen_changelog_at on purpose.
             *
             * Opening the drawer clears the dot for everything. It must not
             * also dismiss a modal the person never saw — otherwise a major
             * release is announced to nobody who happened to glance at the
             * drawer first.
             */
            $table->timestamp('changelog_major_ack_at')->nullable()->after('last_seen_changelog_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('changelog_major_ack_at');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('last_seen_changelog_at', 'changelog_read_at');
        });

        Schema::table('changelog_entries', function (Blueprint $table): void {
            $table->dropIndex(['is_major', 'published_at']);
            $table->dropColumn(['image_path', 'is_major']);
        });

        Schema::table('changelog_entries', function (Blueprint $table): void {
            $table->renameColumn('type', 'category');
        });

        Schema::dropIfExists('notification_digests');

        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropIndex('notifications_inbox_index');
        });
    }
};
