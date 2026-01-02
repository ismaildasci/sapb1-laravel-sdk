<?php

declare(strict_types=1);

namespace SapB1\Testing\Factories;

abstract class SapB1Factory
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    /**
     * @var array<string, mixed>
     */
    protected array $state = [];

    protected int $count = 1;

    /**
     * Create a new factory instance.
     */
    abstract public static function new(): static;

    /**
     * Define the default attributes for the entity.
     *
     * @return array<string, mixed>
     */
    abstract protected function definition(): array;

    /**
     * Set the number of entities to create.
     */
    public function count(int $count): static
    {
        $clone = clone $this;
        $clone->count = $count;

        return $clone;
    }

    /**
     * Apply a state transformation.
     *
     * @param  array<string, mixed>  $state
     */
    public function state(array $state): static
    {
        $clone = clone $this;
        $clone->state = array_merge($clone->state, $state);

        return $clone;
    }

    /**
     * Set specific attributes.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function attributes(array $attributes): static
    {
        $clone = clone $this;
        $clone->attributes = array_merge($clone->attributes, $attributes);

        return $clone;
    }

    /**
     * Create a single entity array.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function make(array $attributes = []): array
    {
        return array_merge(
            $this->definition(),
            $this->state,
            $this->attributes,
            $attributes
        );
    }

    /**
     * Create multiple entity arrays.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<int, array<string, mixed>>
     */
    public function makeMany(array $attributes = []): array
    {
        $entities = [];

        for ($i = 0; $i < $this->count; $i++) {
            $entities[] = $this->make(array_merge($attributes, [
                '_index' => $i,
            ]));
        }

        // Remove _index from final output
        return array_map(function (array $entity): array {
            unset($entity['_index']);

            return $entity;
        }, $entities);
    }

    /**
     * Generate a random string.
     */
    protected function randomString(int $length = 10): string
    {
        return substr(str_shuffle(str_repeat('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', $length)), 0, $length);
    }

    /**
     * Generate a random integer.
     */
    protected function randomInt(int $min = 1, int $max = 10000): int
    {
        return random_int($min, $max);
    }

    /**
     * Generate a random float.
     */
    protected function randomFloat(float $min = 0, float $max = 10000, int $decimals = 2): float
    {
        $multiplier = 10 ** $decimals;

        return random_int((int) ($min * $multiplier), (int) ($max * $multiplier)) / $multiplier;
    }

    /**
     * Generate a random date string.
     */
    protected function randomDate(string $startDate = '-1 year', string $endDate = 'now'): string
    {
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        if ($start === false) {
            $start = strtotime('-1 year');
        }

        if ($end === false) {
            $end = time();
        }

        /** @var int $start */
        /** @var int $end */
        $timestamp = random_int($start, $end);

        return date('Y-m-d', $timestamp);
    }

    /**
     * Pick a random element from an array.
     *
     * @template T
     *
     * @param  array<int, T>  $items
     * @return T
     */
    protected function randomElement(array $items): mixed
    {
        return $items[array_rand($items)];
    }
}
