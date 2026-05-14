<?php

namespace Platform\Seo;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Seo\Services\SeoBudgetGuardService;
use Platform\Seo\Services\SeoProjectService;
use Platform\Seo\Services\SeoKeywordService;
use Platform\Seo\Services\SeoClusteringService;
use Platform\Seo\Services\SeoKeywordCurationService;
use Platform\Seo\Services\SeoAnalysisService;
use Platform\Seo\Services\SeoSignalService;
use Platform\Seo\Services\SeoScoringService;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SeoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/seo.php', 'seo');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Platform\Seo\Console\Commands\RefreshKeywords::class,
                \Platform\Seo\Console\Commands\DetectSignals::class,
                \Platform\Seo\Console\Commands\ResetBudgets::class,
            ]);
        }

        $this->app->singleton(SeoBudgetGuardService::class);
        $this->app->singleton(SeoProjectService::class);
        $this->app->singleton(SeoKeywordService::class);
        $this->app->singleton(SeoClusteringService::class);
        $this->app->singleton(SeoKeywordCurationService::class);
        $this->app->singleton(SeoAnalysisService::class);
        $this->app->singleton(SeoSignalService::class);
        $this->app->singleton(SeoScoringService::class);
    }

    public function boot(): void
    {
        if (
            config()->has('seo.routing') &&
            config()->has('seo.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'seo',
                'title'      => 'SEO',
                'routing'    => config('seo.routing'),
                'guard'      => config('seo.guard'),
                'navigation' => config('seo.navigation'),
                'sidebar'    => config('seo.sidebar'),
            ]);
        }

        if (PlatformCore::getModule('seo')) {
            ModuleRouter::group('seo', function () {
                $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__.'/../config/seo.php' => config_path('seo.php'),
        ], 'config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'seo');

        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        $basePath = __DIR__ . '/Livewire';
        $baseNamespace = 'Platform\\Seo\\Livewire';
        $prefix = 'seo';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = str_replace($basePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $class = $baseNamespace . '\\' . $classPath;

            if (!class_exists($class)) {
                continue;
            }

            $aliasPath = str_replace(['\\', '/'], '.', Str::kebab(str_replace('.php', '', $relativePath)));
            $alias = $prefix . '.' . $aliasPath;

            Livewire::component($alias, $class);
        }
    }
}
