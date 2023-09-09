<?php

namespace App\Http\Webhooks\Handlers\Traits;

use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;

trait InlinePageTrait
{
    public function send_inline_page(string $language_code, string $template_name, array $buttons_info): void
    {
        $buttons = $this->get_buttons($language_code, $buttons_info);
        $keyboard = $this->create_keyboard($buttons);
        $this->chat->message(view("bot.$language_code.$template_name"))->keyboard($keyboard)->send();
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
