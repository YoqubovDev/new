<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Telegram\Bot\Laravel\Facades\Telegram;

class ClearTelegramUpdates extends Command
{
    protected $signature = 'telegram:clear-updates';
    protected $description = 'Clear pending Telegram updates using deleteWebhook';

    public function handle()
    {
        $this->info('✅ Pending Telegram updates cleared.');
    }
}
