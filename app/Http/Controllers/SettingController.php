<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\WebhookLog;
use App\Models\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SettingController extends Controller
{
    /** Plain (non-secret) keys editable from the settings screen. */
    protected const KEYS = [
        'company_name', 'company_phone', 'company_email', 'company_address', 'company_tax_number', 'company_website',
        'bank_details', 'default_tax_rate', 'default_country_code', 'quotation_validity_days', 'quotation_terms', 'invoice_terms',
        'lead_assignment', 'lead_first_response_minutes', 'whatsapp_auto_create_lead', 'allow_negative_stock',
        'whatsapp_phone_number_id', 'whatsapp_business_account_id', 'whatsapp_verify_token', 'whatsapp_template_language',
        'meta_verify_token',
    ];

    public function edit(): View
    {
        return view('settings.edit', [
            'templates' => WhatsappTemplate::orderBy('name')->get(),
            'secretsSet' => collect(Setting::SECRET_KEYS)->mapWithKeys(fn ($k) => [$k => filled(setting($k))]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'company_email' => ['nullable', 'email'],
            'default_tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'default_country_code' => ['nullable', 'digits_between:1,4'],
            'quotation_validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'lead_assignment' => ['nullable', 'in:round_robin,manual'],
            'lead_first_response_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'notify' => ['nullable', 'array'],
        ]);

        foreach (self::KEYS as $key) {
            if ($request->has($key) || in_array($key, ['whatsapp_auto_create_lead', 'allow_negative_stock'], true)) {
                $value = in_array($key, ['whatsapp_auto_create_lead', 'allow_negative_stock'], true)
                    ? ($request->boolean($key) ? '1' : '0')
                    : $request->input($key);
                Setting::set($key, $value);
            }
        }

        // Secrets are only overwritten when a new value is typed in.
        foreach (Setting::SECRET_KEYS as $key) {
            if (filled($request->input($key))) {
                Setting::set($key, $request->input($key));
            }
        }

        foreach (array_keys(config('crm.notification_events')) as $event) {
            $row = $request->input("notify.$event", []);
            Setting::set("notify_{$event}_enabled", ! empty($row['enabled']) ? '1' : '0');
            Setting::set("notify_{$event}_template", $row['template'] ?? null);
            Setting::set("notify_{$event}_text", $row['text'] ?? null);
        }

        if ($request->hasFile('logo')) {
            Setting::set('company_logo', $request->file('logo')->store('branding', 'public'));
        }

        return back()->with('success', 'Settings saved.');
    }

    public function regenerateToken(): RedirectResponse
    {
        Setting::set('webhook_token', Str::random(40));

        return back()->with('success', 'Webhook token regenerated – update it in your website forms and integrations.');
    }

    public function webhookLogs(Request $request): View
    {
        return view('settings.webhook-logs', [
            'logs' => WebhookLog::when($request->query('source'), fn ($q, $v) => $q->where('source', $v))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->latest()->paginate(30)->withQueryString(),
        ]);
    }
}
