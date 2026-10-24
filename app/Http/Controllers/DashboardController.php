<?php

namespace App\Http\Controllers;

use App\Models\FollowUp;
use App\Models\Installation;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\ServiceTicket;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $monthStart = now()->startOfMonth();
        $data = [];

        $data['myFollowUps'] = FollowUp::with('followable')->where('user_id', $user->id)->pending()
            ->where('due_at', '<=', now()->endOfDay())->orderBy('due_at')->limit(10)->get();

        if ($user->canAccess('leads')) {
            $leads = Lead::visibleTo($user);
            $monthLeads = (clone $leads)->where('created_at', '>=', $monthStart)->count();
            $wonLeads = (clone $leads)->where('status', 'won')->where('converted_at', '>=', $monthStart)->count();

            $data['leadStats'] = [
                'today' => (clone $leads)->whereDate('created_at', today())->count(),
                'open' => (clone $leads)->open()->count(),
                'month' => $monthLeads,
                'won' => $wonLeads,
                'conversion' => $monthLeads ? round($wonLeads / $monthLeads * 100, 1) : 0,
                'untouched' => (clone $leads)->where('status', 'new')->where('created_at', '<', now()->subHours(2))->count(),
            ];
            $data['leadsBySource'] = (clone $leads)->where('created_at', '>=', now()->subDays(30))
                ->selectRaw('source, count(*) as total')->groupBy('source')->pluck('total', 'source')
                ->mapWithKeys(fn ($v, $k) => [config("crm.lead_sources.$k", $k) => $v]);
            $data['pipeline'] = (clone $leads)->open()->selectRaw('status, count(*) as total, coalesce(sum(budget),0) as value')
                ->groupBy('status')->get()->keyBy('status');
            $data['recentLeads'] = (clone $leads)->with('assignee')->latest()->limit(6)->get();
        }

        if ($user->canAccess('sales')) {
            $orders = SalesOrder::visibleTo($user)->where('status', '!=', 'cancelled');
            $data['salesStats'] = [
                'month' => (clone $orders)->where('order_date', '>=', $monthStart)->sum('total'),
                'orders' => (clone $orders)->where('order_date', '>=', $monthStart)->count(),
                'collected' => Payment::whereHas('order', fn ($q) => $q->visibleTo($user))->where('paid_on', '>=', $monthStart)->sum('amount'),
                'receivable' => (clone $orders)->selectRaw('coalesce(sum(total - paid_amount),0) as due')->value('due'),
                'pendingDelivery' => (clone $orders)->whereIn('status', ['pending', 'confirmed', 'in_production', 'ready', 'dispatched'])->count(),
            ];

            $trend = (clone $orders)->where('order_date', '>=', now()->subMonths(11)->startOfMonth())
                ->get(['order_date', 'total'])
                ->groupBy(fn ($o) => $o->order_date->format('Y-m'))
                ->map->sum('total');
            $data['salesTrend'] = collect(range(11, 0))->mapWithKeys(function ($i) use ($trend) {
                $m = now()->subMonths($i);

                return [$m->format('M y') => round((float) ($trend[$m->format('Y-m')] ?? 0), 2)];
            });
        }

        if ($user->canAccess('inventory.view')) {
            $data['lowStock'] = Product::active()->lowStock()->withSum('stocks', 'quantity')->orderBy('name')->limit(8)->get();
            $data['lowStockCount'] = Product::active()->lowStock()->count();
        }

        if ($user->canAccess('installations')) {
            $data['upcomingInstallations'] = Installation::visibleTo($user)->with(['customer', 'technician'])
                ->whereIn('status', ['scheduled', 'rescheduled', 'in_progress'])
                ->whereBetween('scheduled_date', [today(), today()->addDays(7)])
                ->orderBy('scheduled_date')->limit(8)->get();
            $data['pendingInstallations'] = Installation::visibleTo($user)->where('status', 'pending')->count();
        }

        if ($user->canAccess('service')) {
            $tickets = ServiceTicket::visibleTo($user)->open();
            $data['ticketStats'] = [
                'open' => (clone $tickets)->count(),
                'overdue' => (clone $tickets)->where('due_at', '<', now())->count(),
                'urgent' => (clone $tickets)->whereIn('priority', ['high', 'urgent'])->count(),
            ];
        }

        return view('dashboard', $data);
    }
}
