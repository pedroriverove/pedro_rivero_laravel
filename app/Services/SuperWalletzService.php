<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PaymentRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SuperWalletzService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('SUPERWALLETZ_BASE_URI', 'http://localhost:3003'),
            'timeout' => 10.0,
        ]);
    }

    /**
     * @return array{message: string, transaction_id: string}
     * @throws GuzzleException
     */
    public function processPayment(Transaction $transaction, $callbackUrl): array
    {
        $payload = [
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'callback_url' => $callbackUrl,
        ];

        PaymentRequest::create([
            'transaction_id' => $transaction->id,
            'type' => 'request',
            'payload' => json_encode($payload),
        ]);

        $response = $this->client->post('/pay', [
            'json' => $payload,
        ]);

        if ($response->getStatusCode() === 200) {
            $responseData = json_decode($response->getBody(), true);

            $transactionId = $responseData['transaction_id'] ?? null;

            if ($transactionId) {
                $transaction->transaction_id = $transactionId;
                $transaction->status = 'pending';
                $transaction->save();

                return [
                    'message' => 'Pago procesado con SuperWalletz',
                ];
            } else {
                Log::error('No se recibió un transaction_id de SuperWalletz.');
                throw new \Exception('No se recibió un transaction_id de SuperWalletz.');
            }

        } else {
            throw new \Exception('Error al procesar el pago con SuperWalletz.');
        }
    }

    /**
     * @param Transaction $transaction
     * @param $payload
     * @return void
     */
    public function handleWebhook(Transaction $transaction, $payload): void
    {
        $cacheKey = 'webhook_' . $transaction->id;

        if (Cache::has($cacheKey)) {
            Log::info('Webhook duplicado detectado y descartado para la transacción: ' . $transaction->id);
            return;
        }

        PaymentRequest::create([
            'transaction_id' => $transaction->id,
            'type' => 'webhook',
            'payload' => json_encode($payload),
        ]);

        if (isset($payload['transaction_id'])) {
            if (isset($payload['status']) && $payload['status'] === 'success') {
                $transaction->status = 'success';
                $transaction->transaction_id = $payload['transaction_id'];
            } else if (isset($payload['status']) && $payload['status'] === 'failed') {
                $transaction->status = 'failed';
                $transaction->transaction_id = $payload['transaction_id'];
            }

            $transaction->save();
        } else {
            Log::warning("Webhook recibido con transaction_id incorrecto. Esperado: {$transaction->transaction_id}, Recibido: {$payload['transaction_id']}");
        }

        Cache::put($cacheKey, true, 60);
    }
}
