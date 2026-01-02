<?php

declare(strict_types=1);

namespace SapB1\Testing;

use Closure;
use SapB1\Client\Response;

/**
 * Trait for testing SAP B1 interactions.
 *
 * Use this trait in your test classes to easily fake SAP B1 requests.
 *
 * @example
 * ```php
 * class BusinessPartnerTest extends TestCase
 * {
 *     use SapB1Fake;
 *
 *     public function test_can_get_business_partners(): void
 *     {
 *         $this->fakeSapB1([
 *             'BusinessPartners' => FakeResponse::collection([
 *                 ['CardCode' => 'C001', 'CardName' => 'Test Customer'],
 *             ]),
 *         ]);
 *
 *         // Your test code here...
 *
 *         $this->assertSapB1Get('BusinessPartners');
 *     }
 * }
 * ```
 */
trait SapB1Fake
{
    protected ?FakeSapB1 $sapB1Fake = null;

    /**
     * Fake SAP B1 requests.
     *
     * @param  array<string, Response|Closure|array<string, mixed>>|Closure|null  $responses
     */
    protected function fakeSapB1(array|Closure|null $responses = null): FakeSapB1
    {
        $this->sapB1Fake = FakeSapB1::fake($responses);

        return $this->sapB1Fake;
    }

    /**
     * Get the SAP B1 fake instance.
     */
    protected function sapB1Fake(): ?FakeSapB1
    {
        return $this->sapB1Fake;
    }

    /**
     * Assert that a SAP B1 request was sent.
     */
    protected function assertSapB1Sent(string $endpoint, ?Closure $callback = null): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertSent($endpoint, $callback);

        return $this;
    }

    /**
     * Assert that a SAP B1 request was not sent.
     */
    protected function assertSapB1NotSent(string $endpoint, ?Closure $callback = null): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertNotSent($endpoint, $callback);

        return $this;
    }

    /**
     * Assert that no SAP B1 requests were sent.
     */
    protected function assertSapB1NothingSent(): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertNothingSent();

        return $this;
    }

    /**
     * Assert the number of SAP B1 requests sent.
     */
    protected function assertSapB1SentCount(int $count): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertSentCount($count);

        return $this;
    }

    /**
     * Assert a GET request was sent to SAP B1.
     */
    protected function assertSapB1Get(string $endpoint): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertGet($endpoint);

        return $this;
    }

    /**
     * Assert a POST request was sent to SAP B1.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function assertSapB1Post(string $endpoint, ?array $data = null): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertPost($endpoint, $data);

        return $this;
    }

    /**
     * Assert a PATCH request was sent to SAP B1.
     *
     * @param  array<string, mixed>|null  $data
     */
    protected function assertSapB1Patch(string $endpoint, ?array $data = null): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertPatch($endpoint, $data);

        return $this;
    }

    /**
     * Assert a DELETE request was sent to SAP B1.
     */
    protected function assertSapB1Delete(string $endpoint): self
    {
        $this->ensureFakeExists();
        $this->sapB1Fake?->assertDelete($endpoint);

        return $this;
    }

    /**
     * Ensure a fake instance exists.
     */
    private function ensureFakeExists(): void
    {
        if ($this->sapB1Fake === null) {
            throw new \RuntimeException('SAP B1 fake not initialized. Call fakeSapB1() first.');
        }
    }
}
