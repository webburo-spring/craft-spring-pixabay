<?php
//Action routes (actions/spring-pixabay/... and {cpTrigger}/actions/spring-pixabay/...), registered by Craft (HasRoutes)
//The URLs are the same as the Yii controller routes they replace

use Illuminate\Support\Facades\Route;
use webburospring\pixabay\controllers\PixabayController;

//Logged in
Route::middleware(['auth'])->group(function () {
	Route::get('pixabay/search', [PixabayController::class, 'search']);
	Route::get('pixabay/download', [PixabayController::class, 'download']);
	Route::get('pixabay/logo', fn () => response()->file(dirname(__DIR__) . '/src/assets/dist/pixabay.svg', ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=31536000'])); //Having the logo is required by Pixabay
});
