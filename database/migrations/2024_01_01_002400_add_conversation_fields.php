<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table): void {
            // Muting is per thread, not per account. Somebody chasing one late
            // post wants that thread quiet without going deaf to the other six.
            $table->timestamp('muted_at')->nullable()->after('status');
        });

        Schema::table('messages', function (Blueprint $table): void {
            /*
             * The browser's id for a message it has already drawn.
             *
             * Sending is optimistic and a failed send offers a retry, so the
             * one thing that must not happen is a retry of a request that
             * actually succeeded posting the message twice. The client mints
             * this before the first attempt and reuses it on every retry; the
             * unique index below turns the second write into a no-op we can
             * detect rather than a duplicate.
             */
            $table->string('client_token', 40)->nullable()->after('body');
            $table->unique(['conversation_id', 'client_token']);
        });

        Schema::table('users', function (Blueprint $table): void {
            // Opt-out, not opt-in: a reply nobody sees is the failure mode this
            // email exists to prevent.
            $table->boolean('notify_replies')->default(true)->after('sidebar_collapsed');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('notify_replies');
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropUnique(['conversation_id', 'client_token']);
            $table->dropColumn('client_token');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn('muted_at');
        });
    }
};
