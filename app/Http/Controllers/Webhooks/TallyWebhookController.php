<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\TallyClient;
use App\Services\TallyImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Push mode for CRMs hosted outside the Tally office network: a scheduled script on the Tally PC
 * fetches the request below, asks the local Tally server for that data and posts the answer back.
 */
class TallyWebhookController extends Controller
{
    public function request(TallyClient $tally): Response
    {
        return response($tally->requestXml(), 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    public function receive(Request $request, TallyImporter $importer): JsonResponse
    {
        $xml = $request->getContent();
        if (trim($xml) === '') {
            return response()->json(['message' => 'Empty body – post the XML returned by Tally.'], 422);
        }

        try {
            $summary = $importer->importXml($xml, 'push');
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true] + $summary);
    }
}
