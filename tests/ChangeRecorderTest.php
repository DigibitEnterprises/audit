<?php declare(strict_types=1);

namespace Digibit\Audit\Tests;

use Digibit\Audit\Diff\ChangeAction;
use Digibit\Audit\Tests\Fixtures\Order;
use Digibit\Audit\AuditEntry;
use Digibit\Audit\AuditStore;
use Digibit\Audit\ChangeRecorder;
use PHPUnit\Framework\TestCase;

final class ChangeRecorderTest extends TestCase
{
    private RecordingAuditStore $store;
    private ChangeRecorder $recorder;

    protected function setUp(): void
    {
        $this->store    = new RecordingAuditStore();
        $this->recorder = new ChangeRecorder($this->store);
    }

    public function test_persists_entries_when_mutation_returns_truthy_and_produces_changes(): void
    {
        $order = new Order(status: 'pending');

        $result = $this->recorder->record($order, function () use ($order) {
            $order->status = 'shipped';
            return true;
        });

        self::assertTrue($result->changed);
        self::assertCount(1, $this->store->batches);
        $entries = $this->store->batches[0];
        self::assertCount(1, $entries);
        self::assertSame('status', $entries[0]->path);
        self::assertSame(ChangeAction::Modified, $entries[0]->action);
        self::assertSame('pending', $entries[0]->oldValue);
        self::assertSame('shipped', $entries[0]->newValue);
    }

    public function test_skips_persist_when_mutation_returns_falsy(): void
    {
        $order = new Order(status: 'pending');

        $result = $this->recorder->record($order, function () use ($order) {
            $order->status = 'shipped';
            return false;
        });

        self::assertTrue($result->aborted);
        self::assertCount(0, $this->store->batches);
    }

    public function test_skips_persist_when_mutation_produces_no_changes(): void
    {
        $order = new Order(status: 'pending');

        $this->recorder->record($order, fn() => true);

        self::assertCount(0, $this->store->batches);
    }

    public function test_string_signal_aborts_with_reason(): void
    {
        $order = new Order(status: 'pending');

        $result = $this->recorder->record($order, fn() => 'some-value');

        self::assertTrue($result->aborted);
        self::assertSame('some-value', $result->error);
    }

    public function test_record_outside_transaction_gets_its_own_id(): void
    {
        $order = new Order(status: 'pending');

        $this->recorder->record($order, function () use ($order) { $order->status = 'a'; return true; });
        $this->recorder->record($order, function () use ($order) { $order->status = 'b'; return true; });

        self::assertCount(2, $this->store->batches);
        // each standalone record() gets a unique transaction ID
        self::assertNotSame(
            $this->store->batches[0][0]->transactionId,
            $this->store->batches[1][0]->transactionId,
        );
    }

    public function test_record_calls_inside_transaction_share_one_id(): void
    {
        $a = new Order(status: 'pending');
        $b = new Order(status: 'pending');

        $this->recorder->transaction(function () use ($a, $b) {
            $this->recorder->record($a, function () use ($a) { $a->status = 'shipped'; return true; });
            $this->recorder->record($b, function () use ($b) { $b->status = 'cancelled'; return true; });
        });

        self::assertCount(2, $this->store->batches);
        self::assertSame(
            $this->store->batches[0][0]->transactionId,
            $this->store->batches[1][0]->transactionId,
        );
    }

    public function test_nested_transactions_get_independent_ids(): void
    {
        $a = new Order(status: 'pending');
        $b = new Order(status: 'pending');

        $this->recorder->transaction(function () use ($a, $b) {
            $this->recorder->record($a, function () use ($a) { $a->status = 'shipped'; return true; });
            $this->recorder->transaction(function () use ($b) {
                $this->recorder->record($b, function () use ($b) { $b->status = 'cancelled'; return true; });
            });
        });

        self::assertCount(2, $this->store->batches);
        self::assertNotSame(
            $this->store->batches[0][0]->transactionId,
            $this->store->batches[1][0]->transactionId,
        );
    }

    public function test_record_event_persists_an_entry_with_no_before_after_value(): void
    {
        $order = new Order(status: 'pending');

        $this->recorder->recordEvent($order, 'secret_rotated');

        self::assertCount(1, $this->store->batches);
        $entries = $this->store->batches[0];
        self::assertCount(1, $entries);
        self::assertSame('secret_rotated', $entries[0]->path);
        self::assertSame(ChangeAction::Occurred, $entries[0]->action);
        self::assertNull($entries[0]->oldValue);
        self::assertNull($entries[0]->newValue);
    }

    public function test_record_event_does_not_snapshot_or_diff_the_entity(): void
    {
        $order = new Order(status: 'pending');
        $order->status = 'mutated-but-should-not-be-captured';

        $this->recorder->recordEvent($order, 'secret_rotated');

        self::assertCount(1, $this->store->batches[0]);
        self::assertNull($this->store->batches[0][0]->oldValue);
        self::assertNull($this->store->batches[0][0]->newValue);
    }

    public function test_record_event_inside_transaction_shares_its_id_and_context(): void
    {
        $order = new Order(status: 'pending');

        $this->recorder->transaction(function () use ($order) {
            $this->recorder->recordEvent($order, 'secret_rotated');
        }, context: ['actor_id' => 42]);

        self::assertSame(['actor_id' => 42], $this->store->batches[0][0]->context);
    }

    public function test_record_event_context_is_merged_over_transaction_context(): void
    {
        $order = new Order(status: 'pending');

        $this->recorder->transaction(function () use ($order) {
            $this->recorder->recordEvent($order, 'secret_rotated', context: ['actor_id' => 99]);
        }, context: ['actor_id' => 42, 'source' => 'web_ui']);

        self::assertSame(
            ['actor_id' => 99, 'source' => 'web_ui'],
            $this->store->batches[0][0]->context,
        );
    }

    public function test_transaction_id_is_cleared_after_transaction_completes(): void
    {
        $order = new Order(status: 'pending');
        $capturedId = null;

        $this->recorder->transaction(function () use ($order, &$capturedId) {
            $this->recorder->record($order, function () use ($order, &$capturedId) {
                $order->status = 'shipped';
                return true;
            });
            $capturedId = $this->store->batches[0][0]->transactionId ?? null;
        });

        // record() after the transaction should get a fresh ID, not the old one
        $order->status = 'pending';
        $this->recorder->record($order, function () use ($order) {
            $order->status = 'returned';
            return true;
        });

        self::assertNotNull($capturedId);
        self::assertNotSame($capturedId, $this->store->batches[1][0]->transactionId);
    }
}

final class RecordingAuditStore implements AuditStore
{
    /** @var list<list<AuditEntry>> */
    public array $batches = [];

    public function persist(array $entries): void
    {
        $this->batches[] = $entries;
    }
}
