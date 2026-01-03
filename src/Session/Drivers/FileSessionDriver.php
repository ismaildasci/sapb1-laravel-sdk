<?php

declare(strict_types=1);

namespace SapB1\Session\Drivers;

use Illuminate\Filesystem\Filesystem;
use SapB1\Contracts\SessionStoreInterface;
use SapB1\Session\SessionData;

class FileSessionDriver implements SessionStoreInterface
{
    protected string $path;

    protected string $prefix;

    protected int $refreshThreshold;

    /**
     * Active file lock handles.
     *
     * @var array<string, resource>
     */
    protected array $lockHandles = [];

    public function __construct(
        protected Filesystem $files
    ) {
        $this->path = storage_path('framework/sap-b1-sessions');
        $this->prefix = config('sap-b1.session.prefix', 'sap_b1_session_');
        $this->refreshThreshold = (int) config('sap-b1.session.refresh_threshold', 300);

        $this->ensureDirectoryExists();
    }

    public function get(string $connection): ?SessionData
    {
        $path = $this->getFilePath($connection);

        if (! $this->files->exists($path)) {
            return null;
        }

        $data = $this->files->get($path);
        $session = SessionData::fromJson($data);

        if ($session->isExpired()) {
            $this->forget($connection);

            return null;
        }

        return $session;
    }

    public function put(string $connection, SessionData $session): void
    {
        $this->files->put(
            $this->getFilePath($connection),
            $session->toJson()
        );
    }

    public function forget(string $connection): void
    {
        $path = $this->getFilePath($connection);

        if ($this->files->exists($path)) {
            $this->files->delete($path);
        }

        $lockPath = $this->getLockFilePath($connection);

        if ($this->files->exists($lockPath)) {
            $this->files->delete($lockPath);
        }
    }

    public function has(string $connection): bool
    {
        return $this->files->exists($this->getFilePath($connection));
    }

    public function needsRefresh(string $connection): bool
    {
        $session = $this->get($connection);

        if ($session === null) {
            return true;
        }

        return $session->isNearExpiry($this->refreshThreshold);
    }

    public function acquireLock(string $connection, int $seconds = 10): bool
    {
        $lockPath = $this->getLockFilePath($connection);

        // Use flock for atomic lock acquisition (prevents TOCTOU race condition)
        $handle = @fopen($lockPath, 'c');

        if ($handle === false) {
            return false;
        }

        // Try to acquire exclusive lock without blocking
        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        // Write timestamp for debugging/monitoring
        ftruncate($handle, 0);
        fwrite($handle, (string) time());
        fflush($handle);

        // Store handle for later release
        $this->lockHandles[$connection] = $handle;

        return true;
    }

    public function releaseLock(string $connection): void
    {
        if (isset($this->lockHandles[$connection])) {
            $handle = $this->lockHandles[$connection];

            // Release lock and close handle
            flock($handle, LOCK_UN);
            fclose($handle);

            unset($this->lockHandles[$connection]);
        }

        // Clean up lock file
        $lockPath = $this->getLockFilePath($connection);

        if ($this->files->exists($lockPath)) {
            $this->files->delete($lockPath);
        }
    }

    public function flush(): void
    {
        $files = $this->files->glob($this->path.'/'.$this->prefix.'*');

        foreach ($files as $file) {
            $this->files->delete($file);
        }
    }

    protected function ensureDirectoryExists(): void
    {
        if (! $this->files->isDirectory($this->path)) {
            $this->files->makeDirectory($this->path, 0755, true);
        }
    }

    protected function getFilePath(string $connection): string
    {
        return $this->path.'/'.$this->prefix.$connection.'.json';
    }

    protected function getLockFilePath(string $connection): string
    {
        return $this->path.'/'.$this->prefix.$connection.'.lock';
    }
}
