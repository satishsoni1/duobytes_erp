<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Setting;
use App\Models\WebhookLog;
use App\Services\TallyClient;
use App\Services\TallyImporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TallyController extends Controller
{
    protected const KEYS = [
        'tally_mode', 'tally_url', 'tally_company', 'tally_voucher_types', 'tally_order_status',
        'tally_sync_from', 'tally_lookback_days',
    ];

    public function index(TallyClient $tally): View
    {
        return view('tally.index', [
            'logs' => WebhookLog::where('source', 'tally')->latest()->limit(15)->get(),
            'imported' => SalesOrder::whereNotNull('tally_guid')->count(),
            'unmatched' => $this->unmatchedItems(),
            'products' => Product::active()->orderBy('name')->get(['id', 'name', 'sku', 'tally_name']),
            'windowStart' => $tally->windowStart(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'tally_mode' => ['required', 'in:manual,pull,push'],
            'tally_url' => ['nullable', 'required_if:tally_mode,pull', 'url'],
            'tally_company' => ['nullable', 'string', 'max:255'],
            'tally_voucher_types' => ['required', 'string', 'max:255'],
            'tally_order_status' => ['required', Rule::in(array_keys(config('crm.order_statuses')))],
            'tally_sync_from' => ['nullable', 'date'],
            'tally_lookback_days' => ['nullable', 'integer', 'min:0', 'max:90'],
        ]);

        foreach (self::KEYS as $key) {
            Setting::set($key, $request->input($key));
        }
        Setting::set('tally_deduct_stock', $request->boolean('tally_deduct_stock') ? '1' : '0');

        return back()->with('success', 'Tally settings saved.');
    }

    public function sync(TallyClient $tally, TallyImporter $importer): RedirectResponse
    {
        if (! $tally->isConfigured()) {
            return back()->with('error', 'Enter the Tally server address first.');
        }

        try {
            $summary = $importer->importXml($tally->fetch(), 'pull');
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not sync with Tally: '.$e->getMessage());
        }

        return $this->finished($summary);
    }

    public function upload(Request $request, TallyImporter $importer): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:51200', 'mimes:xml,txt']]);

        try {
            $summary = $importer->importXml($request->file('file')->get(), 'upload');
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: '.$e->getMessage());
        }

        return $this->finished($summary);
    }

    /**
     * Link a Tally stock item name to a CRM product, now and for every future import.
     * Order lines already imported are linked too, without touching stock retrospectively.
     */
    public function mapItem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'product_id' => ['required', 'exists:products,id'],
        ]);

        Product::findOrFail($data['product_id'])->update(['tally_name' => $data['name']]);
        $linked = $this->unmatchedQuery($data['name'])->update(['product_id' => $data['product_id']]);

        return back()->with('success', "Linked “{$data['name']}” – $linked order lines updated.");
    }

    /**
     * @param  array{created: int, updated: int, cancelled: int, unchanged: int, skipped: int, errors: list<string>, unmatched: list<string>}  $summary
     */
    protected function finished(array $summary): RedirectResponse
    {
        $message = "Tally import: {$summary['created']} new, {$summary['updated']} updated, {$summary['cancelled']} cancelled, {$summary['unchanged']} unchanged.";

        if ($summary['errors']) {
            return back()->with('warning', $message.' '.count($summary['errors']).' vouchers failed – see the log below.');
        }

        return back()->with('success', $message);
    }

    /**
     * @return Collection<string, int>
     */
    protected function unmatchedItems(): Collection
    {
        return SalesOrderItem::whereNull('product_id')
            ->whereHas('order', fn ($q) => $q->whereNotNull('tally_guid'))
            ->pluck('description')
            ->map(fn ($d) => Str::before($d, ' — '))
            ->countBy()
            ->sortDesc();
    }

    /**
     * @return Builder<SalesOrderItem>
     */
    protected function unmatchedQuery(string $name): Builder
    {
        return SalesOrderItem::whereNull('product_id')
            ->whereHas('order', fn ($q) => $q->whereNotNull('tally_guid'))
            ->where(fn ($q) => $q->where('description', $name)->orWhere('description', 'like', str_replace(['%', '_'], ['\%', '\_'], $name).' — %'));
    }
}
