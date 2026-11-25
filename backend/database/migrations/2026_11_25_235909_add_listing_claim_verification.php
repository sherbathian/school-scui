<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listing_claims', fn (Blueprint $table) => $table->timestamp('email_verified_at')->nullable());
        Schema::create('listing_claim_verification_codes', function (Blueprint $table): void {
            $table->foreignId('listing_claim_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_claim_verification_codes');
        Schema::table('listing_claims', fn (Blueprint $table) => $table->dropColumn('email_verified_at'));
    }
};
