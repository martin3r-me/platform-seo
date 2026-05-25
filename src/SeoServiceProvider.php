<?php

namespace Platform\Seo;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Platform\Core\PlatformCore;
use Platform\Core\Routing\ModuleRouter;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Services\SeoBudgetGuardService;
use Platform\Seo\Services\SeoKeywordService;
use Platform\Seo\Services\SeoClusteringService;
use Platform\Seo\Services\SeoKeywordCurationService;
use Platform\Seo\Services\SeoAnalysisService;
use Platform\Seo\Services\SeoSignalService;
use Platform\Seo\Services\SeoScoringService;
use Platform\Seo\Services\SeoUrlPipelineService;
use Platform\Seo\Services\SeoUrlService;
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
                \Platform\Seo\Console\Commands\RunPipeline::class,
                \Platform\Seo\Console\Commands\SnapshotUrls::class,
                \Platform\Seo\Console\Commands\DetectSignals::class,
                \Platform\Seo\Console\Commands\ResetBudgets::class,
                \Platform\Seo\Console\Commands\MigrateFromSyltjunkie::class,
            ]);
        }

        // Services
        $this->app->singleton(SeoBudgetGuardService::class);
        $this->app->singleton(SeoKeywordService::class);
        $this->app->singleton(SeoClusteringService::class);
        $this->app->singleton(SeoKeywordCurationService::class);
        $this->app->singleton(SeoAnalysisService::class);
        $this->app->singleton(SeoSignalService::class);
        $this->app->singleton(SeoScoringService::class);

        // URL-centric services
        $this->app->singleton(SeoUrlPipelineService::class, function ($app) {
            $pipeline = new SeoUrlPipelineService($app->make(SeoBudgetGuardService::class));

            // Register collectors from config
            $collectorClasses = config('seo.collectors', []);
            foreach ($collectorClasses as $collectorClass) {
                if (class_exists($collectorClass)) {
                    $collector = $app->make($collectorClass);
                    if ($collector instanceof SeoCollectorInterface) {
                        $pipeline->registerCollector($collector);
                    }
                }
            }

            return $pipeline;
        });

        $this->app->singleton(SeoUrlService::class);

        // Core-Contracts: URL-Service
        $this->app->singleton(
            \Platform\Core\Contracts\SeoUrlServiceInterface::class,
            fn ($app) => $app->make(SeoUrlService::class)
        );

        // Core-Contracts
        $this->app->singleton(
            \Platform\Core\Contracts\SeoKeywordServiceInterface::class,
            fn ($app) => $app->make(SeoKeywordService::class)
        );
        $this->app->singleton(
            \Platform\Core\Contracts\SeoAnalysisServiceInterface::class,
            fn ($app) => $app->make(SeoAnalysisService::class)
        );
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
        $this->registerTools();
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Dashboard & Analyse
            $registry->register(new \Platform\Seo\Tools\DashboardTool());
            $registry->register(new \Platform\Seo\Tools\AnalysisTool());
            $registry->register(new \Platform\Seo\Tools\CannibalizationTool());

            // URLs
            $registry->register(new \Platform\Seo\Tools\ListUrlsTool());
            $registry->register(new \Platform\Seo\Tools\RegisterUrlTool());
            $registry->register(new \Platform\Seo\Tools\DeleteUrlTool());
            $registry->register(new \Platform\Seo\Tools\EnrichUrlTool());

            // URL-Listen
            $registry->register(new \Platform\Seo\Tools\ListUrlListsTool());
            $registry->register(new \Platform\Seo\Tools\CreateUrlListTool());
            $registry->register(new \Platform\Seo\Tools\UpdateUrlListTool());
            $registry->register(new \Platform\Seo\Tools\DeleteUrlListTool());
            $registry->register(new \Platform\Seo\Tools\ManageUrlListEntriesTool());

            // Keywords
            $registry->register(new \Platform\Seo\Tools\ListKeywordsTool());
            $registry->register(new \Platform\Seo\Tools\CreateKeywordTool());
            $registry->register(new \Platform\Seo\Tools\UpdateKeywordTool());
            $registry->register(new \Platform\Seo\Tools\DiscoverKeywordsTool());
            $registry->register(new \Platform\Seo\Tools\AttachKeywordsTool());
            $registry->register(new \Platform\Seo\Tools\FetchMetricsTool());
            $registry->register(new \Platform\Seo\Tools\FetchRankingsTool());

            // Cluster
            $registry->register(new \Platform\Seo\Tools\ListClustersTool());
            $registry->register(new \Platform\Seo\Tools\CreateClusterTool());
            $registry->register(new \Platform\Seo\Tools\AutoClusterTool());

            // Signale
            $registry->register(new \Platform\Seo\Tools\ListSignalsTool());
            $registry->register(new \Platform\Seo\Tools\UpdateSignalTool());
        } catch (\Throwable $e) {
            \Log::warning('SEO: Tool-Registrierung fehlgeschlagen', ['error' => $e->getMessage()]);
        }
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
