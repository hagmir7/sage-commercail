<?php

namespace App\Models;
use App\Services\WhatsAppService;

use Illuminate\Database\Eloquent\Model;

class WhatsappAnswer extends Model
{


public function receive(Request $request)
{
    $entry = $request->input('entry')[0]['changes'][0]['value'];

    if (!isset($entry['messages'])) return;

    $msg = $entry['messages'][0];
    $from = $msg['from'];
    $type = $msg['type'];

    if ($type === 'interactive') {

        $answer = $msg['interactive']['button_reply']['title'];
        $answerId = $msg['interactive']['button_reply']['id'];

        WhatsappAnswer::create([
            'phone' => $from,
            'question' => 'Do you want a free quote?',
            'answer' => $answer,
        ]);

        // Send next question or thank you message
        (new WhatsAppService())->sendText($from, "Thanks! You answered: ".$answer);
    }
}

}
