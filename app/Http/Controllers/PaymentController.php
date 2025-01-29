<?php

namespace App\Http\Controllers;

use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Services\EasyMoneyService;
use App\Services\SuperWalletzService;

class PaymentController extends Controller
{
    protected EasyMoneyService $easyMoneyService;
    protected SuperWalletzService $superWalletzService;

    /**
     * @param EasyMoneyService $easyMoneyService
     * @param SuperWalletzService $superWalletzService
     */
    public function __construct(EasyMoneyService $easyMoneyService, SuperWalletzService $superWalletzService)
    {
        $this->easyMoneyService = $easyMoneyService;
        $this->superWalletzService = $superWalletzService;
    }

    /**
     * @param Request $request
     * @return JsonResponse|void
     * @throws GuzzleException
     */
    public function process(Request $request)
    {
        $request->validate([
            'payment_gateway' => 'required|in:EasyMoney,SuperWalletz',
            'amount' => 'required|numeric',
            'currency' => 'required|string',
        ]);

        $transaction = Transaction::create([
            'payment_gateway' => $request->input('payment_gateway'),
            'amount' => $request->input('amount'),
            'currency' => $request->input('currency'),
            'status' => 'pending',
        ]);

        try {
            if ($request->input('payment_gateway') === 'EasyMoney') {
                $response = $this->easyMoneyService->processPayment($transaction);
                return response()->json($response);
            } elseif ($request->input('payment_gateway') === 'SuperWalletz') {
                $callbackUrl = route('webhook.superwalletz', ['transaction_id' => $transaction->id]);
                $response = $this->superWalletzService->processPayment($transaction, $callbackUrl);
                return response()->json($response);
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al procesar el pago', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * @param Request $request
     * @param $transaction_id
     * @return JsonResponse
     */
    public function superWalletzWebhook(Request $request, $transaction_id): JsonResponse
    {
        $transaction = Transaction::findOrFail($transaction_id);
        try {
            $payload = $request->all();
            $this->superWalletzService->handleWebhook($transaction, $payload);
            return response()->json(['message' => 'Webhook recibido y procesado correctamente.']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error al procesar el webhook', 'error' => $e->getMessage()], 500);
        }
    }
}
