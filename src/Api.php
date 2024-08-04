<?php declare(strict_types=1);

namespace Framework\Process;

use SysvSharedMemory;
use SysvSemaphore;
use Exception;

/**
 * ···························WWW.TERETA.DEV······························
 * ·······································································
 * : _____                        _                     _                :
 * :|_   _|   ___   _ __    ___  | |_    __ _        __| |   ___  __   __:
 * :  | |    / _ \ | '__|  / _ \ | __|  / _` |      / _` |  / _ \ \ \ / /:
 * :  | |   |  __/ | |    |  __/ | |_  | (_| |  _  | (_| | |  __/  \ V / :
 * :  |_|    \___| |_|     \___|  \__|  \__,_| (_)  \__,_|  \___|   \_/  :
 * ·······································································
 * ·······································································
 *
 * @class Framework\Process\Api
 * @package Framework\Process
 * @link https://tereta.dev
 * @since 2020-2024
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @author Tereta Alexander <tereta.alexander@gmail.com>
 * @copyright 2020-2024 Tereta Alexander
 */
class Api
{
    const DEFAULT_PID_DIRECTORY = '/tmp/pids/';

    /**
     * @var string|null
     */
    private ?string $pidFile = null;

    /**
     * @var int|null
     */
    private ?int $pid = null;

    /**
     * @var array|mixed
     */
    private ?array $data = null;

    /**
     * @var SysvSharedMemory|null
     */
    private ?SysvSharedMemory $shmId = null;

    /**
     * @var SysvSemaphore|null
     */
    private ?SysvSemaphore $semaphore = null;

    /**
     * @param $pidDirectory
     */
    public function __construct(private $pidDirectory = self::DEFAULT_PID_DIRECTORY)
    {
    }

    /**
     * @param int $pid
     * @param string|null $token
     * @return $this
     * @throws Exception
     */
    public function load(int $pid, ?string $token = null): static
    {
        xdebug_break();

        $this->pid = $pid;
        $this->token = $token;
        $this->pidFile = "{$this->pidDirectory}/{$this->pid}.pid";

        $attempts = 0;
        while (!file_exists($this->pidFile)) {
            if ($attempts > 10) {
                throw new Exception("The #{$pid} process descriptor not found");
            }
            $attempts++;
            usleep(5000);
        }

        $pidInfo = json_decode(file_get_contents($this->pidFile), true);
        $size = $pidInfo['size'];

        $key = ftok($this->pidFile, 'a');
        $semaphore = sem_get($key);
        $shmId = shm_attach($key, $size);

        $this->semaphore = $semaphore;
        $this->shmId = $shmId;

        sem_acquire($semaphore);
        $this->data = shm_get_var($shmId, 1);
        sem_release($semaphore);

        return $this;
    }

    /**
     * @param int $pid
     * @param string|null $token
     * @param int $size
     * @return $this
     */
    public function create(int $pid, ?string $token = null, int $size = 1024): static
    {
        $this->pid = $pid;
        $this->token = $token;
        $this->pidFile = "{$this->pidDirectory}/{$this->pid}.pid";

        $pidInfo = [
            'pid' => $this->pid,
            'token' => $token ?? '',
            'size' => $size,
            'createdAt' => date('Y-m-d H:i:s'),
            'createdAtTime' => time(),
        ];

        file_put_contents($this->pidFile, json_encode($pidInfo));

        $key = ftok($this->pidFile, 'a');
        $semaphore = sem_get($key);
        $shmId = shm_attach($key, $size);

        $this->semaphore = $semaphore;
        $this->shmId = $shmId;

        sem_acquire($semaphore);
        if (!shm_has_var($shmId, 1)) {
            shm_put_var($shmId, 1, $this->data);
        } else {
            $this->data = shm_get_var($shmId, 1);
        }
        sem_release($semaphore);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getToken(): ?string
    {
        return $this->token;
    }

    /**
     * @return int
     */
    public function getPid(): int
    {
        return $this->pid;
    }

    /**
     * @return bool
     */
    public function isStopped(): bool
    {
        return !file_exists($this->pidFile);
    }

    /**
     * @return void
     */
    public function stop(): void
    {
        unlink($this->pidFile);
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        sem_acquire($this->semaphore);
        if (shm_has_var($this->shmId, 1)) {
            $this->data = shm_get_var($this->shmId, 1);
        } else {
            $this->data = null;
        }
        sem_release($this->semaphore);

        return $this->data;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function setData(array $data): static
    {
        $this->data = $data;
        sem_acquire($this->semaphore);
        shm_put_var($this->shmId, 1, $this->data);
        sem_release($this->semaphore);

        return $this;
    }

    /**
     * @param string $buffer
     * @return $this
     */
    public function setBuffer(string $buffer): static
    {
        sem_acquire($this->semaphore);
        shm_put_var($this->shmId, 2, $buffer);
        sem_release($this->semaphore);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getBuffer(): ?string
    {
        $buffer = null;
        sem_acquire($this->semaphore);
        if (shm_has_var($this->shmId, 2)) {
            $buffer = shm_get_var($this->shmId, 2);
        }
        sem_release($this->semaphore);

        return $buffer;
    }

    /**
     * @return void
     */
    public function __destruct()
    {
        if ($this->pid == getmypid() && $this->semaphore && $this->shmId && file_exists($this->pidFile)) {
            sem_remove($this->semaphore);
            shm_remove($this->shmId);
            unlink($this->pidFile);
            pcntl_signal_dispatch();
        }
    }
}