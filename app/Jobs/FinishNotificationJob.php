<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Laravel\Facades\Telegram;

class FinishNotificationJob implements ShouldQueue
{
    use Queueable;

    public $chatId;
    public function __construct($chatId) {
        $this->chatId = $chatId;
    }

    public function handle() {
        $success = Cache::get('message_success', 0);
        $failed = Cache::get('message_failed', 0);

        Telegram::sendMessage([
            'chat_id' => $this->chatId,
            'text' => "✅ <b>Xabar yuborish tugallandi!\n\n<i>Yuborildi | Yuborilmadi\n{$success} | {$failed}</i></b>",
            'parse_mode' => 'html'
        ]);
    }
}
