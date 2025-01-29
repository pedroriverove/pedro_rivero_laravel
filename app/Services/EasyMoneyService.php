<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PaymentRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class EasyMoneyService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost:3000', // URL del servidor de EasyMoney
            'timeout' => 5.0,
        ]);
    }

    public function processPayment(Transaction $transaction)
    {
        try {
            // Verificar si el monto tiene decimales
            if (fmod($transaction->amount, 1) !== 0.00) {
                throw new \Exception("EasyMoney no acepta montos con decimales.");
            }
            $payload = [
                'amount' => $transaction->amount,
                'currency' => $transaction->currency,
            ];

            // Registrar la petición antes de enviarla
            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode($payload),
            ]);

            $response = $this->client->post('/process', [
                'json' => $payload,
            ]);

            $responseData = json_decode($response->getBody(), true);

            // Actualizar la transacción con la respuesta, en caso de éxito.
            $transaction->status = 'success';
            $transaction->transaction_id = $responseData['transaction_id'];
            $transaction->save();

            return $responseData;

        } catch (RequestException $e) {
            // Manejar errores de Guzzle (conexión, timeout, etc.)
            $transaction->status = 'failed';
            $transaction->save();

            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode(['error' => $e->getMessage()]),
            ]);

            throw $e;
        } catch (\Exception $e) {
            // Manejar errores relacionados con decimales
            $transaction->status = 'failed';
            $transaction->save();
            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode(['error' => $e->getMessage()]),
            ]);

            throw $e;
        }
    }
}
