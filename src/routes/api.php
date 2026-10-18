<?php

use Illuminate\Support\Facades\Route;
use Modules\Glitter\FormBuilder\Http\Controllers\Admin\FormController;
use Modules\Glitter\FormBuilder\Http\Controllers\Admin\FieldTypeController;
use Modules\Glitter\FormBuilder\Http\Controllers\Admin\SchemaValidationController;
use Modules\Glitter\FormBuilder\Http\Controllers\Admin\SubmissionController;
use Modules\Glitter\FormBuilder\Http\Controllers\User\PublicFormSchemaController;
use Modules\Glitter\FormBuilder\Http\Controllers\User\PublicSubmissionController;

Route::prefix('forms')
    ->middleware(['optional.sanctum', 'throttle:600,1'])
    ->name('forms.')
    ->group(function () {
        Route::get('/{slug}/schema', [PublicFormSchemaController::class, 'show'])
            ->where('slug', '[a-z0-9][a-z0-9-]{0,127}')
            ->name('schema.show');
        Route::post('/{slug}/submissions', [PublicSubmissionController::class, 'store'])
            ->middleware('throttle:glitter-form_builder-submissions')
            ->where('slug', '[a-z0-9][a-z0-9-]{0,127}')
            ->name('submissions.store');
    });

Route::prefix('admin')
    ->middleware(['auth:sanctum', 'throttle:600,1', 'permission:admin,glitter-form_builder.forms.read'])
    ->name('admin.')
    ->group(function () {
        Route::get('/field-types', [FieldTypeController::class, 'index'])->name('field-types.index');
        Route::post('/forms/validate-schema', [SchemaValidationController::class, 'validateSchema'])->name('forms.validate-schema');
    });

Route::prefix('admin/forms')
    ->middleware(['auth:sanctum', 'throttle:600,1'])
    ->name('admin.forms.')
    ->group(function () {
        Route::get('/', [FormController::class, 'index'])
            ->middleware('permission:admin,glitter-form_builder.forms.read')->name('index');
        Route::post('/', [FormController::class, 'store'])
            ->middleware('permission:admin,glitter-form_builder.forms.create')->name('store');
        Route::post('/{form}/publish', [FormController::class, 'publish'])
            ->middleware('permission:admin,glitter-form_builder.forms.publish')->name('publish');
        Route::get('/{form}', [FormController::class, 'show'])
            ->middleware('permission:admin,glitter-form_builder.forms.read')->name('show');
        Route::put('/{form}', [FormController::class, 'update'])
            ->middleware('permission:admin,glitter-form_builder.forms.update')->name('update');
        Route::delete('/{form}', [FormController::class, 'destroy'])
            ->middleware('permission:admin,glitter-form_builder.forms.delete')->name('destroy');
    });

Route::prefix('admin/submissions')
    ->middleware(['auth:sanctum', 'throttle:600,1', 'permission:admin,glitter-form_builder.submissions.read'])
    ->name('admin.submissions.')
    ->group(function () {
        Route::get('/', [SubmissionController::class, 'index'])->name('index');
        Route::get('/{submission}', [SubmissionController::class, 'show'])
            ->whereUuid('submission')->name('show');
    });
