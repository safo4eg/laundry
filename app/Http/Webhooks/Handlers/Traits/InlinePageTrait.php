<?php

namespace App\Http\Webhooks\Handlers\Traits;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait InlinePageTrait
{
    public function send_select_language(): int
    {
        $telegraph_response = $this->chat->message(view('bot.select_language'))
            ->keyboard(Keyboard::make()->buttons([
                Button::make('English')->action('select_language')->param('choice', 'en'),
                Button::make('Русский')->action('select_language')->param('choice', 'ru')
            ]))->send();
        return $telegraph_response->telegraphMessageId();
    }
    public function send_inline_page(string $language_code, array $template_info, array $buttons_info): int
    {
        $buttons = $this->get_buttons($language_code, $buttons_info);
        $keyboard = $this->create_keyboard($buttons);
        $template_name = $template_info['template_name'];
        $template_vars = isset($template_info['vars'])? $template_info['vars']: [];
        $telegraph_response = $this->chat->message(view("bot.$language_code.$template_name", $template_vars))
            ->keyboard($keyboard)
            ->send();
        return $telegraph_response->telegraphMessageId();
    }

    public function next_inline_page(int $message_id, string $language_code, string $template_name, array $buttons_info):void
    {
        $buttons = $this->get_buttons($language_code, $buttons_info);
        $keyboard = $this->create_keyboard($buttons);
        $this->chat->edit($message_id)
            ->message(view("bot.$language_code.$template_name"))
            ->keyboard($keyboard)
            ->send();
    }

    private function create_keyboard(array $buttons): Keyboard
    {
        $keyboard = Keyboard::make();
        foreach ($buttons as $elem) {
            if($elem instanceof Button) {
                $keyboard->buttons($buttons);
                return $keyboard;
            }
            $keyboard->row($elem);
        }
        return $keyboard;
    }

    private function get_buttons(string $language_code, array $buttons_info): array
    {
        $buttons = [];

        if(array_key_exists('row', $buttons_info)) {
            $type = 'row';
            foreach ($buttons_info as $row_info) {
                $row = [];
                foreach ($row_info as $button_info) {
                    $button = $this->get_button($button_info, $language_code);
                    $row[] = $button;
                }
                $buttons[] = $row;
            }
        } else {
            foreach ($buttons_info as $button_info) {
                $button = $this->get_button($button_info, $language_code);
                $buttons[] = $button;
            }
        }

        return $buttons;
    }

    private function get_button(array $button_info, string $language_code): Button
    {
        $button = null;
        $button = Button::make($button_info['language'][$language_code]);
        switch ($button_info['type']) {
            case 'action':
                $button->action($button_info['method']);
                $button->param($button_info['param'][0], $button_info['param'][1]);
                break;
            case 'url':
                $button->url($button_info['link']);
        }
        return $button;
    }
}
