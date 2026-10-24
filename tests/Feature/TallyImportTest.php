<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TallyXmlParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TallyImportTest extends TestCase
{
    use RefreshDatabase;

    protected Warehouse $showroom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->showroom = Warehouse::create(['name' => 'Main Showroom', 'code' => 'SHOW1', 'type' => 'showroom', 'is_default' => true]);
        Setting::set('webhook_token', 'test-token');
    }

    /**
     * A GST invoice as exported from Tally Prime's Day Book: 2 sofas @ 15,000 less 10%, 1 bed @ 20,000,
     * CGST + SGST 9% each = 55,460.
     */
    protected function invoiceXml(int $alterId = 101, string $cancelled = 'No', string $sofaQty = '2 Nos'): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <ENVELOPE><BODY><IMPORTDATA><REQUESTDATA>
            <TALLYMESSAGE xmlns:UDF="TallyUDF">
             <VOUCHER REMOTEID="abc-1" VCHTYPE="Sales" ACTION="Create">
              <DATE>20261020</DATE>
              <GUID>guid-sales-0001</GUID>
              <ALTERID>{$alterId}</ALTERID>
              <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>
              <VOUCHERNUMBER>INV/26-27/045</VOUCHERNUMBER>
              <PARTYLEDGERNAME>Sharma Interiors</PARTYLEDGERNAME>
              <PARTYMAILINGNAME>Sharma Interiors Pvt Ltd</PARTYMAILINGNAME>
              <PARTYGSTIN>27ABCDE1234F1Z5</PARTYGSTIN>
              <STATENAME>Maharashtra</STATENAME>
              <PARTYPINCODE>411001</PARTYPINCODE>
              <ADDRESS.LIST TYPE="String"><ADDRESS>12 MG Road</ADDRESS><ADDRESS>Pune</ADDRESS></ADDRESS.LIST>
              <NARRATION>Deliver after Diwali &#4;</NARRATION>
              <ISCANCELLED>{$cancelled}</ISCANCELLED>
              <ALLINVENTORYENTRIES.LIST>
               <STOCKITEMNAME>Teak Sofa 3 Seater</STOCKITEMNAME>
               <RATE>15000.00/Nos</RATE>
               <DISCOUNT>10</DISCOUNT>
               <AMOUNT>27000.00</AMOUNT>
               <ACTUALQTY> {$sofaQty}</ACTUALQTY>
               <BILLEDQTY> {$sofaQty}</BILLEDQTY>
               <BATCHALLOCATIONS.LIST><GODOWNNAME>Main Showroom</GODOWNNAME></BATCHALLOCATIONS.LIST>
               <RATEDETAILS.LIST><GSTRATEDUTYHEAD>CGST</GSTRATEDUTYHEAD><GSTRATE> 9</GSTRATE></RATEDETAILS.LIST>
               <RATEDETAILS.LIST><GSTRATEDUTYHEAD>SGST/UTGST</GSTRATEDUTYHEAD><GSTRATE> 9</GSTRATE></RATEDETAILS.LIST>
              </ALLINVENTORYENTRIES.LIST>
              <ALLINVENTORYENTRIES.LIST>
               <STOCKITEMNAME>King Bed Walnut</STOCKITEMNAME>
               <RATE>20000.00/Nos</RATE>
               <AMOUNT>20000.00</AMOUNT>
               <BILLEDQTY> 1 Nos</BILLEDQTY>
              </ALLINVENTORYENTRIES.LIST>
              <LEDGERENTRIES.LIST><LEDGERNAME>Sharma Interiors</LEDGERNAME><ISPARTYLEDGER>Yes</ISPARTYLEDGER><AMOUNT>-55460.00</AMOUNT></LEDGERENTRIES.LIST>
              <LEDGERENTRIES.LIST><LEDGERNAME>Output CGST 9%</LEDGERNAME><AMOUNT>4230.00</AMOUNT></LEDGERENTRIES.LIST>
              <LEDGERENTRIES.LIST><LEDGERNAME>Output SGST 9%</LEDGERNAME><AMOUNT>4230.00</AMOUNT></LEDGERENTRIES.LIST>
              <LEDGERENTRIES.LIST><LEDGERNAME>Round Off</LEDGERNAME><AMOUNT>0.00</AMOUNT></LEDGERENTRIES.LIST>
             </VOUCHER>
            </TALLYMESSAGE>
            <TALLYMESSAGE>
             <VOUCHER VCHTYPE="Receipt"><DATE>20261021</DATE><GUID>guid-receipt-1</GUID><VOUCHERTYPENAME>Receipt</VOUCHERTYPENAME><VOUCHERNUMBER>R1</VOUCHERNUMBER>
              <LEDGERENTRIES.LIST><LEDGERNAME>Sharma Interiors</LEDGERNAME><AMOUNT>10000.00</AMOUNT></LEDGERENTRIES.LIST></VOUCHER>
            </TALLYMESSAGE>
            </REQUESTDATA></IMPORTDATA></BODY></ENVELOPE>
            XML;
    }

    protected function import(string $xml)
    {
        return $this->actingAs(User::factory()->admin()->create())
            ->post(route('tally.upload'), ['file' => UploadedFile::fake()->createWithContent('daybook.xml', $xml)]);
    }

    public function test_parser_reads_gst_invoice(): void
    {
        $vouchers = app(TallyXmlParser::class)->parse($this->invoiceXml());

        $this->assertCount(2, $vouchers);
        $invoice = $vouchers[0];
        $this->assertSame('guid-sales-0001', $invoice['guid']);
        $this->assertSame('INV/26-27/045', $invoice['number']);
        $this->assertSame('2026-10-20', $invoice['date']->toDateString());
        $this->assertSame(55460.0, $invoice['total']);
        $this->assertSame(8460.0, $invoice['tax']);
        $this->assertSame("12 MG Road\nPune", $invoice['address']);
        $this->assertSame(['name' => 'Teak Sofa 3 Seater', 'quantity' => 2.0, 'unit' => 'Nos', 'rate' => 15000.0, 'amount' => 27000.0, 'discount' => 10.0, 'tax_rate' => 18.0, 'godown' => 'Main Showroom'], $invoice['items'][0]);
        $this->assertNull($invoice['items'][1]['tax_rate']);
    }

    public function test_upload_creates_customer_and_order_matching_tally_total(): void
    {
        $sofa = Product::factory()->create(['name' => 'Sofa', 'tally_name' => 'Teak Sofa 3 Seater']);
        $sofa->stocks()->create(['warehouse_id' => $this->showroom->id, 'quantity' => 5]);

        $this->import($this->invoiceXml())->assertSessionHas('success');

        $order = SalesOrder::with('items', 'customer')->sole();
        $this->assertSame('INV/26-27/045', $order->tally_voucher_number);
        $this->assertSame('confirmed', $order->status);
        $this->assertEquals(55460.00, $order->total);
        $this->assertEquals(8460.00, $order->tax_total);
        $this->assertSame($sofa->id, $order->items[0]->product_id);
        $this->assertEquals(10, $order->items[0]->discount_percent);
        $this->assertNull($order->items[1]->product_id);

        $this->assertSame('Sharma Interiors', $order->customer->name);
        $this->assertSame('27ABCDE1234F1Z5', $order->customer->tax_number);
        $this->assertSame('tally', $order->customer->source);

        $this->assertTrue($order->stock_deducted);
        $this->assertSame(3, $sofa->fresh()->total_stock);
    }

    public function test_reimporting_same_voucher_changes_nothing(): void
    {
        $this->import($this->invoiceXml());
        $this->import($this->invoiceXml());

        $this->assertSame(1, SalesOrder::count());
        $this->assertSame(1, Customer::count());
    }

    public function test_edited_voucher_resyncs_lines_and_stock(): void
    {
        $sofa = Product::factory()->create(['tally_name' => 'Teak Sofa 3 Seater']);
        $sofa->stocks()->create(['warehouse_id' => $this->showroom->id, 'quantity' => 5]);

        $this->import($this->invoiceXml());
        $this->import($this->invoiceXml(alterId: 150, sofaQty: '3 Nos'));

        $order = SalesOrder::with('items')->sole();
        $this->assertSame(150, (int) $order->tally_alter_id);
        $this->assertSame(3, $order->items[0]->quantity);
        $this->assertSame(2, $sofa->fresh()->total_stock);
    }

    public function test_cancelled_voucher_cancels_order_and_returns_stock(): void
    {
        $sofa = Product::factory()->create(['tally_name' => 'Teak Sofa 3 Seater']);
        $sofa->stocks()->create(['warehouse_id' => $this->showroom->id, 'quantity' => 5]);

        $this->import($this->invoiceXml());
        $this->import($this->invoiceXml(alterId: 160, cancelled: 'Yes'));

        $this->assertSame('cancelled', SalesOrder::sole()->status);
        $this->assertSame(5, $sofa->fresh()->total_stock);
    }

    public function test_sale_is_imported_even_without_enough_stock(): void
    {
        $sofa = Product::factory()->create(['tally_name' => 'Teak Sofa 3 Seater']);

        $this->import($this->invoiceXml())->assertSessionHas('success');

        $this->assertSame(-2, $sofa->fresh()->total_stock);
    }

    public function test_mapping_an_unmatched_item_links_existing_lines(): void
    {
        $bed = Product::factory()->create(['name' => 'Walnut King Bed']);
        $this->import($this->invoiceXml());

        $this->post(route('tally.map-item'), ['name' => 'King Bed Walnut', 'product_id' => $bed->id])->assertSessionHas('success');

        $this->assertSame('King Bed Walnut', $bed->fresh()->tally_name);
        $this->assertSame($bed->id, SalesOrder::sole()->items()->where('description', 'King Bed Walnut')->value('product_id'));
    }

    public function test_push_endpoint_requires_token_and_imports(): void
    {
        $this->call('POST', '/api/webhooks/tally/wrong', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $this->invoiceXml())->assertUnauthorized();

        $this->call('POST', '/api/webhooks/tally/test-token', [], [], [], ['CONTENT_TYPE' => 'text/xml'], $this->invoiceXml())
            ->assertOk()
            ->assertJson(['success' => true, 'created' => 1, 'skipped' => 1]);

        $this->get('/api/webhooks/tally/request/test-token')->assertOk()->assertSee('<ID>Day Book</ID>', false);
    }

    public function test_pull_sync_fetches_from_tally_server(): void
    {
        Setting::set('tally_mode', 'pull');
        Setting::set('tally_url', 'http://192.168.1.20:9000');
        Http::fake(['192.168.1.20:9000*' => Http::response(mb_convert_encoding($this->invoiceXml(), 'UTF-16LE', 'UTF-8'))]);

        $this->artisan('crm:tally-sync')->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->body(), 'Day Book'));
        $this->assertSame(1, SalesOrder::count());
    }

    public function test_tally_page_renders_for_managers_only(): void
    {
        $this->actingAs(User::factory()->role('manager')->create())->get(route('tally.index'))->assertOk();
        $this->actingAs(User::factory()->role('sales')->create())->get(route('tally.index'))->assertForbidden();
    }
}
