<?php

declare(strict_types=1);

use ArgusApi\Http\Controllers\SearchController;
use Illuminate\Support\Facades\Route;

Route::post('search', SearchController::class)->name('argus-api.search');
