<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 190);
            $table->string('slug', 190)->unique();
            $table->string('excerpt', 500);
            // Authored by our own editors, so it is rendered unescaped. Nothing
            // user-submitted is ever written here.
            $table->longText('body_html');
            $table->string('author', 120);
            $table->unsignedTinyInteger('reading_minutes')->default(4);
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
