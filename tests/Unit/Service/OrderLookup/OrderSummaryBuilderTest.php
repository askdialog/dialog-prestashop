<?php

namespace Dialog\AskDialog\Tests\Unit\Service\OrderLookup;

use Dialog\AskDialog\Service\OrderLookup\OrderSummaryBuilder;
use PHPUnit\Framework\TestCase;

class OrderSummaryBuilderTest extends TestCase
{
    private const CANCELED = 6;
    private const ERROR = 8;
    private const REFUND = 7;
    private const SHIPPED = 4;
    private const DELIVERED = 5;
    private const PAID = 2;

    /** @var string */
    private $timezone;

    protected function setUp(): void
    {
        $this->timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Paris');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->timezone);
    }

    public function testSummarisesAShippedOrderWithItsTracking()
    {
        $summary = $this->builder()->build(
            'XKBKNABJK',
            [$this->order(41, self::SHIPPED, ['paid' => 1, 'shipped' => 1])],
            [41 => ['tracking_number' => '6A 123', 'name' => 'Colissimo', 'url' => 'https://track.example/@?l=fr']],
            [41 => [
                $this->entry(self::PAID, '2026-11-20 10:00:00'),
                $this->entry(self::SHIPPED, '2026-11-21 09:30:00', ['shipped' => 1]),
            ]],
            [41 => [['product_id' => '7', 'product_name' => 'Monstera', 'product_quantity' => '2']]]
        );

        $this->assertSame('XKBKNABJK', $summary['reference']);
        $this->assertSame(12, $summary['idCustomer']);
        $this->assertSame('2026-11-20T10:00:00+01:00', $summary['createdAt']);
        $part = $summary['orders'][0];
        $this->assertSame(
            ['id' => self::SHIPPED, 'paid' => true, 'shipped' => true, 'delivered' => false, 'canceled' => false, 'refund' => false, 'error' => false],
            $part['state']
        );
        $this->assertSame(['shippedAt' => '2026-11-21T09:30:00+01:00', 'deliveredAt' => null, 'canceledAt' => null], $part['dates']);
        $this->assertSame(
            ['name' => 'Colissimo', 'trackingNumber' => '6A 123', 'trackingUrl' => 'https://track.example/6A%20123?l=fr'],
            $part['carrier']
        );
        $this->assertSame([['idProduct' => 7, 'title' => 'Monstera', 'quantity' => 2]], $part['lines']);
    }

    public function testKeepsEachPartOfASplitOrder()
    {
        $summary = $this->builder()->build(
            'XKBKNABJK',
            [
                $this->order(41, self::DELIVERED, ['paid' => 1, 'shipped' => 1]),
                $this->order(42, self::PAID, ['paid' => 1], '2026-11-20 10:00:01'),
            ],
            [41 => ['tracking_number' => '6A', 'name' => 'Colissimo', 'url' => ''], 42 => ['tracking_number' => '', 'name' => 'DPD', 'url' => 'https://dpd/@']],
            [41 => [
                $this->entry(self::SHIPPED, '2026-11-21 09:00:00', ['shipped' => 1]),
                $this->entry(self::DELIVERED, '2026-11-23 15:00:00', ['shipped' => 1]),
            ]],
            []
        );

        $this->assertSame([41, 42], array_column($summary['orders'], 'idOrder'));
        $this->assertTrue($summary['orders'][0]['state']['delivered']);
        $this->assertSame('2026-11-23T15:00:00+01:00', $summary['orders'][0]['dates']['deliveredAt']);
        $this->assertFalse($summary['orders'][1]['state']['delivered']);
        $this->assertNull($summary['orders'][0]['carrier']['trackingUrl']);
        $this->assertSame(['name' => 'DPD', 'trackingNumber' => null, 'trackingUrl' => null], $summary['orders'][1]['carrier']);
        $this->assertSame([], $summary['orders'][1]['lines']);
    }

    public function testDatesTheLatestCancellation()
    {
        $summary = $this->builder()->build(
            'XKBKNABJK',
            [$this->order(41, self::CANCELED)],
            [],
            [41 => [
                $this->entry(self::ERROR, '2026-11-20 10:00:00'),
                $this->entry(self::PAID, '2026-11-20 11:00:00'),
                $this->entry(self::CANCELED, '2026-11-22 08:00:00'),
            ]],
            []
        );

        $part = $summary['orders'][0];
        $this->assertTrue($part['state']['canceled']);
        $this->assertSame('2026-11-22T08:00:00+01:00', $part['dates']['canceledAt']);
        $this->assertNull($part['carrier']);
    }

    public function testDatesNoCancellationOfAnOrderPutBackInTheFlow()
    {
        $summary = $this->builder()->build(
            'XKBKNABJK',
            [$this->order(41, self::SHIPPED, ['paid' => 1, 'shipped' => 1])],
            [],
            [41 => [
                $this->entry(self::CANCELED, '2026-11-20 10:00:00'),
                $this->entry(self::SHIPPED, '2026-11-21 09:30:00', ['shipped' => 1]),
            ]],
            []
        );

        $this->assertNull($summary['orders'][0]['dates']['canceledAt']);
    }

    public function testFlagsARefundAndAPaymentError()
    {
        $summary = $this->builder()->build(
            'XKBKNABJK',
            [$this->order(41, self::REFUND), $this->order(42, self::ERROR)],
            [],
            [],
            []
        );

        $this->assertTrue($summary['orders'][0]['state']['refund']);
        $this->assertTrue($summary['orders'][1]['state']['error']);
        $this->assertFalse($summary['orders'][1]['state']['canceled']);
    }

    private function builder(): OrderSummaryBuilder
    {
        return new OrderSummaryBuilder([
            'PS_OS_DELIVERED' => self::DELIVERED,
            'PS_OS_CANCELED' => self::CANCELED,
            'PS_OS_ERROR' => self::ERROR,
            'PS_OS_REFUND' => self::REFUND,
        ]);
    }

    /**
     * @param array<string, int> $flags
     *
     * @return array<string, mixed>
     */
    private function order(int $idOrder, int $state, array $flags = [], string $dateAdd = '2026-11-20 10:00:00'): array
    {
        return array_merge(
            ['id_order' => (string) $idOrder, 'id_customer' => '12', 'current_state' => (string) $state, 'date_add' => $dateAdd, 'email' => 'jane@example.com'],
            ['paid' => '0', 'shipped' => '0'],
            array_map('strval', $flags)
        );
    }

    /**
     * @param array<string, int> $flags
     *
     * @return array<string, mixed>
     */
    private function entry(int $state, string $date, array $flags = []): array
    {
        return array_merge(
            ['id_order_state' => (string) $state, 'date_add' => $date, 'shipped' => '0'],
            array_map('strval', $flags)
        );
    }
}
