<?php

namespace App\Services;

use DateTimeZone;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImageGenerationService
{
    private $months = [
        '01' => 'yanvar', '02' => 'fevral', '03' => 'mart', '04' => 'aprel',
        '05' => 'may', '06' => 'iyun', '07' => 'iyul', '08' => 'avgust',
        '09' => 'sentabr', '10' => 'oktabr', '11' => 'noyabr', '12' => 'dekabr'
    ];

    public function generateImage(array $data) {
        $data['amount'] = number_format($data['amount'], 0, '.', ' ') . " so‘m";

        $imagePath = storage_path('app/public/main.jpg');
        $image = imagecreatefromjpeg($imagePath);
        $width = imagesx($image);
        $height = imagesy($image);

        $white = imagecolorallocate($image, 255, 255, 255);
        $gray = imagecolorallocate($image, 200, 200, 200);

        $font = storage_path('app/public/fonts/SFPRODISPLAYMEDIUM.OTF');
        $boldFont = storage_path('app/public/fonts/SFPRODISPLAYBOLD.OTF');

        $pay_date = date('d') . '-' . $this->months[date('m')] . ', ' . date('Y') . ' ' . date('H:i');

        imagettftext($image, 18, 0, 30, 45, $white, $font, date('H:i'));

        $this->drawSmartText($image, $data['amount'], $boldFont, 38, $width - 100, 500, $white);
        $this->drawSmartText($image, $pay_date, $font, 14, $width - 60, 540, $gray);
        $this->drawTextWithMaxRightLimit($image, $data['sender_num'], $font, 12, 510, 850, $white);
        $this->drawTextWithMaxRightLimit($image, $data['receiver_num'], $font, 12, 510, 947, $white);
        $this->drawTextWithMaxRightLimit($image, $this->generateSimilarNumber(), $font, 12, 460, 1049, $white);
        $this->drawTextWithMaxRightLimit($image, $data['amount'], $font, 12, 460, 1095, $white);
        $this->drawTextWithMaxRightLimit($image, $data['amount'], $font, 12, 460, 1190, $white);

        $this->drawTextWithMaxRightLimit($image, strtoupper($data['sender']), $font, 12, 410, 900, $white);
        $this->drawTextWithMaxRightLimit($image, strtoupper($data['receiver']), $font, 12, 410, 1000, $white);

        $output = 'output_' . now()->format('Y-m-d_H-i-s') . '.jpg';
        $outputPath = storage_path('app/public/' . $output);
        imagejpeg($image, $outputPath);
        imagedestroy($image);

        return $outputPath;
    }

    private function generateSimilarNumber() {
        $prefix = rand(30, 70);
        $middle = rand(1000000, 9999999);
        $end = rand(10, 99);
        return $prefix . $middle . $end;
    }

    private function drawSmartText($image, $text, $fontPath, $initialFontSize, $maxWidth, $y, $color) {
        $fontSize = $initialFontSize;

        do {
            $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
            $textWidth = $bbox[2] - $bbox[0];
            if ($textWidth <= $maxWidth || $fontSize <= 10) {
                break;
            }
            $fontSize--;
        } while (true);

        $imageWidth = imagesx($image);
        $x = max(0, ($imageWidth - $textWidth) / 2);

        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
    }

    private function drawTextWithMaxRightLimit($image, $text, $fontPath, $fontSize, $x, $y, $color, $maxRightX = 550) {
        $bbox = imagettfbbox($fontSize, 0, $fontPath, $text);
        $textWidth = $bbox[2] - $bbox[0];

        $textRightEdge = $x + $textWidth;

        if ($textRightEdge > $maxRightX) {
            $shiftAmount = $textRightEdge - $maxRightX;
            $x -= $shiftAmount;
        }

        imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $text);
    }
}