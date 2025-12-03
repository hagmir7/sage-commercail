<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    private $baseUrl;
    private $token;

    public function __construct()
    {
        $phoneId = env('WA_PHONE_ID');
        $this->baseUrl = "https://graph.facebook.com/v22.0/" . env('WA_PHONE_ID') . "/messages";
        $this->token = env('WA_TOKEN');
    }

    public function sendText($to, $text)
    {
        return Http::withToken($this->token)->post($this->baseUrl, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => ["body" => $text]
        ]);
    }

    public function sendTemplate($to, $templateName, $params = [])
    {
        return Http::withToken($this->token)->post($this->baseUrl, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "template",
            "template" => [
                "name" => $templateName,
                "language" => ["code" => "en_US"],
                "components" => [
                    [
                        "type" => "body",
                        "parameters" => array_map(function ($p) {
                            return ["type" => "text", "text" => $p];
                        }, $params)
                    ]
                ]
            ]
        ]);
    }

    public function sendButtons($to, $body)
    {
        return Http::withToken($this->token)->post($this->baseUrl, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => ["text" => $body],
                "action" => [
                    "buttons" => [
                        ["type" => "reply", "reply" => ["id" => "answer_yes", "title" => "Yes"]],
                        ["type" => "reply", "reply" => ["id" => "answer_no", "title" => "No"]],
                        ["type" => "reply", "reply" => ["id" => "answer_nothing", "title" => "Nothing"]],
                    ]
                ]
            ]
        ]);
    }
}
