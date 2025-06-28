<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;

class SendTelegramMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $chatIds;
    public $fromChatId;
    public $messageId;

    public function __construct(array $chatIds, $fromChatId, $messageId)
    {
        $this->chatIds = $chatIds;
        $this->fromChatId = $fromChatId;
        $this->messageId = $messageId;
    }

    public function handle()
    {        
        $success = 0;
        $failed = 0;


        foreach ($this->chatIds as $userId) {
            try {
                Telegram::copyMessage([
                    'chat_id' => $userId,
                    'from_chat_id' => $this->fromChatId,
                    'message_id' => $this->messageId,
                ]);
                $success++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        Cache::put('message_success', $success, now()->addMinute());
        Cache::put('message_failed', $failed, now()->addMinute());
        return;
    }
}

