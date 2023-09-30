<?php

namespace App\Services;

use App\Models\User;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\Color\Color;
use Illuminate\Support\Facades\Storage;

class QR
{
    public static function generate_referrals_qr(User $user)
    {
        $link = "https://t.me/rastan_telegraph_bot?start=ref{$user->id}";
        $qr_code = (QrCode::create($link))
            ->setSize(300)
            ->setForegroundColor(new Color(0, 101, 213));

        $logo = Logo::create(Storage::path('service/laundry_logo.png'))
            ->setResizeToWidth(100);

        $writer = new PngWriter();

        $result = $writer->write($qr_code);
        $result->saveToFile(Storage::path("user/qr_code_{$user->id}.png"));
    }
}
