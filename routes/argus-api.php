<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\AlertRules\CreateAlertRuleController;
use ArgusApi\Http\Controllers\AlertRules\DeleteAlertRuleController;
use ArgusApi\Http\Controllers\AlertRules\ListAlertRulesController;
use ArgusApi\Http\Controllers\AlertRules\ListSavedSearchAlertRulesController;
use ArgusApi\Http\Controllers\AlertRules\ShowAlertRuleController;
use ArgusApi\Http\Controllers\AlertRules\UpdateAlertRuleController;
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

Route::get('saved-searches/{savedSearchId}/alert-rules', ListSavedSearchAlertRulesController::class)->name('argus-api.saved-searches.alert-rules.index');
Route::post('saved-searches/{savedSearchId}/alert-rules', CreateAlertRuleController::class)->name('argus-api.saved-searches.alert-rules.store');

Route::get('alert-rules', ListAlertRulesController::class)->name('argus-api.alert-rules.index');
Route::get('alert-rules/{id}', ShowAlertRuleController::class)->name('argus-api.alert-rules.show');
Route::put('alert-rules/{id}', UpdateAlertRuleController::class)->name('argus-api.alert-rules.update');
Route::delete('alert-rules/{id}', DeleteAlertRuleController::class)->name('argus-api.alert-rules.destroy');
