<?php

declare(strict_types=1);

use Byte5\Doormat\Http\Controllers\NoticesController;
use Illuminate\Support\Facades\Route;

// Statamic prefixes an addon's action routes with /!/<addon slug>.
Route::get('notices', NoticesController::class)->name('doormat.notices');
