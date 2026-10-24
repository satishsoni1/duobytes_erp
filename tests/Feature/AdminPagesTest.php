<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function adminPages(): array
    {
        return [
            'whatsapp inbox' => ['whatsapp.index', []],
            'whatsapp templates' => ['whatsapp.templates', []],
            'whatsapp chat' => ['whatsapp.show', ['phone' => '919876543210']],
            'sales report' => ['reports.index', ['tab' => 'sales']],
            'leads report' => ['reports.index', ['tab' => 'leads']],
            'inventory report' => ['reports.index', ['tab' => 'inventory']],
            'service report' => ['reports.index', ['tab' => 'service']],
            'users' => ['users.index', []],
            'new user' => ['users.create', []],
            'settings' => ['settings.edit', []],
            'webhook logs' => ['settings.webhook-logs', []],
        ];
    }

    /**
     * @param  array<string, string>  $parameters
     */
    #[DataProvider('adminPages')]
    public function test_admin_can_open_page(string $route, array $parameters): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(route($route, $parameters))
            ->assertOk();
    }

    public function test_every_authenticated_page_without_parameters_renders(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $uris = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true)
                && in_array('auth', $route->gatherMiddleware(), true)
                && ! str_contains($route->uri(), '{')
                && ! str_contains($route->uri(), 'export'))
            ->map(fn ($route) => '/'.ltrim($route->uri(), '/'));

        foreach ($uris as $uri) {
            $status = $this->get($uri)->status();
            $this->assertSame(200, $status, "GET $uri returned $status");
        }
    }

    public function test_whatsapp_chat_shows_conversation_and_marks_inbound_read(): void
    {
        WhatsappTemplate::create(['name' => 'festive_offer', 'body' => 'Hi {{1}}, enjoy 20% off', 'variable_count' => 1]);
        $inbound = WhatsappMessage::create(['phone' => '919876543210', 'contact_name' => 'Asha', 'direction' => 'in', 'body' => 'Is the teak sofa in stock?']);

        $this->actingAs(User::factory()->role('sales')->create())
            ->get(route('whatsapp.show', '919876543210'))
            ->assertOk()
            ->assertSee('Is the teak sofa in stock?')
            ->assertSee('festive_offer');

        $this->assertTrue($inbound->fresh()->is_read);
    }

    public function test_settings_page_lists_webhook_urls_once_token_exists(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('settings.token'))->assertRedirect();

        $this->actingAs($admin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertSee(route('webhooks.leads', setting('webhook_token')));
    }

    public function test_help_guide_is_available_to_every_role(): void
    {
        foreach (array_keys(config('crm.roles')) as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get(route('help.index'))
                ->assertOk()
                ->assertSee('How the business flows')
                ->assertSee('Tally sync');
        }
    }

    public function test_reports_are_forbidden_for_sales_users(): void
    {
        $this->actingAs(User::factory()->role('sales')->create())
            ->get(route('reports.index'))
            ->assertForbidden();
    }
}
