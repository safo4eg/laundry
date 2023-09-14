<?php

namespace App\Services;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;


class Template
{
    private string $newTemplate;
    public mixed $keyboard;
    public string $template = '';

    public function __construct(string $template, string $lang = null, array $dataArray = [])
    {
        $lang ? $this->newTemplate = view("bot.{$lang}.{$template}", $dataArray)
            : $this->newTemplate = view("bot.{$template}", $dataArray);

        $this->setTemplate();
    }

    private function setTemplate(): void
    {
        if (preg_match('/<row>(.*?)<\/row>/s', $this->newTemplate, $rows) && isset($rows)) {
            $this->collectRows();
        } else {
            $this->keyboard = Keyboard::make()->buttons($this->collectButtons($this->newTemplate));
        }

        $regexp = '/<buttons(?:\s+type="(?<type>[^"]*)")?(?:\s+action="(?<action>[^"]*)")?(?:\s+url="(?<url>[^"]*)")?(?:\s+param="(?<param>[^"]*)")?(?:\s+webApp="(?<webApp>[^"]*)")?(?:\s+loginUrl="(?<loginUrl>[^"]*)")?(?:\s+width="([^"]*)")?>(?<text>.*?)<\/buttons>/';
        if (!preg_match($regexp, $this->newTemplate)){
            $this->keyboard = null;
            $this->template = $this->newTemplate;
        }
    }

    private function collectRows(): void
    {
        $regexp = '/<row>(?<buttons>[^"]*)<\/row>/s';
        while (preg_match($regexp, $this->newTemplate, $matches)) {
            $keyboard = Keyboard::make();
            $keyboard->row($this->collectButtons($matches['buttons']));
            $this->keyboard[] = $keyboard;
        }
    }

    private function collectButtons(string $template): array
    {
        $buttons = [];
        $regexp = '/<buttons(?:\s+type="(?<type>[^"]*)")?(?:\s+action="(?<action>[^"]*)")?(?:\s+url="(?<url>[^"]*)")?(?:\s+param="(?<param>[^"]*)")?(?:\s+webApp="(?<webApp>[^"]*)")?(?:\s+loginUrl="(?<loginUrl>[^"]*)")?(?:\s+width="([^"]*)")?>(?<text>.*?)<\/buttons>/';

        while (preg_match($regexp, $template, $matches)) {
            $type = $matches['type'];
            $text = $matches['text'];
            $button = Button::make($text);

            if ($type === 'action') {
                $action = $matches['action'];
                if (isset($matches['param'][1])) {
                    $param = explode(', ', $matches['param']);
                    $button->action($action)->param($param[0], $param[1]);
                } else {
                    $button->action($action);
                }
                $buttons[] = $button;
            }

            if ($type === 'url') {
                $url = $matches['url'];
                $button->url($url);
                $buttons[] = $button;
            }

            if ($type === 'webApp') {
                $webApp = $matches['webApp'];
                $button->webApp($webApp);
                $buttons[] = $button;
            }

            if ($type === 'loginUrl') {
                $loginUrl = $matches['loginUrl'];
                $button->loginUrl($loginUrl);
                $buttons[] = $button;
            }

            $template = preg_replace($regexp, '', $template, 1);
            $this->template = $template;
        }
        return $buttons;
    }
}
