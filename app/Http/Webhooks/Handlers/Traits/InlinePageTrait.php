<?php

namespace App\Http\Webhooks\Handlers\Traits;

use App\Services\Template;
use App\Models\User as UserModel;

trait InlinePageTrait
{
    public function send_select_language(): int
    {
        return $this->sendMessage('select_language');
    }

    public function sendMessage(string $template, array $templateData = []): ?int
    {
        $template = $this->createTemplate($template, $templateData);

        if (!$template->keyboard == null) {
            $telegraph_response = $this->chat->message($template->template)
                ->keyboard($template->keyboard)
                ->send();
        } else {
            $telegraph_response = $this->chat->message($template->template)
                ->send();
        }

        return $telegraph_response->telegraphMessageId();
    }

    public function editMessage(int $message_id, string $template, array $template_data): void
    {
        $template = $this->createTemplate($template, $template_data);

        if (!$template->keyboard == null) {
            $this->chat->edit($message_id)
                ->message($template->template)
                ->keyboard($template->keyboard)
                ->send();
        } else {
            $this->chat->edit($message_id)
                ->message($template->template)
                ->send();
        }
    }

    private function createTemplate(string $template, array $templateData): Template
    {
        $user = UserModel::where('chat_id', $this->message->from()->id())->first();
        return new Template($template, $user->language_code, $templateData);
    }
}
