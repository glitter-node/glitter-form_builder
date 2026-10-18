<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_forms', function (Blueprint $table) {
            $table->id()->comment('Internal form ID');
            $table->string('uuid', 36)->unique()->comment('Public UUID v7');
            $table->string('slug', 100)->unique()->comment('Public slug; immutable after first publish');
            $table->json('title')->comment('Locale-keyed title');
            $table->json('description')->nullable()->comment('Locale-keyed description');
            $table->string('status', 20)->default('draft')->comment('draft, published, archived');
            $table->json('draft_schema')->comment('Mutable draft working copy');
            $table->string('access_mode', 40)->default('authenticated_only');
            $table->string('retention_mode', 20)->default('manual');
            $table->unsignedInteger('retention_days')->nullable();
            $table->timestamp('retention_acknowledged_at')->nullable();
            $table->unsignedBigInteger('current_published_version_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('access_mode');
            $table->index('current_published_version_id');
            $table->index('created_by');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('form_forms', fn (Blueprint $table) => $table->comment('Glitter Form Builder forms'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_forms');
    }
};
