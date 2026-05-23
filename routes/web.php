<?php

use Platform\Seo\Livewire\SeoProjectIndex;
use Platform\Seo\Livewire\SeoProjectDashboard;
use Platform\Seo\Livewire\SeoUrlExplorer;
use Platform\Seo\Livewire\SeoUrlDetail;
use Platform\Seo\Livewire\SeoKeywordExplorer;
use Platform\Seo\Livewire\SeoRankingTracker;
use Platform\Seo\Livewire\SeoCompetitorAnalysis;
use Platform\Seo\Livewire\SeoCannibalization;
use Platform\Seo\Livewire\SeoSignalIndex;

Route::get('/', SeoProjectIndex::class)->name('seo.projects.index');
Route::get('/projects/{seoProject}', SeoProjectDashboard::class)->name('seo.projects.show');
Route::get('/projects/{seoProject}/urls', SeoUrlExplorer::class)->name('seo.projects.urls');
Route::get('/projects/{seoProject}/urls/{seoUrl}', SeoUrlDetail::class)->name('seo.projects.urls.show');
Route::get('/projects/{seoProject}/keywords', SeoKeywordExplorer::class)->name('seo.projects.keywords');
Route::get('/projects/{seoProject}/rankings', SeoRankingTracker::class)->name('seo.projects.rankings');
Route::get('/projects/{seoProject}/competitors', SeoCompetitorAnalysis::class)->name('seo.projects.competitors');
Route::get('/projects/{seoProject}/cannibalization', SeoCannibalization::class)->name('seo.projects.cannibalization');
Route::get('/projects/{seoProject}/signals', SeoSignalIndex::class)->name('seo.projects.signals');
