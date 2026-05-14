<?php

use Platform\Seo\Livewire\SeoProjectIndex;
use Platform\Seo\Livewire\SeoProjectDetail;
use Platform\Seo\Livewire\SeoKeywordExplorer;
use Platform\Seo\Livewire\SeoRankingTracker;
use Platform\Seo\Livewire\SeoCompetitorAnalysis;
use Platform\Seo\Livewire\SeoSignalIndex;

Route::get('/', SeoProjectIndex::class)->name('seo.projects.index');
Route::get('/projects/{seoProject}', SeoProjectDetail::class)->name('seo.projects.show');
Route::get('/projects/{seoProject}/keywords', SeoKeywordExplorer::class)->name('seo.projects.keywords');
Route::get('/projects/{seoProject}/rankings', SeoRankingTracker::class)->name('seo.projects.rankings');
Route::get('/projects/{seoProject}/competitors', SeoCompetitorAnalysis::class)->name('seo.projects.competitors');
Route::get('/projects/{seoProject}/signals', SeoSignalIndex::class)->name('seo.projects.signals');
