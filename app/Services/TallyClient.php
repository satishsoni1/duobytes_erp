<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Talks to Tally Prime / Tally.ERP 9 through its built-in XML server
 * (F1 Help → Settings → Connectivity → "Act as Server", default port 9000).
 */
class TallyClient
{
    public function isConfigured(): bool
    {
        return filled(setting('tally_url'));
    }

    /**
     * Requests the Day Book for the sync window. The Day Book carries every voucher type with full
     * inventory and ledger detail; the importer keeps only the configured sales voucher types.
     */
    public function requestXml(?CarbonImmutable $from = null, ?CarbonImmutable $to = null): string
    {
        [$from, $to] = [$from ?? $this->windowStart(), $to ?? CarbonImmutable::today()];
        $company = filled(setting('tally_company'))
            ? '<SVCURRENTCOMPANY>'.htmlspecialchars(setting('tally_company'), ENT_XML1).'</SVCURRENTCOMPANY>'
            : '';

        return <<<XML
            <ENVELOPE>
                <HEADER><VERSION>1</VERSION><TALLYREQUEST>Export</TALLYREQUEST><TYPE>Data</TYPE><ID>Day Book</ID></HEADER>
                <BODY><DESC><STATICVARIABLES>
                    <SVEXPORTFORMAT>\$\$SysName:XML</SVEXPORTFORMAT>
                    <SVFROMDATE TYPE="Date">{$from->format('Ymd')}</SVFROMDATE>
                    <SVTODATE TYPE="Date">{$to->format('Ymd')}</SVTODATE>
                    {$company}
                </STATICVARIABLES></DESC></BODY>
            </ENVELOPE>
            XML;
    }

    public function fetch(): string
    {
        return Http::timeout(180)
            ->withBody($this->requestXml(), 'text/xml; charset=utf-8')
            ->post(rtrim((string) setting('tally_url'), '/'))
            ->throw()
            ->body();
    }

    /**
     * The first sync reads everything from the configured start date. Later syncs re-read a few days
     * before the last successful sync, so vouchers edited after entry (or during downtime) are picked up.
     */
    public function windowStart(): CarbonImmutable
    {
        $floor = filled(setting('tally_sync_from'))
            ? CarbonImmutable::parse(setting('tally_sync_from'))->startOfDay()
            : CarbonImmutable::today()->startOfMonth();

        if (blank(setting('tally_last_synced_at'))) {
            return $floor;
        }

        $lookback = CarbonImmutable::parse(setting('tally_last_synced_at'))->startOfDay()
            ->subDays(max(0, (int) setting('tally_lookback_days', 7)));

        return $lookback->max($floor);
    }
}
