<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class Geo
{
    private string $token;
    private array $coords;
    private string $lang;
    private string $format;
    public string $address;

    public function __construct($y, $x)
    {
        $this->token = config('services.geo.token');
        $this->coords = [
            'x' => $x,
            'y' => $y
        ];
        $this->lang = config('services.geo.lang');
        $this->format = config('services.geo.format');
        $this->getGeo();
    }

    private function getGeo(): void
    {
        $baseUrl = "https://geocode-maps.yandex.ru/1.x";

        $response = Http::get($baseUrl, [
            "apikey" => $this->token,
            "geocode" => "{$this->coords['y']},{$this->coords['x']}",
            "lang" => $this->lang,
            "format" => $this->format
        ]);

        if ($response['response']["GeoObjectCollection"]["featureMember"] != []){
            $this->address = $response["response"]["GeoObjectCollection"]["featureMember"][0]["GeoObject"]["metaDataProperty"]["GeocoderMetaData"]["text"];
        } else {
            $this->address = "Address not available";
        }
    }
}
