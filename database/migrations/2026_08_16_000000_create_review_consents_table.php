<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('review_consents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('review_id')->constrained('reviews')->onDelete('cascade');
            $table->uuid('evidence_id');

            $table->string('consent_full_name');
            $table->string('consent_email');

            $table->timestamp('user_agreement_accepted_at');
            $table->timestamp('personal_data_consent_accepted_at');
            $table->timestamp('review_publication_consent_accepted_at');

            $table->string('user_agreement_version');
            $table->string('personal_data_consent_version');
            $table->string('review_publication_consent_version');

            $table->json('publication_scope');
            $table->text('publication_conditions')->nullable();

            $table->char('review_payload_sha256', 64);

            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            $table->unique('review_id');
            $table->unique('evidence_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('review_consents');
    }
};
