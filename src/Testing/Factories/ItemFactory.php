<?php

declare(strict_types=1);

namespace SapB1\Testing\Factories;

class ItemFactory extends SapB1Factory
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
     * Define the default attributes for an Item.
     *
     * @return array<string, mixed>
     */
    protected function definition(): array
    {
        $itemCode = 'ITEM-'.$this->randomString(6);

        return [
            'ItemCode' => $itemCode,
            'ItemName' => 'Product '.$this->randomString(8),
            'ItemType' => 'itItems',
            'ItemsGroupCode' => 100,
            'BarCode' => (string) $this->randomInt(1000000000000, 9999999999999),
            'PurchaseItem' => 'tYES',
            'SalesItem' => 'tYES',
            'InventoryItem' => 'tYES',
            'Valid' => 'tYES',
            'Frozen' => 'tNO',
            'ManageSerialNumbers' => 'tNO',
            'ManageBatchNumbers' => 'tNO',
        ];
    }

    /**
     * Configure as a service item.
     */
    public function service(): static
    {
        return $this->state([
            'ItemType' => 'itService',
            'InventoryItem' => 'tNO',
        ]);
    }

    /**
     * Configure as purchase only.
     */
    public function purchaseOnly(): static
    {
        return $this->state([
            'PurchaseItem' => 'tYES',
            'SalesItem' => 'tNO',
        ]);
    }

    /**
     * Configure as sales only.
     */
    public function salesOnly(): static
    {
        return $this->state([
            'PurchaseItem' => 'tNO',
            'SalesItem' => 'tYES',
        ]);
    }

    /**
     * Configure with serial number management.
     */
    public function withSerialNumbers(): static
    {
        return $this->state([
            'ManageSerialNumbers' => 'tYES',
        ]);
    }

    /**
     * Configure with batch number management.
     */
    public function withBatchNumbers(): static
    {
        return $this->state([
            'ManageBatchNumbers' => 'tYES',
        ]);
    }

    /**
     * Configure as frozen.
     */
    public function frozen(): static
    {
        return $this->state([
            'Frozen' => 'tYES',
        ]);
    }

    /**
     * Configure as inactive.
     */
    public function inactive(): static
    {
        return $this->state([
            'Valid' => 'tNO',
        ]);
    }

    /**
     * Configure with price.
     */
    public function withPrice(float $price, string $priceList = '1'): static
    {
        return $this->state([
            'ItemPrices' => [
                [
                    'PriceList' => $priceList,
                    'Price' => $price,
                    'Currency' => 'TRY',
                ],
            ],
        ]);
    }

    /**
     * Configure with warehouse quantity.
     */
    public function withQuantity(float $quantity, string $warehouse = '01'): static
    {
        return $this->state([
            'ItemWarehouseInfoCollection' => [
                [
                    'WarehouseCode' => $warehouse,
                    'InStock' => $quantity,
                ],
            ],
        ]);
    }
}
