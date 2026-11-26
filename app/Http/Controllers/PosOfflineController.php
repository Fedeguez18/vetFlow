<?php

namespace App\Http\Controllers;

use App\Http\Requests\SyncOfflineSaleRequest;
use App\Services\OfflineSaleService;
use App\Services\PosTerminalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Selling at the mostrador without internet: the page the browser keeps for when the connection drops,
 * the data it saves while online, and the upload of the sales once the connection is back.
 */
class PosOfflineController extends Controller
{
    /**
     * The offline mostrador. It is saved by the browser and works from its own copy of the data, so it
     * doesn't depend on who is logged in nor on the caja: the browser decides what to show.
     */
    public function page(): Response
    {
        return response()->view('pos.offline')->header('Cache-Control', 'no-cache');
    }

    public function snapshot(PosTerminalService $terminals, OfflineSaleService $offline): JsonResponse
    {
        $cashSession = $terminals->openCashSession();

        return response()->json($offline->snapshot($terminals->current(), $cashSession, $cashSession && $terminals->isUnlocked($cashSession)));
    }

    /**
     * A fresh CSRF token before uploading: the one in the page may have expired while the internet was down.
     */
    public function token(): JsonResponse
    {
        return response()->json(['token' => csrf_token()]);
    }

    public function sync(SyncOfflineSaleRequest $request, PosTerminalService $terminals, OfflineSaleService $offline): JsonResponse
    {
        return response()->json($offline->import($terminals->current(), $request->validated()));
    }
}
