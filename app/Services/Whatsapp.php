<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class Whatsapp
{
    private int|string $id;
    private string $token;

    public function __construct()
    {
        $this->id = config('services.whatsapp.id');
        $this->token = config('services.whatsapp.token');
        return $this;
    }

    public function check_account(int $phone_number): bool
    {
        $request_url = "https://api.green-api.com/waInstance{$this->id}/checkWhatsapp/{$this->token}";
        $response = Http::post($request_url, ['phoneNumber' => $phone_number]);
        return $response->collect()->get('existsWhatsapp'); // если такого нет вернёт null, можно сделать обработку
    }
}
