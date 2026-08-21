<?php

use Platform\Seo\Livewire\SeoCannibalization;
use Platform\Seo\Livewire\SeoClusterDetail;
use Platform\Seo\Livewire\SeoClusters;
use Platform\Seo\Livewire\SeoCockpit;
use Platform\Seo\Livewire\SeoCompetitorAnalysis;
use Platform\Seo\Livewire\SeoCompetitors;
use Platform\Seo\Livewire\SeoContentBriefDetail;
use Platform\Seo\Livewire\SeoContentBriefs;
use Platform\Seo\Livewire\SeoContextWorkspace;
use Platform\Seo\Livewire\SeoKeywordExplorer;
use Platform\Seo\Livewire\SeoPerspective;
use Platform\Seo\Livewire\SeoProjectDashboard;
use Platform\Seo\Livewire\SeoRankingTracker;
use Platform\Seo\Livewire\SeoSignalDefinitions;
use Platform\Seo\Livewire\SeoSignalIndex;
use Platform\Seo\Livewire\SeoSignals;
use Platform\Seo\Livewire\SeoUrlDetail;
use Platform\Seo\Livewire\SeoUrlExplorer;
use Platform\Seo\Livewire\SeoUrlListDetail;
use Platform\Seo\Livewire\SeoUrlListManager;
use Platform\Seo\Livewire\SeoKosmos;
use Platform\Seo\Livewire\SeoPortfolioBasis;
use Platform\Seo\Livewire\SeoPortfolioDashboard;
use Platform\Seo\Livewire\SeoPortfolioDetail;
use Platform\Seo\Livewire\SeoPortfolioMeta;
use Platform\Seo\Livewire\SeoPortfolioInbox;
use Platform\Seo\Livewire\SeoPortfolioManager;

// Top-Level
Route::get('/', SeoCockpit::class)->name('seo.dashboard');
Route::get('/overview', SeoProjectDashboard::class)->name('seo.overview');
Route::get('/signals', SeoSignals::class)->name('seo.signals');
Route::get('/signals/definitions', SeoSignalDefinitions::class)->name('seo.signals.definitions');
Route::get('/clusters', SeoClusters::class)->name('seo.clusters');
Route::get('/clusters/{cluster}', SeoClusterDetail::class)->name('seo.clusters.show');
Route::get('/briefs', SeoContentBriefs::class)->name('seo.briefs');
Route::get('/briefs/{brief}', SeoContentBriefDetail::class)->name('seo.briefs.show');
Route::get('/context/{entity}', SeoContextWorkspace::class)->name('seo.context');
Route::get('/perspective/{entity}/kunden', SeoPerspective::class)->name('seo.perspective.customers');
Route::get('/perspective/{entity}/rel/{relation}', SeoPerspective::class)->name('seo.perspective.relation');
Route::get('/perspective/{entity}', SeoPerspective::class)->name('seo.perspective');
Route::get('/quelle/{module}', SeoPerspective::class)->name('seo.perspective.source');
Route::get('/eingang', SeoPerspective::class)->name('seo.perspective.unassigned');
Route::get('/lists', SeoUrlListManager::class)->name('seo.lists');
Route::get('/urls', SeoUrlExplorer::class)->name('seo.urls');
Route::get('/competitors', SeoCompetitors::class)->name('seo.competitors');
Route::get('/urls/{seoUrl}', SeoUrlDetail::class)->name('seo.urls.show');

// Listen-Kontext
Route::get('/lists/{seoUrlList}', SeoUrlListDetail::class)->name('seo.lists.show');
Route::get('/lists/{seoUrlList}/competitors', SeoCompetitorAnalysis::class)->name('seo.lists.competitors');
Route::get('/lists/{seoUrlList}/cannibalization', SeoCannibalization::class)->name('seo.lists.cannibalization');
Route::get('/lists/{seoUrlList}/signals', SeoSignalIndex::class)->name('seo.lists.signals');

// Wirkungsräume (Steuer-Scopes)
Route::get('/portfolios', SeoPortfolioManager::class)->name('seo.portfolios');
// Hauptseite = Dashboard (eigene Komponente, herausgelöst).
Route::get('/portfolios/{seoPortfolio}', SeoPortfolioDashboard::class)->name('seo.portfolios.show');
Route::get('/portfolios/{seoPortfolio}/inbox', SeoPortfolioInbox::class)->name('seo.portfolios.inbox');
Route::get('/portfolios/{seoPortfolio}/kosmos', SeoKosmos::class)->name('seo.portfolios.kosmos');
// Herausgelöste Stationen als eigene Komponenten/Routen (Stufe-2-Entflechtung) —
// gehen VOR dem {station}-Catch-all der Gott-Komponente.
Route::get('/portfolios/{seoPortfolio}/dashboard', SeoPortfolioDashboard::class)->name('seo.portfolios.dashboard');
Route::get('/portfolios/{seoPortfolio}/basis', SeoPortfolioBasis::class)->name('seo.portfolios.basis');
Route::get('/portfolios/{seoPortfolio}/meta', SeoPortfolioMeta::class)->name('seo.portfolios.meta');
// Übrige Stationen laufen (noch) über die Gott-Komponente; sie liest {station}
// in mount(). Englische Keys (= $view/PHASES), constrained.
Route::get('/portfolios/{seoPortfolio}/{station}', SeoPortfolioDetail::class)
    ->whereIn('station', ['measure', 'organize', 'distribute', 'act', 'impact', 'entities', 'keywords', 'clusters', 'competitors'])
    ->name('seo.portfolios.station');

// URL-Kontext
Route::get('/urls/{seoUrl}/keywords', SeoKeywordExplorer::class)->name('seo.urls.keywords');
Route::get('/urls/{seoUrl}/rankings', SeoRankingTracker::class)->name('seo.urls.rankings');
