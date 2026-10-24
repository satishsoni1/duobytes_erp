<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Facebook / Instagram integration via the Meta Graph API:
 * Lead Ads (leadgen webhook) and Messenger / Instagram direct messages.
 */
class MetaLeadService
{
    public function token(): ?string
    {
        return setting('meta_page_access_token') ?: config('crm.meta.page_access_token');
    }

    protected function graph(string $path, array $query = []): array
    {
        $response = Http::baseUrl('https://graph.facebook.com/'.config('crm.whatsapp.api_version'))
            ->timeout(20)
            ->retry(2, 500, throw: false)
            ->get($path, $query + ['access_token' => $this->token()]);

        if (! $response->successful()) {
            throw new RuntimeException('Graph API error: '.($response->json('error.message') ?? $response->body()));
        }

        return $response->json();
    }

    /**
     * Fetch a Lead Ads submission and map it to CRM lead fields.
     *
     * @return array{data: array<string, mixed>, source: string, raw: array<string, mixed>}
     */
    public function fetchLead(string $leadgenId): array
    {
        $raw = $this->graph($leadgenId, ['fields' => 'field_data,created_time,ad_name,campaign_name,form_id,platform']);

        return [
            'data' => $this->mapFieldData($raw['field_data'] ?? []) + [
                'source_ref' => $leadgenId,
                'campaign' => $raw['campaign_name'] ?? ($raw['ad_name'] ?? null),
            ],
            'source' => in_array(strtolower($raw['platform'] ?? ''), ['ig', 'instagram'], true) ? 'instagram' : 'facebook',
            'raw' => $raw,
        ];
    }

    /**
     * @param  array<int, array{name: string, values: array<int, string>}>  $fields
     * @return array<string, mixed>
     */
    public function mapFieldData(array $fields): array
    {
        $data = [];
        $extra = [];

        foreach ($fields as $field) {
            $key = strtolower($field['name'] ?? '');
            $value = implode(', ', $field['values'] ?? []);

            match (true) {
                in_array($key, ['full_name', 'name'], true) => $data['name'] = $value,
                $key === 'first_name' => $data['name'] = trim($value.' '.($data['name'] ?? '')),
                $key === 'last_name' => $data['name'] = trim(($data['name'] ?? '').' '.$value),
                str_contains($key, 'phone') => $data['phone'] = $value,
                str_contains($key, 'email') => $data['email'] = $value,
                $key === 'city' => $data['city'] = $value,
                in_array($key, ['street_address', 'address'], true) => $data['address'] = $value,
                str_contains($key, 'budget') => $data['budget'] = preg_replace('/[^\d.]/', '', $value) ?: null,
                default => $extra[] = Str::headline($key).': '.$value,
            };
        }

        if ($extra) {
            $data['requirement'] = implode("\n", $extra);
        }

        return $data;
    }

    /**
     * Look up the display name of a Messenger PSID or Instagram-scoped user id.
     */
    public function profileName(string $userId, string $platform): ?string
    {
        try {
            $profile = $this->graph($userId, ['fields' => $platform === 'instagram' ? 'name,username' : 'first_name,last_name']);

            return $platform === 'instagram'
                ? ($profile['name'] ?? $profile['username'] ?? null)
                : (trim(($profile['first_name'] ?? '').' '.($profile['last_name'] ?? '')) ?: null);
        } catch (\Throwable) {
            return null;
        }
    }
}
