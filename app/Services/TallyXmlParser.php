<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;

/**
 * Reads vouchers out of Tally Prime / Tally.ERP 9 XML (a Day Book export, or the response of
 * Tally's XML server on port 9000) into plain arrays the importer can work with.
 *
 * Tally XML signs amounts by side: debits are negative, credits positive. On a sales voucher the
 * party (customer) ledger is the debit, while items, tax and most charges are credits.
 */
class TallyXmlParser
{
    /** Ledger names treated as duties rather than income or charges. */
    protected const TAX_LEDGER = '/\b(c|s|i|ut)?gst\b|\btax\b|\bvat\b|\bcess\b|\btcs\b/i';

    /** In accounting-mode invoices, ledgers matching this are charges rather than goods sold. */
    protected const CHARGE_LEDGER = '/round|freight|transport|packing|forwarding|cartage|delivery|loading|installation|insurance/i';

    /**
     * @return list<array{
     *     guid: string, alter_id: int, type: string, number: string, date: CarbonImmutable, party: string,
     *     buyer: string, gstin: ?string, address: ?string, state: ?string, pincode: ?string, narration: ?string,
     *     reference: ?string, cancelled: bool, deleted: bool, total: float, tax: float,
     *     items: list<array{name: string, quantity: float, unit: ?string, rate: float, amount: float, discount: float, tax_rate: ?float, godown: ?string}>,
     *     charges: list<array{name: string, amount: float}>
     * }>
     */
    public function parse(string $xml): array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($this->sanitize($xml), SimpleXMLElement::class, LIBXML_NOCDATA | LIBXML_PARSEHUGE | LIBXML_NONET);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false) {
            throw new RuntimeException('Not a valid Tally XML file'.($errors ? ': '.trim($errors[0]->message) : '.'));
        }
        if ($error = $doc->xpath('//LINEERROR')) {
            throw new RuntimeException('Tally returned an error: '.trim((string) $error[0]));
        }

        $vouchers = [];
        foreach ($doc->xpath('//VOUCHER') as $voucher) {
            if ($parsed = $this->voucher($voucher)) {
                $vouchers[] = $parsed;
            }
        }

        return $vouchers;
    }

    /**
     * Tally's HTTP server answers in UTF-16 when asked in Unicode and happily emits control-character
     * references such as &#4; that strict XML parsers reject.
     */
    public function sanitize(string $xml): string
    {
        if (str_starts_with($xml, "\xFF\xFE") || (! str_starts_with($xml, "\xFE\xFF") && substr_count(substr($xml, 0, 200), "\0") > 20)) {
            $xml = mb_convert_encoding(preg_replace('/^\xFF\xFE/', '', $xml), 'UTF-8', 'UTF-16LE');
        } elseif (str_starts_with($xml, "\xFE\xFF")) {
            $xml = mb_convert_encoding(substr($xml, 2), 'UTF-8', 'UTF-16BE');
        } elseif (! mb_check_encoding($xml, 'UTF-8')) {
            $xml = mb_convert_encoding($xml, 'UTF-8', 'Windows-1252');
        }

        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml);
        $xml = preg_replace('/^\s*<\?xml[^>]*\?>/', '', $xml);
        $xml = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $xml);

        return preg_replace_callback('/&#(x[0-9a-f]+|\d+);/i', function (array $m) {
            $code = str_starts_with(strtolower($m[1]), 'x') ? hexdec(substr($m[1], 1)) : (int) $m[1];

            return in_array($code, [9, 10, 13], true) || $code >= 32 ? $m[0] : '';
        }, $xml);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function voucher(SimpleXMLElement $v): ?array
    {
        $guid = $this->text($v->GUID) ?? $this->text($v['REMOTEID']);
        $date = $this->text($v->DATE);
        if (! $guid || ! $date) {
            return null;
        }

        $party = $this->text($v->PARTYLEDGERNAME) ?? $this->text($v->PARTYNAME) ?? 'Cash';

        $items = [];
        foreach ($this->entries($v, 'ALLINVENTORYENTRIES.LIST', 'INVENTORYENTRIES.LIST') as $entry) {
            if ($item = $this->inventoryEntry($entry)) {
                $items[] = $item;
            }
        }

        $partyTotal = null;
        $tax = 0.0;
        $charges = [];
        foreach ($this->entries($v, 'LEDGERENTRIES.LIST', 'ALLLEDGERENTRIES.LIST') as $entry) {
            $name = $this->text($entry->LEDGERNAME);
            $amount = $this->number($entry->AMOUNT);
            if (! $name || $amount === 0.0) {
                continue;
            }

            if ($this->text($entry->ISPARTYLEDGER) === 'Yes' || strcasecmp($name, $party) === 0) {
                $partyTotal = ($partyTotal ?? 0) - $amount;
            } elseif (preg_match(self::TAX_LEDGER, $name)) {
                $tax += $amount;
            } elseif (! $items && $amount > 0 && ! preg_match(self::CHARGE_LEDGER, $name)) {
                // Accounting-mode invoice: the sales ledger itself is the thing sold.
                $items[] = ['name' => $name, 'quantity' => 1.0, 'unit' => null, 'rate' => $amount, 'amount' => $amount, 'discount' => 0.0, 'tax_rate' => null, 'godown' => null, 'from_ledger' => true];
            } else {
                $charges[] = ['name' => $name, 'amount' => $amount];
            }
        }

        $items = array_map(function (array $item) {
            unset($item['from_ledger']);

            return $item;
        }, $items);

        $goods = array_sum(array_column($items, 'amount'));
        $total = $partyTotal ?? $goods + $tax + array_sum(array_column($charges, 'amount'));

        return [
            'guid' => $guid,
            'alter_id' => (int) $this->text($v->ALTERID),
            'type' => $this->text($v->VOUCHERTYPENAME) ?? $this->text($v['VCHTYPE']) ?? '',
            'number' => $this->text($v->VOUCHERNUMBER) ?? '',
            'date' => CarbonImmutable::createFromFormat('Ymd', substr($date, 0, 8))->startOfDay(),
            'party' => $party,
            'buyer' => $this->text($v->BASICBUYERNAME) ?? $this->text($v->PARTYMAILINGNAME) ?? $party,
            'gstin' => $this->text($v->PARTYGSTIN) ?? $this->text($v->BASICBUYERGSTIN),
            'address' => $this->lines($v->xpath('ADDRESS.LIST/ADDRESS|BASICBUYERADDRESS.LIST/BASICBUYERADDRESS')),
            'state' => $this->text($v->STATENAME) ?? $this->text($v->PLACEOFSUPPLY),
            'pincode' => $this->text($v->PARTYPINCODE),
            'narration' => $this->text($v->NARRATION),
            'reference' => $this->text($v->REFERENCE),
            'cancelled' => $this->text($v->ISCANCELLED) === 'Yes',
            'deleted' => $this->text($v->ISDELETED) === 'Yes' || strcasecmp((string) $v['ACTION'], 'Delete') === 0,
            'total' => round(abs($total), 2),
            'tax' => round($tax, 2),
            'items' => $items,
            'charges' => $charges,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function inventoryEntry(SimpleXMLElement $entry): ?array
    {
        $name = $this->text($entry->STOCKITEMNAME);
        if (! $name) {
            return null;
        }

        [$quantity, $unit] = $this->quantity($this->text($entry->BILLEDQTY) ?? $this->text($entry->ACTUALQTY));

        $rates = [];
        foreach ($entry->xpath('RATEDETAILS.LIST') as $rate) {
            $head = strtoupper((string) $this->text($rate->GSTRATEDUTYHEAD));
            $rates[$head] = abs($this->number($rate->GSTRATE));
        }
        $taxRate = ! empty($rates['IGST']) ? $rates['IGST'] : (($rates['CGST'] ?? 0) + ($rates['SGST/UTGST'] ?? $rates['SGST'] ?? $rates['UTGST'] ?? 0));

        return [
            'name' => $name,
            'quantity' => $quantity,
            'unit' => $unit,
            'rate' => abs((float) strtok((string) $this->text($entry->RATE), '/')),
            'amount' => round(abs($this->number($entry->AMOUNT)), 2),
            'discount' => abs($this->number($entry->DISCOUNT)),
            'tax_rate' => $taxRate > 0 ? $taxRate : null,
            'godown' => $this->text($entry->{'BATCHALLOCATIONS.LIST'}?->GODOWNNAME),
        ];
    }

    /**
     * Exports carry either the "ALL…" or the plain variant of a list; reading both would double count.
     *
     * @return array<int, SimpleXMLElement>
     */
    protected function entries(SimpleXMLElement $voucher, string $preferred, string $fallback): array
    {
        return $voucher->xpath($preferred) ?: ($voucher->xpath($fallback) ?: []);
    }

    /**
     * Tally quantities look like " 2 Nos", "2.500 Sqft" or "2 Nos = 1 Set".
     *
     * @return array{0: float, 1: ?string}
     */
    protected function quantity(?string $value): array
    {
        if ($value && preg_match('/(-?[\d,]*\.?\d+)\s*([A-Za-z.]+)?/', $value, $m)) {
            return [abs((float) str_replace(',', '', $m[1])), $m[2] ?? null];
        }

        return [1.0, null];
    }

    protected function number(?SimpleXMLElement $node): float
    {
        return (float) str_replace(',', '', (string) $this->text($node));
    }

    protected function text(mixed $node): ?string
    {
        $value = trim((string) $node);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, SimpleXMLElement>|false|null  $nodes
     */
    protected function lines(array|false|null $nodes): ?string
    {
        $lines = array_filter(array_map(fn ($n) => $this->text($n), $nodes ?: []));

        return $lines ? implode("\n", $lines) : null;
    }
}
