<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\WaRequest;
use App\Models\WhatsappAnswer;

class WhatsappController extends Controller
{
    private $token;
    private $phone_id;
    private $verify_token;

    public function __construct()
    {
        $this->token = env('WA_TOKEN');
        $this->phone_id = env('WA_PHONE_ID');
        $this->verify_token = env('WA_VERIFY_TOKEN', 'intercocina');
    }

    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $challenge = $request->query('hub_challenge');
        $verify_token = $request->query('hub_verify_token');
        if ($mode === 'subscribe' && $verify_token === $this->verify_token) {
            // \Log::alert($request->all());
            return response($challenge, 200);
        }

        return response('Invalid token', 403);
    }


        public function receive(Request $request)
        {
            \Log::info('Webhook Received:', $request->all());

            // Check message event
            $entry = $request->input('entry.0.changes.0.value.messages.0');
            if (!$entry) {
                return response('No message', 200);
            }

            $from    = $entry['from']; // customer number
            $message = $entry['text']['body'] ?? null;

            // Auto reply (example)
            $this->sendMessage($from, "Hello 👋 Thanks for contacting Intercocina!");

            return response('EVENT_RECEIVED', 200);
        }

    public function sendMessage($to, $text)
    {
        $url = "https://graph.facebook.com/v24.0/" . $this->phone_id . "/messages";

        $response = Http::withToken($this->token)->post($url, [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "text",
            "text" => ["body" => $text]
        ]);

        \Log::info("Message Response:", $response->json());
    }





    // MAIN WEBHOOK - ENTRY POINT
    public function webhook(Request $request)
    {
        // quick guard
        $entry = $request->input('entry.0.changes.0.value') ?? null;
        $msg = $entry['messages'][0] ?? null;

        if (!$msg) {
            return response('no messages', 200);
        }

        $from = $msg['from']; // phone like 15551234567
        $waRequest = WaRequest::firstOrCreate(
            ['phone' => $from],
            ['step' => 'start', 'answers' => []]
        );

        // 1) If user clicked a reply button
        if (!empty($msg['interactive'])) {
            $interactive = $msg['interactive'];
            // Button reply
            if (!empty($interactive['button_reply'])) {
                $buttonId = $interactive['button_reply']['id'] ?? null;
                $buttonTitle = $interactive['button_reply']['title'] ?? null;
                return $this->handleButtonReply($waRequest, $buttonId, $buttonTitle);
            }

            // List reply (if you use lists)
            if (!empty($interactive['list_reply'])) {
                $listId = $interactive['list_reply']['id'] ?? null;
                $listTitle = $interactive['list_reply']['title'] ?? null;
                return $this->handleListReply($waRequest, $listId, $listTitle);
            }
        }

        // 2) Otherwise, it's a text message
        $text = $msg['text']['body'] ?? '';

        return $this->handleTextReply($waRequest, $text);
    }

    // HANDLE BUTTON REPLIES
    private function handleButtonReply(WaRequest $req, $buttonId, $buttonTitle)
    {
        // Example buttonId values: "devis", "produits", "support"
        switch ($buttonId) {
            case 'devis':
                $req->step = 'type_placard';
                $req->save();

                // send a second level with quick reply buttons (dressing, battant, coulissant)
                return $this->sendButtons(
                    $req->phone,
                    "Quel type de placard voulez-vous ?",
                    [
                        ['id' => 'type_dressing', 'title' => 'Dressing'],
                        ['id' => 'type_battant', 'title' => 'Battant'],
                        ['id' => 'type_coulissant', 'title' => 'Coulissant'],
                    ]
                );

            case 'produits':
                $req->step = 'browsing_products';
                $req->save();
                return $this->sendText($req->phone, "Voici nos produits: Dressing, Placard, Bibliothèque.\nTapez *menu* pour revenir.");
                
            case 'support':
                $req->step = 'support';
                $req->save();
                return $this->sendText($req->phone, "Support client 🧑‍💼\nMerci d'envoyer votre message, notre équipe va répondre.");
        }

        return $this->sendText($req->phone, "Option inconnue. Tapez *menu* pour revenir.");
    }

    // HANDLE LIST REPLIES (if you use lists)
    private function handleListReply(WaRequest $req, $listId, $listTitle)
    {
        // Implement similarly to button replies depending on your list IDs
        $req->step = 'list_selected';
        $answers = $req->answers ?? [];
        $answers['last_list'] = ['id' => $listId, 'title' => $listTitle];
        $req->answers = $answers;
        $req->save();

        return $this->sendText($req->phone, "Merci, vous avez choisi: {$listTitle}.");
    }

    // HANDLE TEXT MESSAGES (and step logic)
    private function handleTextReply(WaRequest $req, $text)
    {
        $phone = $req->phone;
        $step = $req->step;

        // universal menu command
        if (strtolower(trim($text)) === 'menu') {
            $req->step = 'main_menu';
            $req->save();
            return $this->sendMainMenuButtons($phone);
        }

        switch ($step) {
            case 'start':
            case 'main_menu':
                // if user types text instead of clicking button, accept 1/2/3
                $normalized = trim($text);
                if ($normalized === '1' || strtolower($normalized) === 'devis') {
                    $req->step = 'type_placard';
                    $req->save();
                    return $this->sendButtons(
                        $phone,
                        "Quel type de placard voulez-vous ?",
                        [
                            ['id' => 'type_dressing', 'title' => 'Dressing'],
                            ['id' => 'type_battant', 'title' => 'Battant'],
                            ['id' => 'type_coulissant', 'title' => 'Coulissant'],
                        ]
                    );
                }

                if ($normalized === '2' || strtolower($normalized) === 'produits') {
                    $req->step = 'browsing_products';
                    $req->save();
                    return $this->sendText($phone, "Voici nos produits: Dressing, Placard, Bibliothèque.\nTapez *menu* pour revenir.");
                }

                if ($normalized === '3' || strtolower($normalized) === 'support') {
                    $req->step = 'support';
                    $req->save();
                    return $this->sendText($phone, "Support client 🧑‍💼\nEnvoyez votre message.");
                }

                // default: send main menu
                return $this->sendMainMenuButtons($phone);

            case 'type_placard':
                // user typed dimensions or text; save as answer and proceed
                $answers = $req->answers ?? [];
                $answers['type_placard'] = $text;
                $req->answers = $answers;
                $req->step = 'ask_dimensions';
                $req->save();

                return $this->sendText($phone, "Reçu ✅\nMerci. Maintenant, envoyez les dimensions (Largeur x Hauteur en cm), ex: 200x250");

            case 'ask_dimensions':
                // save dimensions and finish
                $answers = $req->answers ?? [];
                $answers['dimensions'] = $text;
                $req->answers = $answers;
                $req->step = 'done';
                $req->save();

                // Optionally send summary back to user
                $summary = "✅ Demande reçue :\nType: " . ($answers['type_placard'] ?? '-') .
                           "\nDimensions: " . ($answers['dimensions'] ?? '-');

                $this->sendText($phone, $summary);
                return $this->sendText($phone, "Merci ! Votre demande de devis est enregistrée. Notre équipe vous contactera bientôt.");

            case 'support':
                // Save the support message and notify team (store text)
                $answers = $req->answers ?? [];
                $answers['support_message'] = ($answers['support_message'] ?? '') . "\n" . $text;
                $req->answers = $answers;
                $req->save();

                // You can also dispatch a job or send an email to your support team here.
                return $this->sendText($phone, "Merci, votre message de support a été reçu. Nous reviendrons vers vous rapidement.");

            default:
                return $this->sendMainMenuButtons($phone);
        }
    }

    // SEND MAIN MENU AS QUICK REPLY BUTTONS
    private function sendMainMenuButtons($to)
    {
        return $this->sendButtons(
            $to,
            "Bienvenue 👋\nChoisissez une option :",
            [
                ['id' => 'devis', 'title' => 'Demander un devis'],
                ['id' => 'produits', 'title' => 'Voir nos produits'],
                ['id' => 'support', 'title' => 'Support client'],
            ]
        );
    }

    // GENERIC SEND TEXT
    private function sendText($to, $text)
    {
        return Http::withToken($this->token)->post(
            "https://graph.facebook.com/v22.0/{$this->phone_id}/messages",
            [
                "messaging_product" => "whatsapp",
                "to" => $to,
                "type" => "text",
                "text" => ["body" => $text]
            ]
        );
    }

    // SEND BUTTONS (interactive type)
    private function sendButtons($to, $bodyText, array $buttons)
    {
        // $buttons is array of ['id'=>'xxx','title'=>'Title']
        $actionButtons = array_map(function ($b) {
            return [
                'type' => 'reply',
                'reply' => [
                    'id' => $b['id'],
                    'title' => $b['title']
                ]
            ];
        }, $buttons);

        $payload = [
            "messaging_product" => "whatsapp",
            "to" => $to,
            "type" => "interactive",
            "interactive" => [
                "type" => "button",
                "body" => ["text" => $bodyText],
                "action" => [
                    "buttons" => $actionButtons
                ]
            ]
        ];

        return Http::withToken($this->token)
            ->post("https://graph.facebook.com/v22.0/{$this->phone_id}/messages", $payload);
    }
}
