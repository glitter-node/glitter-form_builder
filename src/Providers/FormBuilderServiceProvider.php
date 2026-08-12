<?php

namespace Modules\Glitter\FormBuilder\Providers;

use App\Extension\BaseModuleServiceProvider;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormVersionRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\Contracts\PublicFormRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\FormRepository;
use Modules\Glitter\FormBuilder\Repositories\FormVersionRepository;
use Modules\Glitter\FormBuilder\Repositories\PublicFormRepository;
use Modules\Glitter\FormBuilder\Repositories\Contracts\SubmissionRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\SubmissionRepository;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;

/**
 * Form Builder service provider.
 *
 * Repository bindings are container-only and do not access the database while
 * the application boots or before the module migrations are available.
 */
class FormBuilderServiceProvider extends BaseModuleServiceProvider
{
    protected string $moduleIdentifier = 'glitter-form_builder';

    protected array $repositories = [
        FormRepositoryInterface::class => FormRepository::class,
        FormVersionRepositoryInterface::class => FormVersionRepository::class,
        PublicFormRepositoryInterface::class => PublicFormRepository::class,
        SubmissionRepositoryInterface::class => SubmissionRepository::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->singleton(FieldTypeRegistry::class, static fn (): FieldTypeRegistry => new FieldTypeRegistry);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->bound(RateLimiter::class)) {
            $this->app->make(RateLimiter::class)->for('glitter-form_builder-submissions', static function (Request $request): Limit {
            $slug = (string) $request->route('slug');
            $user = $request->user();
            if ($user !== null) {
                return Limit::perMinute(30)->by($slug.'|user|'.$user->getAuthIdentifier());
            }

            return Limit::perMinute(10)->by($slug.'|guest|'.hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')));
            });
        }

        $registry = $this->app->make(FieldTypeRegistry::class);
        BuiltInFieldProviders::register($registry);

        $this->app->booted(static function () use ($registry): void {
            $registry->seal();
        });
    }
}
