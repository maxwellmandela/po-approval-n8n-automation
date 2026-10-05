<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IntegrationController extends Controller
{
    public function webhook(Request $request)
    {
        $secret = config('procurement.n8n_webhook_secret');
        $signature = $request->header('X-N8N-Signature');

        if ($secret && $signature) {
            $expected = hash_hmac('sha256', json_encode($request->all()), $secret);
            if (! hash_equals($expected, $signature)) {
                return response()->json(['message' => 'Invalid signature.'], 403);
            }
        }

        Log::info('n8n webhook received', ['payload' => $request->all()]);

        return response()->json([
            'status' => 'accepted',
            'received_at' => now()->toISOString(),
            'event' => $request->input('event'),
        ]);
    }
}
