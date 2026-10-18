<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_versions', function (Blueprint $table) {
            $table->id()->comment('Internal form version ID');
            $table->unsignedBigInteger('form_id');
            $table->unsignedInteger('revision');
            $table->string('schema_version', 20)->default('1.0');
            $table->json('snapshot')->comment('Immutable published form snapshot');
            $table->unsignedBigInteger('published_by')->nullable();
            $table->timestamp('published_at');

            $table->unique(['form_id', 'revision'], 'form_versions_form_revision_unique');
            $table->index('published_at');
            $table->foreign('form_id')->references('id')->on('form_forms')->restrictOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('form_forms', function (Blueprint $table) {
            $table->foreign('current_published_version_id', 'form_forms_current_version_foreign')
                ->references('id')->on('form_versions')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('form_versions', fn (Blueprint $table) => $table->comment('Immutable published form versions'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('form_forms')) {
            Schema::table('form_forms', fn (Blueprint $table) => $table->dropForeign('form_forms_current_version_foreign'));
        }
        Schema::dropIfExists('form_versions');
    }
};
