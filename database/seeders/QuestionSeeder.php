<?php

namespace Database\Seeders;

use App\Models\Question;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            'sport' => [
                [
                    'question' => 'Futbol nechta futbolchi bilan o‘ynaladi?',
                    'option_a' => '9',
                    'option_b' => '11',
                    'option_c' => '10',
                    'option_d' => '12',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Olimpiya o‘yinlari necha yilda bir marta o‘tkaziladi?',
                    'option_a' => '2',
                    'option_b' => '3',
                    'option_c' => '4',
                    'option_d' => '5',
                    'correct_option' => 'c',
                ],
                [
                    'question' => 'Tennisda g‘alaba uchun nechta set kerak?',
                    'option_a' => '3',
                    'option_b' => '2',
                    'option_c' => '4',
                    'option_d' => '5',
                    'correct_option' => 'a',
                ],
                [
                    'question' => 'Basketbol maydonining uzunligi qancha metr?',
                    'option_a' => '25',
                    'option_b' => '30',
                    'option_c' => '28',
                    'option_d' => '32',
                    'correct_option' => 'c',
                ],
                [
                    'question' => 'Formul1da nechta poyga bor?',
                    'option_a' => '18',
                    'option_b' => '20',
                    'option_c' => '22',
                    'option_d' => '24',
                    'correct_option' => 'b',
                ],
            ],
            'geography' => [
                [
                    'question' => 'Yer qanchalik o‘ralgan?',
                    'option_a' => '40,075 km',
                    'option_b' => '42,000 km',
                    'option_c' => '39,500 km',
                    'option_d' => '38,600 km',
                    'correct_option' => 'a',
                ],
                [
                    'question' => 'Eng katta okean qaysi?',
                    'option_a' => 'Atlantika',
                    'option_b' => 'Hind',
                    'option_c' => 'Shimoliy Muz okeani',
                    'option_d' => 'Tinch',
                    'correct_option' => 'd',
                ],
                [
                    'question' => 'Qaysi davlat hududi eng katta?',
                    'option_a' => 'Kanada',
                    'option_b' => 'Rossiya',
                    'option_c' => 'Xitoy',
                    'option_d' => 'AQSh',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Qaysi qit’a eng kichigi?',
                    'option_a' => 'Antarktida',
                    'option_b' => 'Avstraliya',
                    'option_c' => 'Yevropa',
                    'option_d' => 'Janubiy Amerika',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Eng baland tog‘ qaysi?',
                    'option_a' => 'Everest',
                    'option_b' => 'K2',
                    'option_c' => 'Kangchenjunga',
                    'option_d' => 'Lhotse',
                    'correct_option' => 'a',
                ],
            ],
            'history' => [
                [
                    'question' => 'O‘zbekiston mustaqillikka qachon erishgan?',
                    'option_a' => '1990',
                    'option_b' => '1991',
                    'option_c' => '1992',
                    'option_d' => '1993',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Buyuk ipak yo‘li qachon paydo bo‘lgan?',
                    'option_a' => 'Miloddan avvalgi 1-asr',
                    'option_b' => 'Miloddan avvalgi 3-asr',
                    'option_c' => 'Miloddan avvalgi 2-asr',
                    'option_d' => 'Miloddan avvalgi 4-asr',
                    'correct_option' => 'c',
                ],
                [
                    'question' => 'Amir Temur qaysi asrda yashagan?',
                    'option_a' => '13-asr',
                    'option_b' => '14-asr',
                    'option_c' => '15-asr',
                    'option_d' => '16-asr',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Ikkinchi jahon urushi qachon tugagan?',
                    'option_a' => '1944',
                    'option_b' => '1945',
                    'option_c' => '1946',
                    'option_d' => '1947',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Qaysi davlat dastlabki demokratik respublika?',
                    'option_a' => 'Fransiya',
                    'option_b' => 'Germaniya',
                    'option_c' => 'Italiya',
                    'option_d' => 'Buyuk Britaniya',
                    'correct_option' => 'a',
                ],
            ],
            'chemistry' => [
                [
                    'question' => 'Suvning kimyoviy formulasi nima?',
                    'option_a' => 'H2O',
                    'option_b' => 'CO2',
                    'option_c' => 'O2',
                    'option_d' => 'NaCl',
                    'correct_option' => 'a',
                ],
                [
                    'question' => 'Kimyoda atom nima?',
                    'option_a' => 'Eng kichik zarracha',
                    'option_b' => 'Moddaning eng kichik qismi',
                    'option_c' => 'Kimyoviy element',
                    'option_d' => 'Molekula',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Kimyoviy elementlar nechta asosiy guruhga bo‘linadi?',
                    'option_a' => '2',
                    'option_b' => '3',
                    'option_c' => '4',
                    'option_d' => '5',
                    'correct_option' => 'a',
                ],
                [
                    'question' => 'pH 7 nimani anglatadi?',
                    'option_a' => 'Kislota',
                    'option_b' => 'Asos',
                    'option_c' => 'Neytral muhit',
                    'option_d' => 'Gaz',
                    'correct_option' => 'c',
                ],
                [
                    'question' => 'Kimyoda eng engil gaz qaysi?',
                    'option_a' => 'Vodorod',
                    'option_b' => 'Azot',
                    'option_c' => 'Kislorod',
                    'option_d' => 'Heliy',
                    'correct_option' => 'a',
                ],
            ],
            'uzbekistan' => [
                [
                    'question' => 'O‘zbekiston poytaxti qaysi shahar?',
                    'option_a' => 'Samarqand',
                    'option_b' => 'Buxoro',
                    'option_c' => 'Toshkent',
                    'option_d' => 'Namangan',
                    'correct_option' => 'c',
                ],
                [
                    'question' => 'O‘zbekiston milliy bayrog‘ining rangi nechta?',
                    'option_a' => '2',
                    'option_b' => '3',
                    'option_c' => '4',
                    'option_d' => '5',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'Amir Temur qaysi asrda yashagan?',
                    'option_a' => '14-asr',
                    'option_b' => '15-asr',
                    'option_c' => '13-asr',
                    'option_d' => '12-asr',
                    'correct_option' => 'a',
                ],
                [
                    'question' => 'O‘zbekiston qaysi materikda joylashgan?',
                    'option_a' => 'Afrika',
                    'option_b' => 'Osiyo',
                    'option_c' => 'Yevropa',
                    'option_d' => 'Amerika',
                    'correct_option' => 'b',
                ],
                [
                    'question' => 'O‘zbekiston davlat tili qaysi?',
                    'option_a' => 'Rus tili',
                    'option_b' => 'Ingliz tili',
                    'option_c' => 'O‘zbek tili',
                    'option_d' => 'Qozoq tili',
                    'correct_option' => 'c',
                ],
            ],
        ];

        foreach ($questions as $category => $qList) {
            foreach ($qList as $q) {
                Question::create([
                    'category' => $category,
                    'question' => $q['question'],
                    'option_a' => $q['option_a'],
                    'option_b' => $q['option_b'],
                    'option_c' => $q['option_c'],
                    'option_d' => $q['option_d'],
                    'correct_option' => $q['correct_option'],
                ]);
            }
        }
    }
}
