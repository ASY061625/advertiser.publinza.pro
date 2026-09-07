<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // What the app calls them, which is not always their legal name.
            // Invoices use `name`; the header and messages use this.
            $table->string('display_name', 60)->nullable()->after('name');
            $table->string('avatar_path', 512)->nullable()->after('display_name');
            // Kept apart from the number itself, so "+44" and "7700 900123"
            // stay separately editable and a country change does not have to
            // reparse a string somebody typed.
            $table->char('phone_country', 2)->nullable()->after('phone');

            /*
             * Rendering preferences.
             *
             * Stored as pattern names rather than ICU skeletons: the set is
             * small, the UI shows an example of each, and a free-text ICU
             * pattern is a way for somebody to break every date on the site.
             */
            $table->string('date_format', 16)->default('medium')->after('locale');
            $table->string('number_format', 16)->default('plain')->after('date_format');

            /*
             * An email change in flight.
             *
             * The new address lives here until it is confirmed — never in
             * `email` — so a typo cannot lock somebody out of their own
             * account, and a hijacked session cannot change the address that
             * password resets go to.
             */
            $table->string('pending_email', 190)->nullable()->after('email_verified_at');
            $table->string('pending_email_token', 64)->nullable()->after('pending_email');
            $table->timestamp('pending_email_sent_at')->nullable()->after('pending_email_token');

            // Company details beyond what signup collects.
            $table->string('registration_no', 64)->nullable()->after('vat_no');
            $table->string('company_logo_path', 512)->nullable()->after('registration_no');

            // A quiet period for everything that is not about money or orders.
            $table->timestamp('notifications_paused_until')->nullable()->after('sidebar_collapsed');

            /*
             * Superseded by notification_preferences.
             *
             * This column answered "email me when the team replies", which is
             * one cell of the matrix below. Two places answering the same
             * question is how somebody ends up muted from something they never
             * muted, so the column goes and the row wins.
             */
            $table->dropColumn('notify_replies');

            // Set when deletion is requested; the account is retained for 30
            // days and can be recovered by signing in during that window.
            $table->timestamp('deletion_requested_at')->nullable()->after('notifications_paused_until');
        });

        /*
         * Which events reach somebody, and how.
         *
         * A table rather than a JSON blob on the user, because the question the
         * sending code asks is "who wants an email about this event" — and that
         * has to be a query, not a scan of every row deserialised in PHP.
         *
         * A missing row means the event's own default applies, so a new event
         * type does not need a backfill before anybody hears about it.
         */
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event', 48);
            $table->boolean('email')->default(true);
            $table->boolean('in_app')->default(true);
            $table->boolean('push')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'event']);
        });

        /*
         * Personal access tokens.
         *
         * The token is stored as a SHA-256 hash: it is a bearer credential, so
         * the plaintext exists once, in the response that created it. Hash
         * rather than bcrypt because a lookup has to be a single indexed
         * SELECT, and the token is 40 random bytes rather than a password
         * somebody chose.
         */
        Schema::create('personal_access_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->char('token', 64)->unique();
            $table->json('abilities');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::table('login_attempts', function (Blueprint $table): void {
            // Recorded at sign-in from the edge's own header. Null where the
            // deployment has no such header — the UI says "Unknown" rather
            // than guessing.
            $table->char('country', 2)->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('login_attempts', function (Blueprint $table): void {
            $table->dropColumn('country');
        });

        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('notification_preferences');

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('notify_replies')->default(true)->after('sidebar_collapsed');

            $table->dropColumn([
                'display_name', 'avatar_path', 'phone_country', 'date_format', 'number_format',
                'pending_email', 'pending_email_token', 'pending_email_sent_at',
                'registration_no', 'company_logo_path',
                'notifications_paused_until', 'deletion_requested_at',
            ]);
        });
    }
};
