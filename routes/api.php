<?php

use App\Http\Controllers\TelegramBotController;
use App\Http\Requests\ImageRequest;
use App\Services\ImageGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('webhook', [TelegramBotController::class, 'handle']);

Route::get('/image', function (ImageGenerationService $service, ImageRequest $request) {
    $data = $request->validated();
    $imagePath = $service->generateImage($data);
        
    return response()->file($imagePath, [
        'Content-Type' => 'image/jpeg',
    ]);
});