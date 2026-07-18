<?php

use Platform\Seo\Livewire\SeoCannibalization;
use Platform\Seo\Livewire\SeoCompetitorAnalysis;
use Platform\Seo\Livewire\SeoKeywordExplorer;
use Platform\Seo\Livewire\SeoProjectDashboard;
use Platform\Seo\Livewire\SeoRankingTracker;
use Platform\Seo\Livewire\SeoRecommendations;
use Platform\Seo\Livewire\SeoSignalIndex;
use Platform\Seo\Livewire\SeoUrlDetail;
use Platform\Seo\Livewire\SeoUrlExplorer;
use Platform\Seo\Livewire\SeoUrlListDetail;
use Platform\Seo\Livewire\SeoUrlListManager;

// Top-Level
Route::get('/', SeoProjectDashboard::class)->name('seo.dashboard');
Route::get('/recommendations', SeoRecommendations::class)->name('seo.recommendations');
Route::get('/lists', SeoUrlListManager::class)->name('seo.lists');
Route::get('/urls', SeoUrlExplorer::class)->name('seo.urls');
Route::get('/urls/{seoUrl}', SeoUrlDetail::class)->name('seo.urls.show');

// Listen-Kontext
Route::get('/lists/{seoUrlList}', SeoUrlListDetail::class)->name('seo.lists.show');
Route::get('/lists/{seoUrlList}/competitors', SeoCompetitorAnalysis::class)->name('seo.lists.competitors');
Route::get('/lists/{seoUrlList}/cannibalization', SeoCannibalization::class)->name('seo.lists.cannibalization');
Route::get('/lists/{seoUrlList}/signals', SeoSignalIndex::class)->name('seo.lists.signals');

// URL-Kontext
Route::get('/urls/{seoUrl}/keywords', SeoKeywordExplorer::class)->name('seo.urls.keywords');
Route::get('/urls/{seoUrl}/rankings', SeoRankingTracker::class)->name('seo.urls.rankings');
