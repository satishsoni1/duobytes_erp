<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\ServiceTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function global(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $user = $request->user();
        $results = [];

        if (mb_strlen($term) >= 2) {
            if ($user->canAccess('leads')) {
                $results['Leads'] = Lead::visibleTo($user)->search($term)->limit(10)->get()
                    ->map(fn ($l) => ['title' => "{$l->name} · {$l->code}", 'sub' => $l->phone, 'url' => route('leads.show', $l)]);
            }
            if ($user->canAccess('customers')) {
                $results['Customers'] = Customer::search($term)->limit(10)->get()
                    ->map(fn ($c) => ['title' => "{$c->display_name} · {$c->code}", 'sub' => $c->phone, 'url' => route('customers.show', $c)]);
            }
            if ($user->canAccess('sales')) {
                $results['Orders'] = SalesOrder::visibleTo($user)->with('customer')->where('number', 'like', "%$term%")->limit(10)->get()
                    ->map(fn ($o) => ['title' => $o->number, 'sub' => $o->customer->name, 'url' => route('orders.show', $o)]);
            }
            if ($user->canAccess('inventory.view')) {
                $results['Products'] = Product::search($term)->limit(10)->get()
                    ->map(fn ($p) => ['title' => "{$p->name} · {$p->sku}", 'sub' => money($p->selling_price), 'url' => route('products.show', $p)]);
            }
            if ($user->canAccess('service')) {
                $results['Service Tickets'] = ServiceTicket::with('customer')->where(fn ($q) => $q->where('number', 'like', "%$term%")->orWhere('subject', 'like', "%$term%"))->limit(10)->get()
                    ->map(fn ($t) => ['title' => "{$t->number} · {$t->subject}", 'sub' => $t->customer->name, 'url' => route('tickets.show', $t)]);
            }
        }

        return view('search', ['term' => $term, 'results' => array_filter($results, fn ($r) => $r->isNotEmpty())]);
    }

    public function customers(Request $request): JsonResponse
    {
        return response()->json(
            Customer::search($request->query('q'))->orderBy('name')->limit(20)->get()
                ->map(fn ($c) => ['value' => $c->id, 'text' => "{$c->display_name} ({$c->phone})", 'address' => $c->address])
        );
    }

    public function products(Request $request): JsonResponse
    {
        return response()->json(
            Product::active()->search($request->query('q'))->withSum('stocks', 'quantity')->orderBy('name')->limit(20)->get()
                ->map(fn ($p) => [
                    'value' => $p->id,
                    'text' => "{$p->name} ({$p->sku})",
                    'price' => (float) $p->selling_price,
                    'cost' => (float) $p->cost_price,
                    'tax' => (float) $p->tax_rate,
                    'stock' => $p->total_stock,
                ])
        );
    }
}
