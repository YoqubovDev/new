<?php

namespace App\Http\Controllers;

use App\Jobs\FinishNotificationJob;
use App\Models\Question;
use App\Models\User;
use App\Models\UserAnswer;
use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Carbon;
use App\Jobs\SendTelegramMessageJob;
use App\Models\MandatorySubcription;
use Illuminate\Support\Facades\Storage;
use App\Services\ImageGenerationService;
use Illuminate\Support\Facades\Log;

class TelegramBotController extends Controller
{
    private $categories = ['sport' => "Sport ⚽️", 'geography' => "Geografiya 🗺", 'history' => "Tarix 🏹", 'chemistry' => "Kimyo 🌡", 'uzbekistan' => "O'zbekiston 🇺🇿"];
    private $offers_cost;
    private $proofChan;
    private $minimum_offers;
    private $correct_answer_amount;
    private $admins = [6252419804, 6837321890, /* 7576788021 */];
    private $max_answers = 5;


    private function getMandatorySubscriptions() {
        return Cache::remember('mandatory_channels', 300, function () {
            return MandatorySubcription::all();
        });
    }

    private function mandatory($userId): bool {
        $mandatory = $this->getMandatorySubscriptions();

        if ($mandatory->isEmpty()) {
            return true;
        }

        if (in_array($userId, $this->admins)) {
            return true;
        }

        $no_subs = 0;
        $buttons = [];
        $counter = 1;

        foreach ($mandatory as $row) {
            if ($row->type === 'link') {
                $buttons[][] = [
                    'text' => $counter . ' - kanal',
                    'url' => $row->link
                ];
                $counter++;
            } elseif ($row->type == 'public') {
                $chatMember = Telegram::getChatMember([
                    'chat_id' => $row->channelId,
                    'user_id' => $userId
                ]);
                $status = $chatMember['status'] ?? null;
                if (!in_array($status, ['member', 'administrator', 'creator'])) {
                    $buttons[][] = [
                        'text' => $counter . ' - kanal',
                        'url' => $row->link
                    ];
                    $no_subs++;
                    $counter++;
                }
            } elseif ($row->type == 'private') {
                $status = $this->checkRequestsJson($row->channelId, $userId);
                if (!$status) {
                    
                    
                    $buttons[][] = [
                        'text' => $counter . ' - kanal',
                        'url' => $row->link
                    ];
                    $no_subs++;
                    $counter++;
                }
            }
        }

        if ($no_subs > 0) {
            $buttons[][] = [
                'text' => '✅ Tekshirish',
                'callback_data' => 'check_subscription'
            ];

            Telegram::sendMessage([
                'chat_id' => $userId,
                'text' => "<b>❗ Iltimos, quyidagi kanallarga obuna bo‘ling:($no_subs)</b>",
                'parse_mode' => 'HTML',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);

            return false;
        }

        return true;
    }

    private function checkRequestsJson($channelID, $userID) {
        $path = "requests_{$channelID}.json";
        return Storage::exists($path) && in_array($userID, Storage::json($path));
    }

    private function addRequestJson($channelID, $userID) {
        $path = "requests_{$channelID}.json";
        $data = Storage::exists($path) ? Storage::json($path) : [];
        if (!in_array($userID, $data)) {
            $data[] = $userID;
            Storage::put($path, json_encode($data));
        }
    }


    public function handle($update) {
        $message = $update["message"] ?? [];
        $callback = $update["callback_query"] ?? [];
        $poll_answer = $update["poll_answer"] ?? [];
        $my_chat_member = $update["my_chat_member"] ?? [];
        $chat_join_request = $update["chat_join_request"] ?? [];

        if (Storage::missing('settings.json')) {
            $settings = [
                'offers_cost' => 1000,
                'proof_channel' => '@sadiyuz',
                'minimum_offers' => 15,
                'correct_answer_amount' => 10000
            ];
            Storage::put('settings.json', json_encode($settings));
        }

        if (Storage::exists('settings.json')) {
            $settings = Storage::json('settings.json');
            $this->offers_cost = $settings["offers_cost"];
            $this->proofChan = $settings["proof_channel"];
            $this->minimum_offers = $settings["minimum_offers"];
            $this->correct_answer_amount = $settings["correct_answer_amount"];
        }

        if ($message) {
            $chatId = $message["chat"]["id"] ?? '';
            $msgId = $message["message_id"] ?? '';
            $text = $message["text"] ?? '';

            $step = Cache::get($chatId);
            $this->checkOffer($chatId);

            switch ($step) {
                case 'change_wallet':
                    $this->handleChangeWallet($chatId, $text);
                    break;

                case 'change_placeholder':
                    $this->handlePlaceholder($chatId, $text);
                    break;

                case 'send_message':
                    if (!empty($text) && in_array($text, ['📊 Statistika', '💬 Xabar yuborish', '📢 Kanallarni boshqarish','⚙️ Sozlamalar', '◀️ Orqaga'])) {
                        break;
                    }
                    $this->handleSendMessageToUsers($chatId, $msgId);
                    break;

                case 'channel_id':
                    $this->channel_id($chatId, $text, $message);
                    break;

                case 'channel_link':
                    $this->channel_link($chatId, $text);
                    break;
                    
                case 'change_settings':
                    $this->change_settings_($chatId, $text);
                    break;
            }

            if (!empty($text)):
            switch ($text) {
                case '/start':
                case '◀️ Orqaga':
                case '⛔ Bekor qilish':
                    Cache::forget($chatId);
                    $this->sendMainMenu($chatId);
                    break;
                case strpos($text, '/start ') === 0:
                    $refId = trim(str_ireplace('/start ', '', $text));
                    $this->hanldeOffers($chatId, $refId);
                    break;
                case '💡 Testlarni boshlash':
                    $this->sendCategoryMenu($chatId);
                    break;
                case '💰 Hisobim':
                    $this->sendUserStats($chatId);
                    break;
                case 'Bonus 🏵':
                    $this->sendBonusMenu($chatId);
                    break;
                case 'Do\'stlarni taklif qilish 🗣':
                    $this->sendOffersMenu($chatId);
                    break;
                case '/admin':
                    $this->sendAdminMenu($chatId);
                    break;
                case '📊 Statistika':
                    $this->sendStatictics($chatId);
                    break;
                case '📢 Kanallarni boshqarish':
                    $this->sendChannelManage($chatId);
                    break;
                case '💬 Xabar yuborish':
                    $this->sendMessageMenu($chatId);
                    break;
                case '⚙️ Sozlamalar':
                    $this->sendSettingsMenu($chatId);
                    break;
            }
            endif;
        }

        if ($callback) {
            $answerId = $callback["id"] ?? '';
            $call_data = $callback["data"] ?? '';
            $callbackMessage = $callback["message"] ?? [];
            $chatId = $callbackMessage["chat"]["id"] ?? '';
            $msgId = $callbackMessage["message_id"] ?? '';

            $this->checkOffer($chatId);

            switch ($call_data) {
                case 'check_subscription':
                    Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);
                    $this->sendMainMenu($chatId);
                    break;

                case 'withdrawal':
                    $this->sendWithdrawalButton($chatId, $msgId, $answerId);
                    break;

                case strpos($call_data, 'withdrawal_confirm=') === 0:
                    $userId = trim(str_ireplace('withdrawal_confirm=', '', $call_data));
                    $this->handleWithdrawalConfirm($chatId, $msgId, $userId);
                    break;

                case 'change_wallet':
                    $this->sendChangeWallet($chatId, $msgId, $answerId);
                    break;
                
                case 'confirm_wallet':
                    $this->handleConfirmWallet($chatId, $msgId, $answerId);
                    break;

                case 'edit_wallet':
                    $this->handleEditWallet($chatId, $msgId);
                    break;

                case strpos($call_data, 'category:') === 0:
                    $this->handleQuiz($call_data, $chatId, $msgId, $answerId);
                    break;
                case 'channel_add':
                    $this->hanldeAddChannel($chatId, $msgId, $answerId);
                    break;
                case strpos($call_data, 'handleChannel=') === 0:
                    $this->handleAddChannelCallab($chatId, $msgId, $call_data);
                    break;
                case 'channel_list':
                    $this->channel_list($chatId, $msgId, $answerId);
                    break;
                case 'channel_delete':
                    $this->channel_delete($chatId, $msgId, $answerId);
                    break;
                case strpos($call_data, 'channel_del') === 0:
                    $deleteId = trim(str_ireplace('channel_del', '', $call_data));
                    $this->channel_del($chatId, $msgId, $answerId, $deleteId);
                    break;

                case strpos($call_data, 'change_settings_') === 0:
                    $type = trim(str_ireplace('change_settings_', '', $call_data));
                    $this->change_settings($chatId, $msgId, $type);
                    break;
            }
        }

        if ($poll_answer) {
            $pollId = $poll_answer["poll_id"];
            $userId = $poll_answer["user"]["id"];
            $selectedOption = $poll_answer["option_ids"][0];

            $poll_id = Cache::get("current_poll_id_{$userId}");
            $poll_correct = Cache::get("current_poll_correct_{$userId}");
            $category = Cache::get("current_category_{$userId}");
            $questionId = Cache::get("current_question_{$userId}");

            if ($pollId == $poll_id) {
                $isCorrect = false;

                if ($selectedOption == $poll_correct) {
                    $isCorrect = true;

                    $user = User::firstOrCreate(['telegram_id' => $userId]);
                    $user->balance = $user->balance + $this->correct_answer_amount;
                    $user->save();

                    Telegram::sendMessage([
                        'chat_id' => $userId,
                        'text' => "<b>✅ To'g'ri javob berdingiz!</b>\n\n💰 Hisobingizda {$user->balance} so‘m mavjud!",
                        'parse_mode' => "html"
                    ]);
                }

               try {
                    UserAnswer::create([
                        'user_id' => $userId,
                        'category' => $category,
                        'question_id' => $questionId,
                        'is_correct' => $isCorrect,
                    ]);
                } catch (\Exception $e) {
                    Telegram::sendMessage([
                        'chat_id' => $userId,
                        'text' => "{$e->getMessage()}",
                        'parse_mode' => "html"
                    ]);
                }

            }
        }

        if ($my_chat_member) {
            $userId = $my_chat_member["from"]["id"];
            $userStatus = $my_chat_member["new_chat_member"]["status"];

            if ($userStatus == "kicked") {
                $user = User::where('telegram_id', $userId)->first();
                $user->is_active = false;
                $user->save();
            }
        }

        if ($chat_join_request) {
            $channelID = $chat_join_request["chat"]["id"] ?? '';
            $userID = $chat_join_request["from"]["id"] ?? '';

            if (!$this->checkRequestsJson($channelID, $userID)) {
                $this->addRequestJson($channelID, $userID);
            }
        }

        return response('OK', 200);
    }

    private function checkOffer($userId) {
        try {
            $offerId = Cache::get("offer_id_{$userId}");

            if ($offerId && $this->mandatory($userId)) {
                $user = User::where('telegram_id', $offerId)->first();
                if ($user) {
                    $user->increment('ref_count');
                    $user->increment('balance', $this->offers_cost);
                        
                    Cache::forget("offer_id_{$userId}");
                    Telegram::sendMessage([
                        'chat_id' => $offerId,
                        'text' => "✅ <b>Do‘stingiz to‘liq ro‘yxatdan o‘tganligi uchun sizga +1 taklif yozildi.</b>",
                        'parse_mode' => 'HTML'
                    ]);
                }
            }
        } catch (\Exception $e) {
            Telegram::sendMessage([
                'chat_id' => $userId,
                'text' => "<b>{$e->getMessage()}</b>",
                'parse_mode' => 'HTML'
            ]);
        }
    }

    private function sendMainMenu($chatId) {
        if ($this->mandatory($chatId) == true) {
            $user = User::firstOrCreate(['telegram_id' => $chatId]);
            if (!$user->is_active) {
                $user->is_active = true;
                $user->save();
            }
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>Savollarga to'g'ri javoblar uchun pul to'laydigan bot 💸\n\nAsosiy menyu👇</b>",
                'parse_mode' => 'html',
                'reply_markup' => json_encode([
                    'resize_keyboard' => true,
                    'keyboard' => [
                        [['text' => "💡 Testlarni boshlash"]],
                        [['text' => "💰 Hisobim"], ['text' => "Bonus 🏵"]],
                        [['text' => "Do'stlarni taklif qilish 🗣"]],
                    ]
                ])
            ]);
        }
    }

    private function hanldeOffers($chatId, $refId) {
        if ($refId == $chatId) {
            $this->sendMainMenu($chatId);
            return;
        }

        if ($this->mandatory($chatId) == true) {
            if (User::where('telegram_id', $chatId)->doesntExist()) {
                if ($user = User::where('telegram_id', $refId)->first()) {
                    $user->ref_count = $user->ref_count + 1;
                    $user->save();
                }
                Telegram::sendMessage([
                    'chat_id' => $refId,
                    'text' => "✅ <b>Do‘stingiz to‘liq ro‘yxatdan o‘tganligi uchun sizga +1 taklif yozildi.</b>",
                    'parse_mode' => 'html'
                ]);
            }

            $this->sendMainMenu($chatId);
        } else {
            Cache::put("offer_id_{$chatId}", $refId);
            Telegram::sendMessage([
                'chat_id' => $refId,
                'text' => "<b>🔥 Sizning do‘stingiz bizni kanallarga obuna bo‘lsa sizga +1 taklif yoziladi!</b>",
                'parse_mode' => "html"
            ]);
        }
    }

    private function sendCategoryMenu($chatId) {
        if ($this->mandatory($chatId) == true) {
            $buttons = [];
            foreach ($this->categories as $index => $category) {
                $buttons[] = [['text' => $category, 'callback_data' => "category:{$index}"]];
            }
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>❗Testni faqat 1 marta o'tishingiz mumkin\n\nBoshlamoqchi bo'lgan testni tanlang👇</b>",
                'parse_mode' => 'html',
                'reply_markup' => json_encode([
                    'inline_keyboard' => $buttons
                ])
            ]);
        }
    }

    private function sendUserStats($chatId) {
        if ($this->mandatory($chatId) == true) {
            $user = User::firstOrCreate(['telegram_id' => $chatId]);
            if (is_null($user->wallet) || $user->wallet === '') {
                $user->wallet = "o‘rnatilmagan";
            } else {
                $chunks = str_split($user->wallet, 4);
                $user->wallet = implode(' ', $chunks);
            }

            $buttons[] = [['text' => "Pul yechish 🚀", 'callback_data' => "withdrawal"]];
            $buttons[] = [['text' => "Hamyoni yangilash 💳", 'callback_data' => "change_wallet"]];
            $keyboard = json_encode(['inline_keyboard' => $buttons]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>🛡️Hisobingiz🛡️\n\n💰 Hisobdagi mablag‘: {$user->balance} so'm\n👥 Takliflar soni: {$user->ref_count} nafar\n📢 To‘lovlar kanali: {$this->proofChan}\n💳 Hamyon raqami: {$user->wallet}\n\n<i>⚠️ Hamyoningiz to'g'ri kiritilganiga ishonchingiz komil bo'lgandagida pul yechish uchun so'rov yuboring. To'lovlar uchun 12 soatdan 24 soatgacha vaqt talab qilinadi.</i>\n\n🔽 Pul yechish uchun quyidagi tugmani bosing! 🔽</b>",
                'parse_mode' => 'html',
                'reply_markup' => $keyboard
            ]);
        }
    }

    private function sendBonusMenu($chatId) {
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>😊 Yangiliklarni kutib qoling, ushbu bo‘lim bo‘yicha!\n\n<i>🔥 Tez kunlarda tayyorlanadi va hammasi sizlar uchun.</i></b>",
            'parse_mode' => 'html'
        ]);
    }

    private function sendOffersMenu($chatId) {
        if ($this->mandatory($chatId) == true) {
            $botInfo = Telegram::getMe();
            $offer_link = "https://t.me/" . $botInfo->getUsername() . "?start=$chatId";
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>💵 100 ming soʻmlik savollarga javob topish uchun doʻstlarning yordami kerak.\n\n{$this->proofChan} - toʻlanayotgan yutuqlar isboti\n\n❓ Savollarni boshlash uchun:\n{$offer_link}</b>",
                'parse_mode' => 'html'
            ]);
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "💁‍♂ Nima uchun loyihaga doʻst taklif qilishim kerak?\n\n🎈 Chunki ularham savollarga javob topib, pul ishlashi kerak-da!%0A\n🎈 Siz ham haftalik taklifni top 10 ga kirsangiz pul yutuqlari topshiriladi.\n🎈 Har bir taklifingiz uchun {$this->offers_cost} so'm pul qo'shiladi.\n\n🖇 Quyidagi maktub yuborish tugmasini bosib, doʻstlaringizni taklif qiling.",
                'parse_mode' => 'html',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => "📤 Maktub yuborish", 'url' => "https://t.me/share/url?url={$offer_link}&text=%F0%9F%91%8B%20Salom.%20Savollarga%20javob%20topib%20pul%20ishlashni%20hohlaysizmi%3F%20100%20ming%20so%CA%BBmlik%20savollarga%20javob%20topish%20uchun%20yordamingiz%20kerak%2C%20quyidagi%20havoladan%20kirib%2C%20tezroq%20savollarni%20boshlang."]]
                    ]
                ])
            ]);
        }
    }

    private function sendWithdrawalButton($chatId, $msgId, $answerId) {
        $user = User::firstOrCreate(['telegram_id' => $chatId]);
        if ($user->ref_count >= $this->minimum_offers) {
            if (empty($user->wallet)) {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $answerId,
                    'text' => "⛔ Avval hamyoningizni kiritishingiz kerak, keyinchalik qayta urinib ko‘ring!",
                    'show_alert' => true
                ]);
                return;
            }

            if ($user->is_withdrawal) {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $answerId,
                    'text' => "⛔ Siz allaqachon pul yechmoqchib bo‘lgansiz!",
                    'show_alert' => true
                ]);
                return;
            }

            $user->is_withdrawal = 1;
            $user->save();

            Telegram::sendMessage([
                'chat_id' => $this->admins[0],
                'text' => "💰 <b>Foydalanuvchi pul yechmoqchi!</b>\n\n" . 
                "👥 <b>Ism sharifi:</b> {$user->name}\n" .
                "💳 <b>Hamyon raqami:</b> {$user->wallet}\n",
                'parse_mode' => 'html',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => "✅ Tasdiqlash", 'callback_data' => "withdrawal_confirm={$chatId}"]]
                    ]
                ])
            ]);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "✅ <b>Pullaringiz tez orada hisobingizda bo‘ladi!</b>\n\nDo‘stlaringizni ham taklif qiling, axir ular ham pul ishlashlari kerakku. 😊",
                'parse_mode' => 'html'
            ]);
            return;
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $answerId,
            'text' => "❗ Pullaringizni olish uchun kamida {$this->minimum_offers} nafar odamni taklif qilishingiz kerak.\n\nSiz {$user->ref_count} nafar odam taklif qilgansiz.",
            'show_alert' => true
        ]);
        return;
    }

    private function handleWithdrawalConfirm($chatId, $msgId, $userId) {
        $user = User::where('telegram_id', $userId)->first();
        $balance = $user->balance;
        
        if ($user) {
            $user->balance = 0;
            $user->save();
        }

        $wallet = substr($user->wallet, -4);
        $image = app(ImageGenerationService::class)->generateImage([
            'amount' => $balance,
            'sender_num' => "0435",
            'sender' => "Bobojonov Shaxriyor",
            'receiver_num' => $wallet,
            'receiver' => $user->name,
        ]);
        
        Telegram::sendPhoto([
            'chat_id' => $this->proofChan,
            'photo' => fopen($image, 'r'),
            'caption' => "<b>✅ TO'LOV CHEKI👆🏻</b>\n➖➖➖➖➖➖➖➖➖\n<b>👤 Foydalanuvchi:</b> {$user->name}\n<b>💰 To‘langan summa:</b> {$balance} so‘m\n<b>📅 To‘lov sanasi:</b> " . now()->format('d-m-Y H:i:s') . "\n➖➖➖➖➖➖➖➖➖\n<b>🔍 Tasdiqlandi: Pul muvaffaqiyatli yechildi va foydalanuvchiga yetkazildi.</b>",
            'parse_mode' => 'html'
        ]);

        if (file_exists($image)) {
            unlink($image);
        }

        Telegram::sendMessage([
            'chat_id' => $userId,
            'text' => "<b>✅ Pul yechish muvaffaqiyatli amalga oshirildi!</b>",
            'parse_mode' => 'html'
        ]);
        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $msgId,
            'text' => "<b>✅ Tasdiqlandi ($chatId)!</b>",
            'parse_mode' => 'html'
        ]);
        $this->sendMainMenu($userId);
    }

    private function sendChangeWallet($chatId, $msgId, $answerId) {
        Cache::put($chatId, 'change_wallet');
        Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);

        $buttons[] = [['text' => "⛔ Bekor qilish"]];
        $keyboard = json_encode(['resize_keyboard' => true, 'keyboard' => $buttons]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>💳 Hamyoningiz raqamini kiriting:</b>\n\nBekor qilish uchun /cancel ni yuboring.",
            'parse_mode' => 'html',
            'reply_markup' => $keyboard
        ]);
    }

    private function handleChangeWallet($chatId, $text) {
        if (strtolower($text) == "/cancel" || $text == "⛔ Bekor qilish") {
            Cache::forget($chatId);
            $this->sendMainMenu($chatId);
            return;
        }

        $text = str_replace(' ', '', $text);

        if (is_numeric($text) and strlen($text) == 16) {
            Cache::put("wallet_{$chatId}", $text);
            Cache::put($chatId, 'change_placeholder');
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>✅ Endilikda kartaning egasini ism familiyasini kiritishingiz kerak!</b>\n\n<i>Namuna: <b>Palonchiyev Pistonchi</b></i>",
                'parse_mode' => "html"
            ]);
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>⛔ Hamyon raqami 16 ta bo'lishi kerak!</b>",
                'parse_mode' => "html"
            ]);
        }
    }

    private function handlePlaceholder($chatId, $text) {
        if (strtolower($text) == "/cancel" || $text == "⛔ Bekor qilish") {
            Cache::forget($chatId);
            $this->sendMainMenu($chatId);
            return;
        }
        
        $explode = explode(' ', $text);
        if (count($explode) == 2) {
            Cache::put("placeholder_{$chatId}", $text);
            Cache::forget($chatId);

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>❓ Karta raqam egasini to'g'ri yozdingizmi?</b>\n\n<i>Kiritdingiz: <b>{$text}</b></i>\n\nEslatma, agar to‘g‘ri yozilmagan bo‘lsa, tahrirlang. Aks holda pul tushmasligi mumkin!",
                'parse_mode' => "html",
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [
                            ['text' => '✅ Tasdiqlash', 'callback_data' => 'confirm_wallet'],
                            ['text' => '✏️ Tahrirlash', 'callback_data' => 'edit_wallet']
                        ]
                    ]
                ])
            ]);
            return;
        }

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>⛔ Iltimos, ism sharifni to‘liq kiriting!</b>",
            'parse_mode' => "html"
        ]);
    }

    private function handleEditWallet($chatId, $msgId) {
        Cache::put($chatId, 'change_placeholder');

        $buttons[] = [['text' => "⛔ Bekor qilish"]];
        $keyboard = json_encode(['resize_keyboard' => true, 'keyboard' => $buttons]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>➡️ Kartani egasini kiritng faqat to‘g‘ri yozing:</b>\n\n<i>Namuna: <b>Palonchiyev Pistonchi</b></i>\n\nBekor qilish uchun /cancel ni yuboring.",
            'parse_mode' => 'html',
            'reply_markup' => $keyboard
        ]);

        Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);
    }

    private function handleConfirmWallet($chatId, $msgId, $answerId) {
        $name = Cache::get("placeholder_{$chatId}");
        $wallet = Cache::get("wallet_{$chatId}");
        $user = User::firstOrCreate(['telegram_id' => $chatId]);
        $user->name = $name;
        $user->wallet = $wallet;
        $user->save();

        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $msgId,
            'text' => "<b>✅ Hamyon raqamingiz almashtirildi!</b>",
            'parse_mode' => "html"
        ]);
    }

    private function handleQuiz($text, $chatId, $msgId, $answerId) {
        if (str_starts_with($text, "category:")) {
            $category = str_ireplace('category:', '', $text);
            $attempt = UserAnswer::where('user_id', $chatId)->where('category', $category)->count();

            if ($attempt >= $this->max_answers) {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $answerId,
                    'text' => "Siz ushbu kategoriyadagi barcha savollarga javob berib bo‘ldingiz!",
                    'show_alert' => true
                ]);
                return;
            }

            $currentQuestionNumber = Cache::get("quiz_{$chatId}_{$category}", 0);
            if ($currentQuestionNumber >= $this->max_answers) {
                Cache::forget("quiz_{$chatId}_{$category}");
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $answerId,
                    'text' => "🔥 Testni yakunladingiz!",
                    'show_alert' => true
                ]);
                Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);
                $this->sendMainMenu($chatId);
                return;
            }

            $answeredQuestions = UserAnswer::where('user_id', $chatId)->where('category', $category)->pluck('question_id')->toArray();
            $question = Question::where('category', $category)->whereNotIn('id', $answeredQuestions)->inRandomOrder()->first();
            if ($question) {
                Cache::put("quiz_{$chatId}_{$category}", $currentQuestionNumber + 1, now()->addHours(1));
                Cache::put("current_question_{$chatId}", $question->id, now()->addHours(1));
                Cache::put("current_category_{$chatId}", $category, now()->addMinutes(5));

                $options = [
                    'a' => $question->option_a,
                    'b' => $question->option_b,
                    'c' => $question->option_c,
                    'd' => $question->option_d,
                ];

                $shuffled = collect($options)->shuffle()->values();
                $shuffledOptions = $shuffled->all();
                $correctText = $options[$question->correct_option];
                $correctOptionId = array_search($correctText, $shuffledOptions);

                Telegram::deleteMessage([
                    'chat_id' => $chatId,
                    'message_id' => $msgId
                ]);

                $response = Telegram::sendPoll([
                    'chat_id' => $chatId,
                    'question' => $question->question,
                    'options' => json_encode($shuffledOptions),
                    'type' => 'quiz',
                    'correct_option_id' => $correctOptionId,
                    'is_anonymous' => false,
                    'open_period' => 30,
                    'reply_markup' => json_encode([
                        'inline_keyboard' => [
                            [['text' => "Keyingi ➡️", 'callback_data' => $text]]
                        ]
                    ])
                ]);
                $pollId = $response['poll']['id'];
                Cache::put("current_poll_id_{$chatId}", $pollId, now()->addHours(1));
                Cache::put("current_poll_correct_{$chatId}", $correctOptionId, now()->addHours(1));
            } else {
                Telegram::answerCallbackQuery([
                    'callback_query_id' => $answerId,
                    'text' => "Ushbu kategoriyada savollar mavjud emas!",
                    'show_alert' => true
                ]);
            }
        }
    }

    private function sendAdminMenu($chatId) {
        if (in_array($chatId, $this->admins)) {
            $buttons[] = [['text' => "✨ Savollar qo‘shish", 'web_app' => ['url' => "https://c519.coresuz.ru/add"]]];
            $buttons[] = [['text' => "📊 Statistika"], ['text' => "💬 Xabar yuborish"]];
            $buttons[] = [['text' => "📢 Kanallarni boshqarish"]];
            $buttons[] = [['text' => "⚙️ Sozlamalar"], ['text' => "◀️ Orqaga"]];
            $keyboard = json_encode(['resize_keyboard' => true, 'keyboard' => $buttons]);

            try {
                $currentDateTime = now();
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Boshqaruv paneliga xush kelibsiz!</b>\n\n<code>$currentDateTime</code>",
                    'parse_mode' => "html",
                    'reply_markup' => $keyboard
                ]);
            } catch (\Exception $e) {
                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Xabar: " . $e->getMessage() . "</b>",
                    'parse_mode' => "html"
                ]);
            }
        }
    }

    private function sendStatictics($chatId) {
        if (in_array($chatId, $this->admins)) {
            $bugun = User::whereDate('created_at', Carbon::today())->count();
            $buHafta = User::whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count();
            $buOy = User::whereMonth('created_at', now()->month)->count();
            $buYil = User::whereYear('created_at', now()->year)->count();
            $jami = User::count();
            $tarkEtgan = User::where('is_active', false)->count();
            $faol = User::where('is_active', true)->count();

            $text = "🎉📊 <b>Bot statistikasi:</b>\n\n"
                . "👥 <b>Foydalanuvchilar:</b>\n"
                . "  - 📅 <b>Bugun:</b> {$bugun} ta yangi foydalanuvchi qo'shildi!\n"
                . "  - 🗓 <b>Bu hafta:</b> {$buHafta} ta yangi foydalanuvchi qo'shildi!\n"
                . "  - 📆 <b>Bu oy:</b> {$buOy} ta yangi foydalanuvchi qo'shildi!\n"
                . "  - 🏆 <b>Bu yilda:</b> {$buYil} ta yangi foydalanuvchi qo'shildi!\n\n"
                . "📊 <b>Jami foydalanuvchilar soni:</b> {$jami} ta.\n"
                . "❌ <b>Tark etgan foydalanuvchilar soni:</b> {$tarkEtgan} ta.\n"
                . "✅ <b>Hozirda faol foydalanuvchilar soni:</b> {$faol} ta.";

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'html'
            ]);
        }
    }

    private function sendMessageMenu($chatId) {
        Cache::put($chatId, 'send_message');

        $buttons[] = [['text' => "◀️ Orqaga"]];
        $keyboard = json_encode(['resize_keyboard' => true, 'keyboard' => $buttons]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>💬 Foydalanuvchilarga yuboriladigan xabarni kiriting:</b>",
            'parse_mode' => 'html',
            'reply_markup' => $keyboard
        ]);
    }

    private function handleSendMessageToUsers($chatId, $msgId) {

        Cache::forget($chatId);
        $currentDateTime = now();
        $response = Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>✅ Xabar yuborish boshlandi!</b>\n\n<code>$currentDateTime</code>",
            'parse_mode' => "html"
        ]);
        $messageId = $response['message_id'];

        Telegram::pinChatMessage([
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);

        $users = User::pluck('telegram_id')->toArray();
        SendTelegramMessageJob::dispatch($users, $chatId, $msgId);
        FinishNotificationJob::dispatch($chatId);
    }

    private function sendChannelManage($chatId) {
        if (in_array($chatId, $this->admins)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>📢 Kanallarni boshqarish:</b>",
                'parse_mode' => 'html',
                'reply_markup' => json_encode([
                    'inline_keyboard' => [
                        [['text' => "➕ Qo‘shish", 'callback_data' => 'channel_add'], ['text' => "🗑️ O‘chirish", 'callback_data' => 'channel_delete']],
                        [['text' => "📃 Ro‘yxat", 'callback_data' => 'channel_list']]
                    ]
                ])
            ]);
        }
    }

    private function hanldeAddChannel($chatId, $msgId, $answerId) {
        Telegram::editMessageText([
            'chat_id' => $chatId,
            'message_id' => $msgId,
            'text' => "<b>Tanlang!</b>",
            'parse_mode' => "html",
            'reply_markup' => json_encode([
                'inline_keyboard' => [
                    [['text' => "Ommaviy", 'callback_data' => "handleChannel=public"]],
                    [['text' => "So‘rov", 'callback_data' => "handleChannel=private"]],
                    [['text' => "Havola", 'callback_data' => "handleChannel=link"]]
                ]
            ])
        ]);
    }

    private function handleAddChannelCallab($chatId, $msgId, $call_data) {
        try {
            $type = trim(str_ireplace('handleChannel=', '', $call_data));
            Cache::put("channl_type_{$chatId}", $type);

            Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);

            $buttons[] = [['text' => "◀️ Orqaga"]];
            $keyboard = json_encode(['resize_keyboard' => true, 'keyboard' => $buttons]);

            if ($type == 'link') {
                Cache::put($chatId, 'channel_link');

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Havolangizni yuboring:</b>",
                    'parse_mode' => 'html',
                    'reply_markup' => $keyboard
                ]);
                return;
            } else {
                Cache::put($chatId, 'channel_id');

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Kanalingizdan biror xabarni uzating!</b>",
                    'parse_mode' => 'html',
                    'reply_markup' => $keyboard
                ]);
            }
        } catch (\Telegram\Bot\Exceptions\TelegramBotNotFoundException $e) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>Xatolik:</b> " . $e->getMessage(),
                'parse_mode' => 'html',
                'reply_markup' => $keyboard
            ]);
        }
    }

    private function channel_id($chatId, $text, $message) {
        if ($text == "◀️ Orqaga") {
            Cache::forget($chatId);
            $this->sendAdminMenu($chatId);
            return;
        }

        $forward_from_chat = $message["forward_from_chat"] ?? [];
        $channelID = $forward_from_chat["id"] ?? '';

        if (empty($forward_from_chat)) {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>Kanalingizdan biror xabarni uzating!</b>",
                'parse_mode' => 'html'
            ]);
            return;
        }

        $channel_type = Cache::get("channl_type_{$chatId}");
        $params = [
            'chat_id' => $channelID,
            'creates_join_request' => $channel_type === 'private'
        ];

        $response = Telegram::createChatInviteLink($params);
        $link = $response['invite_link'];

        Cache::put("channl_id_{$chatId}", $channelID);
        $this->channel_link($chatId, $link);
        return;
    }

    private function channel_link($chatId, $text) {
        if ($text == "◀️ Orqaga") {
            Cache::forget($chatId);
            $this->sendAdminMenu($chatId);
            return;
        }

        if (strpos($text, 'http://') === 0 || strpos($text, 'https://') === 0) {
            $channelType = Cache::get("channl_type_{$chatId}");
            $channelId = Cache::get("channl_id_{$chatId}", null);
            
            Telegram::sendMessage([
                'chat_id' => $chatId, 
                'text' => $channelId . ' - ' . $text
            ]);
            
            MandatorySubcription::create([
                'type' => $channelType,
                'channelId' => (string) $channelId,
                'link' => $text
            ]);

            foreach ([$chatId, "channl_id_{$chatId}", "channl_type_{$chatId}", "channl_name_{$chatId}"] as $key) {
                Cache::forget($key);
            }


            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>✅ Kanal qo‘shildi!</b>",
                'parse_mode' => 'HTML',
            ]);
            $this->sendAdminMenu($chatId);
            Cache::forget('mandatory_channels');
            return;
        } else {
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>❌ Noto‘g‘ri havola formati!</b>\nNa‘muna: https://t.me/your_channel",
                'parse_mode' => 'html'
            ]);

            return;
        }
    }

    private function channel_list($chatId, $msgId, $answerId) {
        $mandatory = MandatorySubcription::all();
        if (count($mandatory) > 0) {
            $text = "<b>Kanallar ro‘yxati:</b>\n\n";
            $counter = 1;
            foreach ($mandatory as $row) {
                $type = $row['type'] == 'link' ? 'Havola' : ($row['type'] == 'public' ? 'Ommaviy' : 'So‘rov');
                $text .= $counter . ') ' . $row['link'] . ' (' . $type . ')' . PHP_EOL;
                $counter++;
            }
            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true
            ]);
            Telegram::answerCallbackQuery(['callback_query_id' => $answerId, 'text' => ""]);
            return true;
        }
        Telegram::answerCallbackQuery([
            'callback_query_id' => $answerId,
            'text' => "Kanallar mavjud emas!",
            'show_alert' => true
        ]);
        return false;
    }

    private function channel_delete($chatId, $msgId, $answerId) {
        $mandatory = MandatorySubcription::all();
        if (count($mandatory) > 0) {
            $text = "<b>O‘chirish uchun kerakli kanalni tanlang va shu zahotiyoq o‘chirib yuboriladi.</b>\n\n";
            $buttons = [];
            $counter = 1;
            foreach ($mandatory as $row) {
                $text .= "<b>{$counter}.</b> {$row["name"]} ({$row["link"]})\n";
                $buttons[] = ['text' => $counter, 'callback_data' => "channel_del" . $row['id']];
                $counter++;
            }
            $keyboard = array_chunk($buttons, 5);
            Telegram::editMessageText([
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'text' => $text,
                'parse_mode' => 'html',
                'disable_web_page_preview' => true,
                'reply_markup' => json_encode([
                    'inline_keyboard' => $keyboard
                ])
            ]);
            Telegram::answerCallbackQuery(['callback_query_id' => $answerId, 'text' => ""]);
            return;
        }
        Telegram::answerCallbackQuery([
            'callback_query_id' => $answerId,
            'text' => "Kanallar mavjud emas!",
            'show_alert' => true
        ]);
        return;
    }

    private function channel_del($chatId, $msgId, $answerId, $deleteId) {
        $channel = MandatorySubcription::find($deleteId);
        if ($channel) {
            $channel->delete();
            Telegram::answerCallbackQuery([
                'callback_query_id' => $answerId,
                'text' => "Kanal muvoffaqiyatli o‘chirildi!",
                'show_alert' => true
            ]);
            if ($this->channel_delete($chatId, $msgId, $answerId)) {
                Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);
            }
        }

        Telegram::answerCallbackQuery([
            'callback_query_id' => $answerId,
            'text' => "Ushbu kanal mavjud emas ehtimol oldinroq o‘chirilgan!",
            'show_alert' => true
        ]);
        return;
    }

    private function sendSettingsMenu($chatId) {
        $button[] = [['text' => "Taklif narxi: {$this->offers_cost} so‘m", 'callback_data' => "change_settings_offers_cost"]];
        $button[] = [['text' => "Isbot kanal: {$this->proofChan}", 'callback_data' => "change_settings_proof_channel"]];
        $button[] = [['text' => "Min taklif: {$this->minimum_offers}", 'callback_data' => "change_settings_minimum_offers"]];
        $button[] = [['text' => "To‘g‘ri javob: {$this->correct_answer_amount} so‘m", 'callback_data' => "change_settings_correct_answer_amount"]];
        $keyboard = json_encode(['inline_keyboard' => $button]);

        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "<b>⚙️ Sozlamalarni tahrirlash uchun ustiga bosing!</b>",
            'parse_mode' => 'html',
            'reply_markup' => $keyboard
        ]);
    }

    private function change_settings($chatId, $msgId, $type) {
        Cache::put($chatId, 'change_settings');
        Cache::put("set_type_{$chatId}", $type);

        Telegram::deleteMessage(['chat_id' => $chatId, 'message_id' => $msgId]);
        $buttons[] = [['text' => "◀️ Orqaga"]];
        $keyboard = json_encode(['resize_keyboard' => true, 'keyboard' => $buttons]);
        Telegram::sendMessage([
            'chat_id' => $chatId,
            'text' => "➡️ <b>Yangi qiymatini kiriting:</b>",
            'parse_mode' => 'html',
            'reply_markup' => $keyboard
        ]);
    }

    private function change_settings_($chatId, $text) {
        if ($text == "◀️ Orqaga") {
            Cache::forget("set_type_{$chatId}");
            Cache::forget($chatId);
            $this->sendAdminMenu($chatId);
            return;
        }

        $type = Cache::get("set_type_{$chatId}");
        $settings = Storage::json('settings.json');

        if ($type == 'offers_cost' || $type == 'correct_answer_amount' || $type == 'minimum_offers') {
            if (is_numeric($text) == true) {
                $settings[ $type ] = (int) $text;
                Storage::put('settings.json', json_encode($settings));

                Cache::forget("set_type_{$chatId}");
                Cache::forget($chatId);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Muvoffaqiyatli saqlandi!</b>",
                    'parse_mode' => 'html'
                ]);
                $this->sendAdminMenu($chatId);
                return;
            }

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>Faqat raqamlar qatnashgan bo‘lishi lozim!</b>",
                'parse_mode' => 'html'
            ]);
            return;
        }

        if ($type == 'proof_channel') {
            if (isset($text) && strpos($text, "@") === 0) {
                $settings[ $type ] = $text;
                Storage::put('settings.json', json_encode($settings));

                Cache::forget("set_type_{$chatId}");
                Cache::forget($chatId);

                Telegram::sendMessage([
                    'chat_id' => $chatId,
                    'text' => "<b>Muvoffaqiyatli saqlandi!</b>",
                    'parse_mode' => 'html'
                ]);
                $this->sendAdminMenu($chatId);
                return;
            }

            Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => "<b>Noto‘g‘ri!</b> Qayta urining.",
                'parse_mode' => 'html'
            ]);
            return;
        }
    }
}
