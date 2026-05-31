<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\FailureGroupController;
use ArgusApi\Http\Controllers\JobHistoryController;
use ArgusApi\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('search', SearchController::class)->name('argus-api.search');
Route::get('jobs/{jobUuid}/history', JobHistoryController::class)->name('argus-api.jobs.history');
Route::post('failures', FailureGroupController::class)->name('argus-api.failures');
