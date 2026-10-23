<?php

namespace Tests\Feature;

use App\Modules\Integrations\Contracts\SheetsTransport;
use App\Modules\Integrations\Exceptions\DeliveryNotDispatched;
use App\Modules\Integrations\Exceptions\DeliveryUncertain;
use App\Modules\Integrations\Models\IntegrationOutbox;
use App\Modules\Integrations\Services\DeliverOutbox;
use App\Modules\Integrations\Transports\GoogleSheetsTransport;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class IntegrationOutboxTest extends TestCase
{
    private function event(array $overrides = []): IntegrationOutbox
    {
        return IntegrationOutbox::create(array_merge([
            'submission_key' => (string) Str::uuid(), 'payload_hash' => str_repeat('a', 64),
            'event_type' => 'marklist.public.created', 'aggregate_id' => 123,
            'payload' => ['schema' => 'marklist.v1', 'range' => 'MARKLIST!A:L', 'values' => [['synthetic']]],
            'status' => 'pending', 'available_at' => now(),
        ], $overrides));
    }

    public function test_successful_append_is_sent_only_once(): void
    {
        $event = $this->event();
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldReceive('append')->once()->with($event->payload)->andReturn('MARKLIST!A2:L2');
        $delivery = new DeliverOutbox($transport);
        $this->assertSame('sent', $delivery->deliver($event->id));
        $this->assertSame('skipped', $delivery->deliver($event->id));
        $event->refresh();
        $this->assertSame('sent', $event->status);
        $this->assertSame('MARKLIST!A2:L2', $event->provider_receipt);
        $this->assertSame(1, $event->attempts);
    }

    public function test_ambiguous_append_is_quarantined_and_never_blindly_retried(): void
    {
        $event = $this->event();
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldReceive('append')->once()->andThrow(new DeliveryUncertain('Synthetic timeout after remote commit'));
        $delivery = new DeliverOutbox($transport);
        $this->assertSame('uncertain', $delivery->deliver($event->id));
        $this->assertSame('skipped', $delivery->deliver($event->id));
        $this->assertSame(['sent' => 0, 'pending' => 0, 'uncertain' => 0], $delivery->run());
        $this->assertDatabaseHas('integration_outbox', ['id' => $event->id, 'status' => 'uncertain', 'last_error_code' => 'remote_append_outcome_unknown']);
    }

    public function test_only_a_proven_not_dispatched_failure_can_return_to_pending(): void
    {
        $event = $this->event();
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldReceive('append')->once()->andThrow(new DeliveryNotDispatched('Synthetic missing configuration'));
        $delivery = new DeliverOutbox($transport);
        $this->assertSame('pending', $delivery->deliver($event->id));
        $this->assertSame('skipped', $delivery->deliver($event->id));
        $event->refresh();
        $this->assertTrue($event->available_at->isFuture());
        $this->assertNull($event->claim_token);
        $this->assertSame('not_dispatched', $event->last_error_code);
    }

    public function test_crashed_or_stale_claim_becomes_uncertain_without_dispatch(): void
    {
        $event = $this->event(['status' => 'sending', 'claim_token' => (string) Str::uuid(), 'claimed_at' => now()->subHour()]);
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldNotReceive('append');
        $this->assertSame(['sent' => 0, 'pending' => 0, 'uncertain' => 1], (new DeliverOutbox($transport))->run());
        $this->assertDatabaseHas('integration_outbox', ['id' => $event->id, 'status' => 'uncertain', 'last_error_code' => 'stale_claim_outcome_unknown']);
    }

    public function test_second_worker_cannot_claim_an_inflight_event(): void
    {
        $event = $this->event();
        $transport = Mockery::mock(SheetsTransport::class);
        $delivery = new DeliverOutbox($transport);
        $transport->shouldReceive('append')->once()->andReturnUsing(function () use ($delivery, $event) {
            $this->assertSame('skipped', $delivery->deliver($event->id));
            return 'MARKLIST!A2:L2';
        });
        $this->assertSame('sent', $delivery->deliver($event->id));
        $this->assertSame(1, $event->fresh()->attempts);
    }

    public function test_disabled_command_does_not_dispatch_any_event(): void
    {
        $this->event();
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldNotReceive('append');
        $this->app->instance(SheetsTransport::class, $transport);
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('integration:deliver-outbox'));
        $this->assertStringContainsString('Sheets delivery is disabled. No events were dispatched.', \Illuminate\Support\Facades\Artisan::output());
        $this->assertDatabaseHas('integration_outbox', ['status' => 'pending', 'attempts' => 0]);
    }

    public function test_unconfigured_real_transport_fails_before_any_network_attempt(): void
    {
        config(['integrations.sheets.enabled' => false]);
        $this->expectException(DeliveryNotDispatched::class);
        (new GoogleSheetsTransport())->append(['schema' => 'marklist.v1']);
    }
    public function test_a_late_worker_cannot_overwrite_an_uncertain_claim(): void
    {
        $event = $this->event();
        $transport = Mockery::mock(SheetsTransport::class);
        $transport->shouldReceive('append')->once()->andReturnUsing(function () use ($event) {
            IntegrationOutbox::whereKey($event->id)->update(['status' => 'uncertain', 'last_error_code' => 'manual_review']);
            return 'MARKLIST!A2:L2';
        });
        $this->assertSame('uncertain', (new DeliverOutbox($transport))->deliver($event->id));
        $this->assertDatabaseHas('integration_outbox', ['id' => $event->id, 'status' => 'uncertain', 'last_error_code' => 'manual_review']);
    }

}
