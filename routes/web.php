<?php

use App\Models\Question;
use App\Services\ImageGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Telegram\Bot\Laravel\Facades\Telegram;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test', function () {
    dd(app(ImageGenerationService::class)->generateImage([
        'amount' => 100000,
        'sender_num' => "0435",
        'sender' => "Bobojonov Shaxriyor",
        'receiver_num' => "1234",
        'receiver' => "Palonchiyev Pistonchi",
    ]));
});

Route::get('/add', function () {
    return view('admin.add');
});

Route::post('/add', function (Request $request) {
    $request->validate([
        'category' => 'required|string',
        'text' => 'required|string',
        'option_a' => 'required|string',
        'option_b' => 'required|string',
        'option_c' => 'required|string',
        'option_d' => 'required|string',
        'correct_answer' => 'required|in:a,b,c,d',
    ]);

    $question = new Question();
    $question->category = $request->category;
    $question->question = $request->text;
    $question->option_a = $request->option_a;
    $question->option_b = $request->option_b;
    $question->option_c = $request->option_c;
    $question->option_d = $request->option_d;
    $question->correct_option = $request->correct_answer;
    $question->save();

    return redirect('/add')->with('success', 'Savol muvaffaqiyatli qo‘shildi!');
});