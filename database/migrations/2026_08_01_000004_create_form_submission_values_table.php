<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_values', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('submission_id');
            $table->string('field_key', 64);
            $table->string('field_type', 129);
            $table->string('value_kind', 24);
            $table->text('value_text')->nullable();
            $table->decimal('value_number', 36, 12)->nullable();
            $table->boolean('value_boolean')->nullable();
            $table->date('value_date')->nullable();
            $table->dateTime('value_datetime')->nullable();
            $table->char('value_hash', 64)->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->boolean('is_indexable')->default(false);
            $table->unsignedSmallInteger('position');

            $table->unique(['submission_id', 'field_key'], 'form_submission_values_field_unique');
            $table->index(['field_key', 'value_number']);
            $table->index(['field_key', 'value_boolean']);
            $table->index(['field_key', 'value_date']);
            $table->index(['field_key', 'value_datetime']);
            $table->foreign('submission_id')->references('id')->on('form_submissions')->cascadeOnDelete();
        });

        if (DB::getDriverName() === 'mysql') {
            Schema::table('form_submission_values', fn (Blueprint $table) => $table->comment('Derived non-sensitive typed submission index'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_values');
    }
};
