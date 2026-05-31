<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\FailureGroupController;
use ArgusApi\Http\Controllers\JobHistoryController;
use ArgusApi\Http\Controllers\SavedSearches\CreateSavedSearchController;
use ArgusApi\Http\Controllers\SavedSearches\DeleteSavedSearchController;
use ArgusApi\Http\Controllers\SavedSearches\ListSavedSearchesController;
use ArgusApi\Http\Controllers\SavedSearches\SavedSearchResultsController;
use ArgusApi\Http\Controllers\SavedSearches\ShowSavedSearchController;
use ArgusApi\Http\Controllers\SavedSearches\UpdateSavedSearchController;
use ArgusApi\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('search', SearchController::class)->name('argus-api.search');
Route::get('jobs/{jobUuid}/history', JobHistoryController::class)->name('argus-api.jobs.history');
Route::post('failures', FailureGroupController::class)->name('argus-api.failures');

Route::get('saved-searches', ListSavedSearchesController::class)->name('argus-api.saved-searches.index');
Route::post('saved-searches', CreateSavedSearchController::class)->name('argus-api.saved-searches.store');
Route::get('saved-searches/{id}', ShowSavedSearchController::class)->name('argus-api.saved-searches.show');
Route::put('saved-searches/{id}', UpdateSavedSearchController::class)->name('argus-api.saved-searches.update');
Route::delete('saved-searches/{id}', DeleteSavedSearchController::class)->name('argus-api.saved-searches.destroy');
Route::get('saved-searches/{id}/results', SavedSearchResultsController::class)->name('argus-api.saved-searches.results');
