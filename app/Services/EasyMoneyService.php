<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\PaymentRequest;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class EasyMoneyService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => env('EASYMONEY_BASE_URI'), // URL del servidor de EasyMoney
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

            // Obtener el código de estado de la respuesta
            $statusCode = $response->getStatusCode();

            // Si el código de estado es 200, generamos un ID de transacción aleatorio
            if ($statusCode === 200) {
                Log::info("EasyMoney returned 200 OK. Generating random transaction ID.");

                $transaction->status = 'success';
                $transaction->transaction_id = 'EM-' . str_pad(mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
                $transaction->save();

                return [
                    'message' => 'Pago procesado con EasyMoney (se generó un transaction_id aleatorio)',
                    'transaction_id' => $transaction->transaction_id
                ];
            } else {
                // Si hay otro código de estado, asumimos que es un error
                throw new \Exception('La respuesta de EasyMoney no es válida.');
            }

        } catch (RequestException $e) {
            // Manejar errores de Guzzle (conexión, timeout, etc.)
            $transaction->status = 'failed';
            $transaction->save();

            // Registrar la petición con el error
            PaymentRequest::create([
                'transaction_id' => $transaction->id,
                'type' => 'request',
                'payload' => json_encode(['error' => $e->getMessage()]),
            ]);

            Log::error('Error en la solicitud a EasyMoney: ' . $e->getMessage());

            throw $e;
        } catch (\Exception $e) {
            // Manejar errores relacionados con decimales u otras excepciones
            $transaction->status = 'failed';
            $transaction->save();

            // Registrar la petición con el error
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
