<?php

namespace App\Console\Commands;

use App\Http\Controllers\TelegramBotController;
use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class TelegramPolling extends Command
{
    protected $signature = 'telegram:run';
    protected $description = 'Telegram bot polling';
    protected $offset = 0;

    public function handle() {
        $telegram = new TelegramBotController();
        Telegram::deleteWebhook();
    
        $this->info("Telegram bot polling started!");
    
        while (true) {
            $updates = Telegram::getUpdates(['timeout' => 30, 'offset' => $this->offset + 1]);
            foreach ($updates as $update) {
                $this->offset = $update->update_id;
    
                try {
                    $telegram->handle($update);
                } catch (\Throwable $e) {
                    $this->error("Xatolik: " . $e->getMessage());
                    $this->error("Fayl: " . $e->getFile() . " qator: " . $e->getLine());
                }
            }
            usleep(500000);
        }
    }    
}
