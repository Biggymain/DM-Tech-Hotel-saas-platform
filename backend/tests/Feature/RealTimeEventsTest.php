<?php

namespace Tests\Feature;

use App\Events\OrderCreatedBroadcast;
use App\Events\KitchenStatusUpdatedBroadcast;
use App\Events\InvoicePaidBroadcast;
use App\Events\LowInventoryBroadcast;
use App\Models\Hotel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RealTimeEventsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_order_created_broadcasts_to_correct_tenant_channels()
    {
        $hotelId = 99;
        $branchId = 1;
        $orderData = ['id' => 1, 'total' => 150.00];

        $event = new OrderCreatedBroadcast($hotelId, $branchId, $orderData);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-hotel.99.branch.1.orders', $channels[0]->name);
        $this->assertEquals('private-hotel.99.branch.1.kitchen', $channels[1]->name);
        $this->assertEquals('presence-presence-hotel.99.branch.1.staff', $channels[2]->name);
        $this->assertEquals($orderData, $event->orderData);
    }

    #[Test]
    public function test_kitchen_status_updated_broadcasts_correctly()
    {
        $hotelId = 42;
        $branchId = 2;
        $ticketData = ['ticket_id' => 5, 'status' => 'preparing'];

        $event = new KitchenStatusUpdatedBroadcast($hotelId, $branchId, $ticketData);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-hotel.42.branch.2.orders', $channels[0]->name);
        $this->assertEquals('private-hotel.42.branch.2.kitchen', $channels[1]->name);
        $this->assertEquals('presence-presence-hotel.42.branch.2.staff', $channels[2]->name);
    }

    #[Test]
    public function test_invoice_paid_broadcasts_correctly()
    {
        $hotelId = 7;
        $branchId = 3;
        $invoiceData = ['invoice_id' => 10, 'amount_paid' => 500.00];

        $event = new InvoicePaidBroadcast($hotelId, $branchId, $invoiceData);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals('private-hotel.7.branch.3.billing', $channels[0]->name);
        $this->assertEquals('presence-presence-hotel.7.branch.3.staff', $channels[1]->name);
    }

    #[Test]
    public function test_low_inventory_broadcasts_correctly()
    {
        $hotelId = 12;
        $branchId = 4;
        $inventoryData = ['item' => 'Tomatoes', 'stock' => 5];

        $event = new LowInventoryBroadcast($hotelId, $branchId, $inventoryData);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-hotel.12.branch.4.inventory', $channels[0]->name);
        $this->assertEquals('private-hotel.12.branch.4.notifications', $channels[1]->name);
        $this->assertEquals('presence-presence-hotel.12.branch.4.staff', $channels[2]->name);
    }
}
