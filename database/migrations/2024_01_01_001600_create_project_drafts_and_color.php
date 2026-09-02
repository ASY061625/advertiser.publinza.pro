<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * A half-finished wizard, kept off the projects table.
         *
         * Not a `draft` status on `projects`: that would put unfinished rows
         * inside every query that lists, counts or reports on projects, and
         * each of them would have to remember to exclude it. The last time
         * this codebase had a phantom 'draft' project status it produced three
         * separate silent failures. A draft is a different kind of thing —
         * it has no schema to satisfy and nothing depends on it.
         */
        Schema::create('project_drafts', function (Blueprint $table): void {
            $table->id();
            // One in-flight draft per advertiser: the wizard is a single
            // journey, and resuming it should not present a choice of which
            // half-finished project to continue.
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step')->default(1);
            // The whole wizard state as the client holds it. Deliberately
            // schemaless: a draft is not valid data yet, and forcing it into
            // columns would mean every partial answer had to satisfy the
            // constraints the finished project satisfies.
            $table->json('payload');
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table): void {
            // The dot in the projects list. Nullable, because every project
            // created before this column existed still needs a colour, and
            // null means "derive one from the id" rather than "no colour".
            $table->string('color', 7)->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('color');
        });

        Schema::dropIfExists('project_drafts');
    }
};
