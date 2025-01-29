<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PaymentRequest;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class EasyMoneyService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('EASYMONEY_BASE_URI', 'http://localhost:3000'),
            'timeout' => 5.0,
        ]);
    }

    /**
     * @return array{message: string, transaction_id: mixed|string}
     * @throws GuzzleException
     * @throws Exception
     */
    public function processPayment(Transaction $transaction): array
    {
        try {
            if (fmod($transaction->amount, 1) !== 0.00) {
                throw new Exception('EasyMoney no acepta montos con decimales.');
            }
            $payload = [
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ];

            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode($payload),
            ]);

            $response = $this->client->post('/process', [
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                Log::info('EasyMoney returned 200 OK. Generating random transaction ID.');

                $transaction->status = 'success';
                $transaction->transaction_id = 'EM-' . str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
                $transaction->save();

                return [
                    'message' => 'Pago procesado con EasyMoney',
                    'transaction_id' => $transaction->transaction_id
                ];
            } else {
                throw new Exception('La respuesta de EasyMoney no es válida.');
            }

        } catch (RequestException $e) {
            $transaction->status = 'failed';
            $transaction->save();

            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode(['error' => $e->getMessage()]),
            ]);

            Log::error('Error en la solicitud a EasyMoney: ' . $e->getMessage());

            throw $e;
        } catch (Exception $e) {
            $transaction->status = 'failed';
            $transaction->save();

            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode(['error' => $e->getMessage()]),
            ]);

            Log::error('Error procesando el pago con EasyMoney: ' . $e->getMessage());

            throw $e;
        }
    }
}
