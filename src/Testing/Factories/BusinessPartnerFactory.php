<?php

declare(strict_types=1);

namespace SapB1\Testing\Factories;

class BusinessPartnerFactory extends SapB1Factory
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
     * Define the default attributes for a BusinessPartner.
     *
     * @return array<string, mixed>
     */
    protected function definition(): array
    {
        $cardCode = 'C'.$this->randomString(6);

        return [
            'CardCode' => $cardCode,
            'CardName' => 'Customer '.$this->randomString(8),
            'CardType' => 'cCustomer',
            'GroupCode' => 100,
            'Phone1' => '+90'.$this->randomInt(5000000000, 5999999999),
            'EmailAddress' => strtolower($this->randomString(8)).'@example.com',
            'Valid' => 'tYES',
            'Frozen' => 'tNO',
            'Currency' => 'TRY',
            'FederalTaxID' => (string) $this->randomInt(10000000000, 99999999999),
        ];
    }

    /**
     * Configure as a supplier.
     */
    public function supplier(): static
    {
        return $this->state([
            'CardCode' => 'V'.$this->randomString(6),
            'CardType' => 'cSupplier',
        ]);
    }

    /**
     * Configure as a lead.
     */
    public function lead(): static
    {
        return $this->state([
            'CardCode' => 'L'.$this->randomString(6),
            'CardType' => 'cLead',
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
     * Configure with address.
     *
     * @param  array<string, mixed>  $address
     */
    public function withAddress(array $address = []): static
    {
        return $this->state([
            'BPAddresses' => [
                array_merge([
                    'AddressName' => 'Main',
                    'AddressType' => 'bo_BillTo',
                    'Street' => 'Test Street '.$this->randomInt(1, 100),
                    'City' => 'Istanbul',
                    'Country' => 'TR',
                ], $address),
            ],
        ]);
    }

    /**
     * Configure with contact person.
     *
     * @param  array<string, mixed>  $contact
     */
    public function withContact(array $contact = []): static
    {
        return $this->state([
            'ContactEmployees' => [
                array_merge([
                    'Name' => 'Contact '.$this->randomString(5),
                    'Phone1' => '+90'.$this->randomInt(5000000000, 5999999999),
                    'E_Mail' => strtolower($this->randomString(8)).'@example.com',
                ], $contact),
            ],
        ]);
    }
}
