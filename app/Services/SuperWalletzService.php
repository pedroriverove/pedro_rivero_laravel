<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PaymentRequest;
use GuzzleHttp\Client;

class SuperWalletzService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('SUPERWALLETZ_BASE_URI'), // URL del servidor de SuperWalletz
            'timeout' => 5.0,
        ]);
    }

    public function processPayment(Transaction $transaction, $callbackUrl)
    {
        $payload = [
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'callback_url' => $callbackUrl,
        ];

        // Registrar la petición antes de enviarla
        PaymentRequest::create([
            'transaction_id' => $transaction->id,
            'type' => 'request',
            'payload' => json_encode($payload),
        ]);

        $response = $this->client->post('/pay', [
            'json' => $payload,
        ]);

        $responseData = json_decode($response->getBody(), true);

        // Actualizar la transacción con el ID de la plataforma de pago
        $transaction->transaction_id = $responseData['transaction_id']; // SuperWalletz nos da el transaction_id
        $transaction->save();

        return $responseData;
    }

    public function handleWebhook(Transaction $transaction, $payload)
    {
        // Registrar el webhook recibido
        PaymentRequest::create([
            'transaction_id' => $transaction->id,
            'type' => 'webhook',
            'payload' => json_encode($payload),
        ]);

        // Actualizar el estado de la transacción basado en la información del webhook
        // (En una implementación real, verificaríamos la firma del webhook, etc.)
        if (isset($payload['status']) && $payload['status'] === 'success') {
            $transaction->status = 'success';
            $transaction->save();
        } else if (isset($payload['status']) && $payload['status'] === 'failed') {
            $transaction->status = 'failed';
            $transaction->save();
        }
    }
}
