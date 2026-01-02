<?php

declare(strict_types=1);

namespace SapB1\Testing\Factories;

class OrderFactory extends SapB1Factory
{
    /**
     * Create a new factory instance.
     */
    public static function new(): static
    {
        /** @var static */
        return new self;
    }

    /**
     * Define the default attributes for an Order.
     *
     * @return array<string, mixed>
     */
    protected function definition(): array
    {
        return [
            'DocEntry' => $this->randomInt(1, 100000),
            'DocNum' => $this->randomInt(1, 100000),
            'CardCode' => 'C'.$this->randomString(6),
            'CardName' => 'Customer '.$this->randomString(8),
            'DocDate' => $this->randomDate('-30 days', 'now'),
            'DocDueDate' => $this->randomDate('now', '+30 days'),
            'DocTotal' => $this->randomFloat(100, 10000),
            'DocCurrency' => 'TRY',
            'DocumentStatus' => 'bost_Open',
            'Cancelled' => 'tNO',
            'Comments' => '',
        ];
    }

    /**
     * Configure as closed.
     */
    public function closed(): static
    {
        return $this->state([
            'DocumentStatus' => 'bost_Close',
        ]);
    }

    /**
     * Configure as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state([
            'Cancelled' => 'tYES',
        ]);
    }

    /**
     * Configure with specific customer.
     */
    public function forCustomer(string $cardCode, string $cardName = ''): static
    {
        return $this->state([
            'CardCode' => $cardCode,
            'CardName' => $cardName ?: 'Customer '.$cardCode,
        ]);
    }

    /**
     * Configure with lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function withLines(array $lines): static
    {
        return $this->state([
            'DocumentLines' => $lines,
        ]);
    }

    /**
     * Configure with a single line.
     *
     * @param  array<string, mixed>  $line
     */
    public function withLine(array $line = []): static
    {
        return $this->state([
            'DocumentLines' => [
                array_merge([
                    'ItemCode' => 'ITEM-'.$this->randomString(6),
                    'ItemDescription' => 'Product '.$this->randomString(8),
                    'Quantity' => $this->randomFloat(1, 100, 0),
                    'UnitPrice' => $this->randomFloat(10, 1000),
                    'WarehouseCode' => '01',
                ], $line),
            ],
        ]);
    }

    /**
     * Add multiple random lines.
     */
    public function withRandomLines(int $count = 3): static
    {
        $lines = [];

        for ($i = 0; $i < $count; $i++) {
            $lines[] = [
                'ItemCode' => 'ITEM-'.$this->randomString(6),
                'ItemDescription' => 'Product '.$this->randomString(8),
                'Quantity' => $this->randomFloat(1, 100, 0),
                'UnitPrice' => $this->randomFloat(10, 1000),
                'WarehouseCode' => '01',
            ];
        }

        return $this->state([
            'DocumentLines' => $lines,
        ]);
    }

    /**
     * Configure with total.
     */
    public function withTotal(float $total): static
    {
        return $this->state([
            'DocTotal' => $total,
        ]);
    }

    /**
     * Configure with dates.
     */
    public function withDates(string $docDate, string $dueDate): static
    {
        return $this->state([
            'DocDate' => $docDate,
            'DocDueDate' => $dueDate,
        ]);
    }
}
