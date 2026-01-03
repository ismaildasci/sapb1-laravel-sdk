<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use SapB1\Session\Drivers\FileSessionDriver;

describe('FileSessionDriver Locking', function (): void {
    beforeEach(function (): void {
        $this->driver = new FileSessionDriver(new Filesystem);
    });

    afterEach(function (): void {
        $this->driver->releaseLock('test_connection');
        $this->driver->flush();
    });

    it('acquires lock successfully', function (): void {
        $result = $this->driver->acquireLock('test_connection');

        expect($result)->toBeTrue();
    });

    it('cannot acquire lock when already held', function (): void {
        $this->driver->acquireLock('test_connection');

        // Try to acquire again - should fail
        $driver2 = new FileSessionDriver(new Filesystem);
        $result = $driver2->acquireLock('test_connection');

        expect($result)->toBeFalse();
    });

    it('releases lock correctly', function (): void {
        $this->driver->acquireLock('test_connection');
        $this->driver->releaseLock('test_connection');

        // Now another process should be able to acquire
        $driver2 = new FileSessionDriver(new Filesystem);
        $result = $driver2->acquireLock('test_connection');
        $driver2->releaseLock('test_connection');

        expect($result)->toBeTrue();
    });

    it('can acquire different connection locks independently', function (): void {
        $result1 = $this->driver->acquireLock('connection_a');
        $result2 = $this->driver->acquireLock('connection_b');

        expect($result1)->toBeTrue();
        expect($result2)->toBeTrue();

        $this->driver->releaseLock('connection_a');
        $this->driver->releaseLock('connection_b');
    });
});
