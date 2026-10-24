<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WhatsAppController extends Controller
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    public function index(Request $request): View
    {
        $term = $request->query('q');

        $threads = WhatsappMessage::query()
            ->select('phone', DB::raw('max(id) as last_id'), DB::raw("sum(case when direction = 'in' and is_read = 0 then 1 else 0 end) as unread"))
            ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('phone', 'like', "%$term%")->orWhere('contact_name', 'like', "%$term%")))
            ->when($request->query('filter') === 'unread', fn ($q) => $q->havingRaw("sum(case when direction = 'in' and is_read = 0 then 1 else 0 end) > 0"))
            ->groupBy('phone')
            ->orderByDesc('last_id')
            ->paginate(30)->withQueryString();

        $last = WhatsappMessage::with('contact')->whereIn('id', $threads->pluck('last_id'))->get()->keyBy('id');

        return view('whatsapp.index', [
            'threads' => $threads,
            'last' => $last,
            'configured' => $this->whatsapp->isConfigured(),
        ]);
    }

    public function show(string $phone): View
    {
        $phone = Phone::normalize($phone);
        $messages = WhatsappMessage::with('user')->where('phone', $phone)->latest('id')->limit(300)->get()->reverse();
        WhatsappMessage::where('phone', $phone)->where('direction', 'in')->where('is_read', false)->update(['is_read' => true]);

        $lastInbound = $messages->where('direction', 'in')->last();
        $contact = Lead::findOpenDuplicate($phone, null) ?? Customer::findByContact($phone);

        return view('whatsapp.show', [
            'phone' => $phone,
            'messages' => $messages,
            'contact' => $contact,
            'name' => $contact?->name ?? $lastInbound?->contact_name ?? '+'.$phone,
            'windowOpen' => $lastInbound && $lastInbound->created_at->gt(now()->subHours(24)),
            'templates' => WhatsappTemplate::where('status', 'APPROVED')->orderBy('name')->get(),
            'configured' => $this->whatsapp->isConfigured(),
        ]);
    }

    public function send(Request $request, string $phone): RedirectResponse
    {
        $phone = Phone::normalize($phone);
        $data = $request->validate([
            'mode' => ['required', 'in:text,template,file'],
            'body' => ['nullable', 'required_if:mode,text', 'string', 'max:4096'],
            'template_id' => ['nullable', 'required_if:mode,template', 'exists:whatsapp_templates,id'],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'string', 'max:500'],
            'file' => ['nullable', 'required_if:mode,file', 'file', 'max:16384', 'mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx'],
            'caption' => ['nullable', 'string', 'max:1000'],
        ]);

        $contact = Lead::findOpenDuplicate($phone, null) ?? Customer::findByContact($phone);

        match ($data['mode']) {
            'text' => $this->whatsapp->queueText($phone, $data['body'], $contact, $request->user()),
            'template' => (function () use ($data, $phone, $contact, $request) {
                $tpl = WhatsappTemplate::findOrFail($data['template_id']);
                $this->whatsapp->queueTemplate($phone, $tpl->name, array_values($data['params'] ?? []), $tpl->language, $contact, $request->user());
            })(),
            'file' => (function () use ($request, $data, $phone, $contact) {
                $file = $request->file('file');
                $path = $file->store('whatsapp/outbound/'.now()->format('Y/m'), 'public');
                $type = str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'document';
                $this->whatsapp->queueMedia($phone, $type, $path, $data['caption'] ?? null, $contact, $request->user());
            })(),
        };

        if ($contact instanceof Lead) {
            $contact->update(['last_contacted_at' => now(), 'status' => $contact->status === 'new' ? 'contacted' : $contact->status]);
        }

        return back()->with('success', 'Message queued.');
    }

    public function media(WhatsappMessage $message): RedirectResponse
    {
        if (! $message->media_path && $message->media_id) {
            try {
                $message->update(['media_path' => $this->whatsapp->downloadMedia($message->media_id)]);
            } catch (\Throwable $e) {
                return back()->with('error', 'Media unavailable: '.$e->getMessage());
            }
        }
        abort_unless($message->media_path, 404);

        return redirect(Storage::disk('public')->url($message->media_path));
    }

    public function templates(): View
    {
        return view('whatsapp.templates', [
            'templates' => WhatsappTemplate::orderBy('name')->get(),
            'configured' => $this->whatsapp->isConfigured(),
            'cities' => Customer::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
        ]);
    }

    public function syncTemplates(): RedirectResponse
    {
        try {
            $count = $this->whatsapp->syncTemplates();
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "$count templates synced from Meta.");
    }

    /**
     * Send an approved template to a customer or lead segment (offers, new collections, festive sales).
     * Only opted-in customers are included; Meta requires templates for business-initiated messages.
     */
    public function broadcast(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasRole('admin', 'manager'), 403);
        $data = $request->validate([
            'template_id' => ['required', 'exists:whatsapp_templates,id'],
            'audience' => ['required', 'in:customers,leads'],
            'customer_type' => ['nullable', Rule::in(array_keys(config('crm.customer_types')))],
            'city' => ['nullable', 'string'],
            'lead_status' => ['nullable', Rule::in(array_keys(config('crm.lead_statuses')))],
            'lead_source' => ['nullable', Rule::in(array_keys(config('crm.lead_sources')))],
            'params' => ['nullable', 'array'],
            'params.*' => ['nullable', 'string', 'max:500'],
        ]);

        $template = WhatsappTemplate::findOrFail($data['template_id']);
        $query = $data['audience'] === 'customers'
            ? Customer::where('whatsapp_opt_in', true)
                ->when($data['customer_type'] ?? null, fn ($q, $v) => $q->where('type', $v))
                ->when($data['city'] ?? null, fn ($q, $v) => $q->where('city', $v))
            : Lead::query()
                ->when($data['lead_status'] ?? null, fn ($q, $v) => $q->where('status', $v))
                ->when($data['lead_source'] ?? null, fn ($q, $v) => $q->where('source', $v))
                ->when($data['city'] ?? null, fn ($q, $v) => $q->where('city', $v));

        $count = 0;
        $seen = [];
        foreach ($query->whereNotNull('whatsapp')->lazy(200) as $contact) {
            if (isset($seen[$contact->whatsapp])) {
                continue;
            }
            $seen[$contact->whatsapp] = true;
            $params = array_map(fn ($p) => str_replace('{name}', $contact->name, (string) $p), array_values($data['params'] ?? []));
            $this->whatsapp->queueTemplate($contact->whatsapp, $template->name, $params, $template->language, $contact, $request->user());
            $count++;
        }

        return back()->with('success', "Broadcast queued to $count contacts.");
    }
}
