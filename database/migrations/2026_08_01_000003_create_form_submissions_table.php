<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->unsignedBigInteger('form_id');
            $table->unsignedBigInteger('form_version_id');
            $table->string('status', 24)->default('received');
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->string('actor_type', 24);
            $table->json('normalized_payload');
            $table->char('schema_snapshot_hash', 64);
            $table->char('payload_hash', 64);
            $table->string('idempotency_key', 128)->nullable();
            $table->char('idempotency_scope', 64)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('source', 24)->default('public');
            $table->char('ip_hash', 64)->nullable();
            $table->char('user_agent_hash', 64)->nullable();
            $table->string('retention_mode', 20);
            $table->unsignedInteger('retention_days')->nullable();
            $table->timestamp('retention_due_at')->nullable();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['form_version_id', 'idempotency_scope', 'idempotency_key'], 'form_submissions_idempotency_unique');
            $table->index(['form_id', 'received_at']);
            $table->index(['status', 'received_at']);
            $table->index('submitted_by');
            $table->foreign('form_id')->references('id')->on('form_forms')->restrictOnDelete();
            $table->foreign('form_version_id')->references('id')->on('form_versions')->restrictOnDelete();
            $table->foreign('submitted_by')->references('id')->on('users')->nullOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('form_submissions', fn (Blueprint $table) => $table->comment('Immutable accepted form submissions'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
