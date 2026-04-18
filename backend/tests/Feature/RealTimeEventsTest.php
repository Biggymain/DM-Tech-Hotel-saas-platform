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
        $orderData = ['id' => 1, 'total' => 150.00];

        $event = new OrderCreatedBroadcast($hotelId, $orderData);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-hotel.99.orders', $channels[0]->name);
        $this->assertEquals('private-hotel.99.kitchen', $channels[1]->name);
        $this->assertEquals('presence-presence-hotel.99.staff', $channels[2]->name);
        $this->assertEquals($orderData, $event->orderData);
    }

    #[Test]
    public function test_kitchen_status_updated_broadcasts_correctly()
    {
        $hotelId = 42;
        $ticketData = ['ticket_id' => 5, 'status' => 'preparing'];

        $event = new KitchenStatusUpdatedBroadcast($hotelId, $ticketData);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-hotel.42.orders', $channels[0]->name);
        $this->assertEquals('private-hotel.42.kitchen', $channels[1]->name);
        $this->assertEquals('presence-presence-hotel.42.staff', $channels[2]->name);
    }

    #[Test]
    public function test_invoice_paid_broadcasts_correctly()
    {
        $hotelId = 7;
        $invoiceData = ['invoice_id' => 10, 'amount_paid' => 500.00];

        $event = new InvoicePaidBroadcast($hotelId, $invoiceData);
        $channels = $event->broadcastOn();

        $this->assertCount(2, $channels);
        $this->assertEquals('private-hotel.7.billing', $channels[0]->name);
        $this->assertEquals('presence-presence-hotel.7.staff', $channels[1]->name);
    }

    #[Test]
    public function test_low_inventory_broadcasts_correctly()
    {
        $hotelId = 12;
        $inventoryData = ['item' => 'Tomatoes', 'stock' => 5];

        $event = new LowInventoryBroadcast($hotelId, $inventoryData);
        $channels = $event->broadcastOn();

        $this->assertCount(3, $channels);
        $this->assertEquals('private-hotel.12.inventory', $channels[0]->name);
        $this->assertEquals('private-hotel.12.notifications', $channels[1]->name);
        $this->assertEquals('presence-presence-hotel.12.staff', $channels[2]->name);
    }
}
