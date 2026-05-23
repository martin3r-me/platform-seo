<?php

use Platform\Seo\Livewire\SeoProjectDashboard;
use Platform\Seo\Livewire\SeoUrlExplorer;
use Platform\Seo\Livewire\SeoUrlDetail;
use Platform\Seo\Livewire\SeoKeywordExplorer;
use Platform\Seo\Livewire\SeoRankingTracker;
use Platform\Seo\Livewire\SeoCompetitorAnalysis;
use Platform\Seo\Livewire\SeoCannibalization;
use Platform\Seo\Livewire\SeoSignalIndex;

Route::get('/', SeoProjectDashboard::class)->name('seo.dashboard');
Route::get('/urls', SeoUrlExplorer::class)->name('seo.urls');
Route::get('/urls/{seoUrl}', SeoUrlDetail::class)->name('seo.urls.show');
Route::get('/keywords', SeoKeywordExplorer::class)->name('seo.keywords');
Route::get('/rankings', SeoRankingTracker::class)->name('seo.rankings');
Route::get('/competitors', SeoCompetitorAnalysis::class)->name('seo.competitors');
Route::get('/cannibalization', SeoCannibalization::class)->name('seo.cannibalization');
Route::get('/signals', SeoSignalIndex::class)->name('seo.signals');
