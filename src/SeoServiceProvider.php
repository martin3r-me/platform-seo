<?php

namespace Platform\Seo;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
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
                \Platform\Seo\Console\Commands\RefreshPortfolios::class,
                \Platform\Seo\Console\Commands\SnapshotUrls::class,
                \Platform\Seo\Console\Commands\EvaluateSignals::class,
                \Platform\Seo\Console\Commands\EnrichSignals::class,
                \Platform\Seo\Console\Commands\DispatchSignals::class,
                \Platform\Seo\Console\Commands\ReconcileContentBriefs::class,
                \Platform\Seo\Console\Commands\ClassifyIntent::class,
                \Platform\Seo\Console\Commands\SyncGeoCatalog::class,
                \Platform\Seo\Console\Commands\MigrateDataProfiles::class,
                \Platform\Seo\Console\Commands\RefreshCompetitorFootprints::class,
                \Platform\Seo\Console\Commands\ArchiveLegacySignals::class,
                \Platform\Seo\Console\Commands\RefreshCompetitors::class,
                \Platform\Seo\Console\Commands\ResetBudgets::class,
                \Platform\Seo\Console\Commands\MigrateFromSyltjunkie::class,
                \Platform\Seo\Console\Commands\InspectLinks::class,
                \Platform\Seo\Console\Commands\SnapshotClusters::class,
                \Platform\Seo\Console\Commands\DiscoverClusters::class,
                \Platform\Seo\Console\Commands\MigrateFromBrands::class,
                \Platform\Seo\Console\Commands\PlausibleDoctor::class,
                \Platform\Seo\Console\Commands\SeoEmbedKeywords::class,
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
        $this->app->singleton(\Platform\Seo\Services\SeoOrganizationLinker::class);
        $this->app->singleton(\Platform\Seo\Services\SeoClusterMetricsService::class);
        $this->app->singleton(\Platform\Seo\Services\SeoSemanticMapService::class);
        $this->app->singleton(\Platform\Seo\Services\SeoSignalReadService::class);
        $this->app->singleton(
            \Platform\Core\Contracts\SeoSignalServiceInterface::class,
            fn ($app) => $app->make(\Platform\Seo\Services\SeoSignalReadService::class)
        );

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
        Relation::morphMap([
            'seo_url' => \Platform\Seo\Models\SeoUrl::class,
            'seo_url_list' => \Platform\Seo\Models\SeoUrlList::class,
            'seo_cluster' => \Platform\Seo\Models\SeoKeywordCluster::class,
            'seo_content_brief' => \Platform\Seo\Models\SeoContentBrief::class,
            'seo_signal' => \Platform\Seo\Models\SeoSignal::class,
            'seo_portfolio' => \Platform\Seo\Models\SeoPortfolio::class,
        ]);

        if (
            config()->has('seo.routing') &&
            config()->has('seo.navigation') &&
            Schema::hasTable('modules')
        ) {
            PlatformCore::registerModule([
                'key'        => 'seo',
                'title'      => 'SEO',
                'group'      => 'digital',
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
        $this->registerSchedule();

        // Keyword-Embeddings liegen in Qdrant (ANN, skaliert auf die Regionsraum-/
        // Mehr-Feld-Größe der Mission), nicht im MySQL-Brute-Force-Store. Reine
        // Routing-Entscheidung — Provider/Service bleiben core-seitig gleich.
        try {
            resolve(\Platform\Core\Services\EmbeddingStoreRegistry::class)
                ->route('seo_keyword', 'qdrant');
        } catch (\Throwable $e) {
            // Core-Embedding-Infra nicht geladen
        }

        try {
            resolve(\Platform\Organization\Services\EntityLinkRegistry::class)
                ->register(new \Platform\Seo\Organization\SeoEntityLinkProvider());
        } catch (\Throwable $e) {
            // Organization-Modul nicht geladen
        }

        try {
            resolve(\Platform\FlynkConnector\Services\FlynkContextRegistry::class)
                ->register(new \Platform\Seo\Organization\SeoFlynkContextProvider());
        } catch (\Throwable $e) {
            // Flynk-Connector nicht geladen
        }
    }

    protected function registerSchedule(): void
    {
        // Alle 2 Wochen Sonntag 02:00 — Keywords + Rankings aktualisieren
        Schedule::command('seo:refresh-keywords')
            ->weeklyOn(0, '02:00')
            ->when(fn () => now()->weekOfYear % 2 === 0)
            ->withoutOverlapping()
            ->runInBackground();

        // Alle 2 Wochen Sonntag 02:30 — SERP-Competitors nur für neue Keywords ohne Daten
        Schedule::command('seo:refresh-competitors --only-new')
            ->weeklyOn(0, '02:30')
            ->when(fn () => now()->weekOfYear % 2 === 0)
            ->withoutOverlapping()
            ->runInBackground();

        // Täglich 03:00 — vollständige URL-Pipeline. Der Cron ist nur der
        // Herzschlag; WAS tatsächlich geholt wird, entscheiden die per-Collector-
        // Intervalle (config('seo.refresh_intervals'), priority-skaliert via
        // SeoUrl::getEffectiveRefreshInterval) plus der Budget-Guard (max.
        // max_budget_percentage_per_run des Monatsbudgets pro Lauf, Monats-Cap
        // via canFetch). Dadurch greifen die konfigurierten Frequenzen real:
        // GSC/Plausible ~täglich (kostenlos), SERP/Keyword-Metriken wöchentlich,
        // Backlinks/OnPage 2-wöchentlich, LLM-Mentions ~monatlich — statt alles
        // pauschal alle 2 Wochen zu holen. Nur fällige URLs je Collector werden
        // angefasst, der Rest wird übersprungen.
        Schedule::command('seo:pipeline')
            ->dailyAt('03:00')
            ->withoutOverlapping()
            ->runInBackground();

        // ~2-wöchentlich (1. + 15., 04:30) — Wirkungsraum-Refresh: Nachfrage laden,
        // Entitäten mergen, Maßnahmen erzeugen (v1+v2+KI). Läuft nach der nächtlichen
        // Pipeline, damit frische Daten drinstecken. Macht den Posteingang autonom.
        Schedule::command('seo:refresh-portfolios')
            ->twiceMonthly(1, 15, '04:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Täglich 03:30 — Keyword-Embeddings auffrischen (OpenAI→Qdrant). Nach der
        // Pipeline (03:00), damit neu entdeckte Keywords desselben Nachts embedded
        // werden. Skip-if-unchanged → nur neue/geänderte kosten etwas (Cent-Bereich).
        Schedule::command('seo:embed-keywords')
            ->dailyAt('03:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Täglich 04:00 — Cluster-Erfolgsmessung (reine DB-Aggregation, keine API-Kosten)
        Schedule::command('seo:snapshot-clusters')
            ->dailyAt('04:00')
            ->withoutOverlapping()
            ->runInBackground();

        // --- Signal-Kette: aus den frischen Daten die Arbeitsobjekte machen ---
        // 04:30 erkennen → 04:45 KI-anreichern → 05:00 routen (Content-Brief / Flynk).
        // Die Governance (WIP-Limit, Tageslimit) deckelt, wie viel morgens auftaucht.
        Schedule::command('seo:evaluate-signals')
            ->dailyAt('04:30')
            ->withoutOverlapping()
            ->runInBackground();

        Schedule::command('seo:enrich-signals')
            ->dailyAt('04:45')
            ->withoutOverlapping()
            ->runInBackground();

        Schedule::command('seo:dispatch-signals')
            ->dailyAt('05:00')
            ->withoutOverlapping()
            ->runInBackground();

        // Täglich 05:15 — Content-Briefs mit ihren Live-Seiten abgleichen (Marker im
        // <head>). Leichter HTTP-Fetch, keine API-Kosten; setzt published + trackt die URL.
        Schedule::command('seo:reconcile-briefs')
            ->dailyAt('05:15')
            ->withoutOverlapping()
            ->runInBackground();

        // Monatlich (1. um 00:05) — Monats-Budget zurücksetzen, sonst blockiert der
        // Budget-Guard irgendwann alle Fetches.
        Schedule::command('seo:reset-budgets')
            ->monthlyOn(1, '00:05')
            ->withoutOverlapping()
            ->runInBackground();

        // Wöchentlich (Mo 05:30) — fehlendes search_intent nachziehen. Günstiger
        // Bulk-Call; selbstwartend für neue Keywords aus der Discovery.
        Schedule::command('seo:classify-intent')
            ->weeklyOn(1, '05:30')
            ->withoutOverlapping()
            ->runInBackground();

        // Täglich 02:30 — Wettbewerber-Footprints; die Profil-Kadenz (Standard
        // monatlich) gated, welche URL wirklich fällig ist. Günstig.
        Schedule::command('seo:refresh-footprints')
            ->dailyAt('02:30')
            ->withoutOverlapping()
            ->runInBackground();
    }

    protected function registerTools(): void
    {
        try {
            $registry = resolve(\Platform\Core\Tools\ToolRegistry::class);

            // Dashboard & Analyse
            $registry->register(new \Platform\Seo\Tools\DashboardTool());
            $registry->register(new \Platform\Seo\Tools\AnalysisTool());
            $registry->register(new \Platform\Seo\Tools\DataCostsTool());
            $registry->register(new \Platform\Seo\Tools\SetNodeProfileTool());
            $registry->register(new \Platform\Seo\Tools\CannibalizationTool());

            // URLs
            $registry->register(new \Platform\Seo\Tools\ListUrlsTool());
            $registry->register(new \Platform\Seo\Tools\RegisterUrlTool());
            $registry->register(new \Platform\Seo\Tools\UpdateUrlTool());
            $registry->register(new \Platform\Seo\Tools\DeleteUrlTool());
            $registry->register(new \Platform\Seo\Tools\EnrichUrlTool());
            $registry->register(new \Platform\Seo\Tools\OnboardUrlTool());

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
            $registry->register(new \Platform\Seo\Tools\ClassifyIntentTool());

            // Cluster
            $registry->register(new \Platform\Seo\Tools\ListClustersTool());
            $registry->register(new \Platform\Seo\Tools\CreateClusterTool());
            $registry->register(new \Platform\Seo\Tools\DeleteClusterTool());
            $registry->register(new \Platform\Seo\Tools\AutoClusterTool());

            // Content-Briefs (Cluster → Arbeitsauftrag → Flynk-Loop)
            $registry->register(new \Platform\Seo\Tools\ListContentBriefsTool());
            $registry->register(new \Platform\Seo\Tools\CreateContentBriefTool());
            $registry->register(new \Platform\Seo\Tools\UpdateContentBriefTool());
            $registry->register(new \Platform\Seo\Tools\LinkContentBriefsTool());
            $registry->register(new \Platform\Seo\Tools\ReconcileContentBriefsTool());

            // Wirkungsräume (Steuer-Scopes)
            $registry->register(new \Platform\Seo\Tools\CreatePortfolioTool());
            $registry->register(new \Platform\Seo\Tools\PortfolioUrlsTool());
            $registry->register(new \Platform\Seo\Tools\ListPortfoliosTool());
            $registry->register(new \Platform\Seo\Tools\DeletePortfolioTool());

            // Signale
            $registry->register(new \Platform\Seo\Tools\ListSignalsTool());
            $registry->register(new \Platform\Seo\Tools\UpdateSignalTool());

            // Signal-Definitionen (DB-Objekte, docs/SIGNALS-CONCEPT.md)
            $registry->register(new \Platform\Seo\Tools\ListSignalDefinitionsTool());
            $registry->register(new \Platform\Seo\Tools\CreateSignalDefinitionTool());
            $registry->register(new \Platform\Seo\Tools\UpdateSignalDefinitionTool());
            $registry->register(new \Platform\Seo\Tools\DeleteSignalDefinitionTool());

            // Competitors
            $registry->register(new \Platform\Seo\Tools\FetchSerpCompetitorsTool());
            $registry->register(new \Platform\Seo\Tools\KeywordGapTool());

            // Wartung
            $registry->register(new \Platform\Seo\Tools\RepairRelationshipsTool());
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
