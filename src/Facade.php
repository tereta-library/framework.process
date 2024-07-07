<?php declare(strict_types=1);

namespace Framework\Process;

use Framework\Process\Api as ProcessApi;
use Exception;
use Framework\Process\System as ProcessSystem;

/**
 * Class Framework\Process\Facade
 */
class Facade
{
    /**
     * @var string
     */
    private static string $pidDirectory = '/tmp/pids/';

    /**
     * @param string $directory
     * @return void
     */
    public static function setPidDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0770, true);
        }
        self::$pidDirectory = $directory;
    }

    /**
     * @param string $class
     * @param string $method
     * @param array $arguments
     * @return Api
     * @throws Exception
     */
    public static function runSystem(string $class, string $method, array $arguments = [])
    {
        $systemRunScript = ProcessSystem::getClassFile();

        $error = null;

        $arguments = base64_encode(json_encode([
            'class' => $class,
            'method' => $method,
            'arguments' => serialize($arguments),
            'pidDirectory' => self::$pidDirectory
        ]));
        $command = "php $systemRunScript $arguments";

        $descriptorSpec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"]  // stderr
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            $output = fgets($pipes[0]);
            throw new Exception("Can not run the ${class}->{$method}(...) process: ${output}");
        }

        $initialDataEncoded = '';

        // Wait until the initial data is available
        while ($chunk = fgets($pipes[1])) {
            $initialDataEncoded .= $chunk;
            if (strpos($initialDataEncoded, "\n") !== false) {
                break;
            }
        }

        $status = proc_get_status($process);
        $pidStatus = $status['pid'];

        stream_set_blocking($pipes[1], false); // Set non-blocking mode

        if (!$initialDataEncoded) {
            $error = fgets($pipes[2]);
        }

        $initialData = json_decode(base64_decode(trim($initialDataEncoded)), true);

        fclose($pipes[0]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        if (!$initialData && !$error && $initialDataEncoded) {
            $error = $initialDataEncoded;
        }

        if (!$initialData) {
            throw new Exception("Can not run the ${class}->{$method}(...)\n  Process: ${command}\n  Error:  $error");
        }

        $pid = $initialData['pid'];
        $token = $initialData['token'];
        try {
            $api = (new ProcessApi(self::$pidDirectory))->load($pid, $token);
        } catch (Exception $e) {
            throw new Exception("Can not run the ${class}->{$method}(...)\n  Process: {$command}\n  Message: {$e->getMessage()}");
        }

        return $api;
    }

    /**
     * @param callable $callable
     * @return ProcessApi
     * @throws Exception
     */
    public static function runFork(callable $callable): ProcessApi
    {
        $token = (string) rand(10000000000, 99999999999);
        $size = 254;
        $pid = pcntl_fork();
        if ($pid == -1) {
            throw new Exception('Child process can not be created');
        } else if (!$pid) {
            // Child process
            $api = (new ProcessApi(self::$pidDirectory, $size))->create(getmypid(), $token);
            $callable($api);
        } else {
            // Parent process
            $api = (new ProcessApi(self::$pidDirectory, $size))->load($pid, $token);

            pcntl_waitpid($pid, $status);
            echo "Процесс с PID $pid завершен\n";
        }
        return $api;
    }

    /**
     * @param int|string $pid
     * @param string $token
     * @return Api
     * @throws Exception
     */
    public static function get(int|string $pid, string $token): ProcessApi
    {
        return (new ProcessApi(self::$pidDirectory))->load((int) $pid, $token);
    }
}