<?php

use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\ServiceTicket;
use App\Models\User;
use App\Models\WebhookLog;
use App\Notifications\CrmAlert;
use App\Services\CustomerNotifier;
use App\Services\TallyClient;
use App\Services\TallyImporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('crm:follow-up-reminders', function () {
    $due = FollowUp::with(['user', 'followable'])->pending()->whereNull('reminded_at')
        ->where('due_at', '<=', now()->addMinutes(15))->get();

    foreach ($due as $followUp) {
        $subject = $followUp->followable;
        $url = match (true) {
            $subject instanceof Lead => route('leads.show', $subject),
            $subject instanceof Customer => route('customers.show', $subject),
            $subject instanceof ServiceTicket => route('tickets.show', $subject),
            default => route('follow-ups.index'),
        };
        $followUp->user?->notify(new CrmAlert(
            $followUp->isOverdue() ? 'Follow-up overdue' : 'Follow-up due',
            $followUp->title.($subject?->name ?? null ? ' – '.$subject->name : ''),
            $url,
            'alarm',
            $followUp->isOverdue() ? 'danger' : 'warning',
        ));
        $followUp->update(['reminded_at' => now()]);
    }

    $this->info("Reminded {$due->count()} follow-ups.");
})->purpose('Notify users about follow-ups due in the next 15 minutes');

Artisan::command('crm:sla-check', function () {
    $managers = User::active()->role(['admin', 'manager'])->get();
    $count = 0;

    ServiceTicket::with(['assignee', 'customer'])->open()->where('due_at', '<', now())->each(function (ServiceTicket $ticket) use ($managers, &$count) {
        if (! Cache::add("sla-breach-{$ticket->id}", true, now()->addDays(30))) {
            return;
        }
        $alert = new CrmAlert('SLA breached', "{$ticket->number} – {$ticket->subject} ({$ticket->customer->name})", route('tickets.show', $ticket), 'exclamation-triangle', 'danger');
        $ticket->assignee?->notify($alert);
        $managers->reject(fn ($m) => $m->id === $ticket->assigned_to)->each->notify($alert);
        $count++;
    });

    $this->info("Escalated $count tickets.");
})->purpose('Escalate service tickets that missed their SLA');

Artisan::command('crm:low-stock-alert', function () {
    $products = Product::active()->lowStock()->withSum('stocks', 'quantity')->get();
    if ($products->isEmpty()) {
        return;
    }
    $list = $products->take(5)->map(fn ($p) => "{$p->name} ({$p->total_stock})")->implode(', ');
    User::active()->role(['admin', 'manager', 'inventory'])->get()->each->notify(new CrmAlert(
        "{$products->count()} products low on stock",
        $list.($products->count() > 5 ? '…' : ''),
        route('stock.index', ['stock' => 'low']),
        'box-seam',
        'warning',
    ));
})->purpose('Daily low-stock digest');

Artisan::command('crm:expire-quotations', function () {
    $count = Quotation::whereIn('status', ['draft', 'sent'])->whereDate('valid_until', '<', today())->update(['status' => 'expired']);
    $this->info("Expired $count quotations.");
})->purpose('Mark quotations past their validity as expired');

Artisan::command('crm:customer-greetings', function (CustomerNotifier $notifier) {
    $customers = Customer::where('whatsapp_opt_in', true)->whereNotNull('birthday')
        ->whereMonth('birthday', today()->month)->whereDay('birthday', today()->day)->get();
    $customers->each(fn ($c) => $notifier->send('birthday_wish', $c, []));
    $this->info("Greeted {$customers->count()} customers.");
})->purpose('Send WhatsApp birthday wishes');

Artisan::command('crm:tally-sync {--force : Run even when Tally sync mode is not "pull"}', function (TallyClient $tally, TallyImporter $importer) {
    if (! $tally->isConfigured() || (setting('tally_mode') !== 'pull' && ! $this->option('force'))) {
        $this->info('Tally pull sync is not enabled.');

        return;
    }

    $summary = $importer->importXml($tally->fetch(), 'pull');
    $this->info("Tally: {$summary['created']} new, {$summary['updated']} updated, {$summary['cancelled']} cancelled, ".count($summary['errors']).' errors.');
})->purpose('Import new and edited sales vouchers from the Tally XML server');

Artisan::command('crm:prune-webhook-logs', function () {
    $count = WebhookLog::where('created_at', '<', now()->subDays(60))->delete();
    $this->info("Pruned $count webhook logs.");
})->purpose('Delete webhook logs older than 60 days');

Schedule::command('crm:follow-up-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('crm:sla-check')->hourly()->withoutOverlapping();
Schedule::command('crm:low-stock-alert')->dailyAt('09:00');
Schedule::command('crm:expire-quotations')->dailyAt('00:30');
Schedule::command('crm:customer-greetings')->dailyAt('10:00');
Schedule::command('crm:tally-sync')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('crm:prune-webhook-logs')->weekly();
Schedule::command('queue:prune-failed --hours=720')->weekly();
