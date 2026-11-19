<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('city_id')->constrained()->restrictOnDelete();
            $table->foreignId('area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('author', 150)->nullable();
            $table->string('subject', 80)->nullable();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('board_id')->nullable()->constrained()->nullOnDelete();
            $table->string('condition', 20)->default('good');
            $table->string('listing_type', 10)->default('sell');
            $table->decimal('price', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('status', 20)->default('available')->index();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('book_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('book_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('study_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 200);
            $table->string('subject', 80)->nullable();
            $table->foreignId('education_level_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('file_path');
            $table->integer('download_count')->default(0);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_resources');
        Schema::dropIfExists('book_inquiries');
        Schema::dropIfExists('books');
    }
};
