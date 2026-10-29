<?php namespace Aero\Viral\Updates;

use Illuminate\Support\Facades\Schema;
use October\Rain\Database\Schema\Blueprint;
use October\Rain\Database\Updates\Migration;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('aero_viral_applications')) {
            Schema::create('aero_viral_applications', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->unsignedBigInteger('user_id')->index();            // RainLab.User
                $t->string('track', 16);                               // shorts | live | edit
                $t->unsignedTinyInteger('level')->default(1);          // 1..4
                $t->json('tags')->nullable();
                $t->text('description')->nullable();
                $t->string('phone', 32)->nullable();
                $t->string('department', 64)->nullable();
                $t->string('city', 96)->nullable();
                $t->boolean('offer_to_clients')->default(false);
                $t->timestamp('terms_accepted_at')->nullable();
                $t->timestamp('adult_confirmed_at')->nullable();
                $t->string('status', 24)->default('received')->index(); // received|in_review|changes_requested|approved|rejected
                $t->string('kyc_status', 16)->default('none');          // none|pending|verified|rejected
                $t->unsignedBigInteger('reviewer_id')->nullable();
                $t->timestamp('reviewed_at')->nullable();
                $t->text('review_notes')->nullable();
                $t->timestamps();
                $t->index(['status', 'created_at']);
            });
        }

        if (!Schema::hasTable('aero_viral_samples')) {
            Schema::create('aero_viral_samples', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('application_id')->index();
                $t->string('kind', 12);                                 // link | file | recording
                $t->string('url', 1000)->nullable();
                $t->string('platform', 32)->nullable();
                $t->string('path', 500)->nullable();                    // clave en el disco privado
                $t->string('mime', 96)->nullable();
                $t->unsignedBigInteger('size_bytes')->nullable();
                $t->unsignedInteger('duration_seconds')->nullable();
                $t->string('status', 12)->default('pending');           // pending | ready | rejected
                $t->timestamps();
            });
        }

        if (!Schema::hasTable('aero_viral_kyc_checks')) {
            Schema::create('aero_viral_kyc_checks', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('application_id')->index();
                $t->unsignedBigInteger('user_id')->index();
                $t->string('driver', 24)->default('manual');
                $t->string('status', 12)->default('pending')->index();  // pending|verified|rejected|expired
                $t->string('doc_front_path', 500)->nullable();
                $t->string('doc_back_path', 500)->nullable();
                $t->string('selfie_path', 500)->nullable();
                $t->text('doc_number_enc')->nullable();                 // cifrado
                $t->string('external_ref', 120)->nullable();            // id en el proveedor externo
                $t->unsignedBigInteger('reviewer_id')->nullable();
                $t->timestamp('reviewed_at')->nullable();
                $t->string('reject_reason', 500)->nullable();
                $t->timestamp('purge_at')->nullable()->index();         // borrado de documentos
                $t->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('aero_viral_kyc_checks');
        Schema::dropIfExists('aero_viral_samples');
        Schema::dropIfExists('aero_viral_applications');
    }
};
