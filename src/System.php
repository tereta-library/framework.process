<?php declare(strict_types=1);

namespace Framework\Process;

use Framework\Process\Api as ProcessApi;
use ReflectionClass;
use ReflectionException;

use Framework\Process\Abstract\System as ProcessAbstract;

/**
 * Class Framework\Process\System
 */
class System
{
    /**
     * @throws ReflectionException
     */
    public static function runProcess(string $config): void
    {
        $token = (string) rand(10000000000, 99999999999);
        $pid = getmypid();

        $config = json_decode(base64_decode($config), true);

        $class = $config['class'];
        $method = $config['method'];
        $arguments = $config['arguments'] ? unserialize($config['arguments']) : [];
        $pidDirectory = $config['pidDirectory'];

        $reflectionClass = new ReflectionClass($class);
        $reflectionClass->isSubclassOf(ProcessAbstract::class) ||
            throw new Exception("The class \"$class\" should be extended from the " . ProcessAbstract::class . " class");

        $reflectionMethod = $reflectionClass->getMethod($method);
        if (!$reflectionMethod->isPublic()) {
            throw new Exception("The method \"$method\" should be public");
        }

        echo base64_encode(json_encode(['pid' => $pid, 'token' => $token])) . PHP_EOL;
        $api = (new ProcessApi($pidDirectory))->create($pid, $token, 254);

        ob_start();
        $reflectionMethod->invokeArgs($reflectionClass->newInstanceArgs([$api]), $arguments);
        $buffer = ob_get_clean();

        $api->setBuffer($buffer);
    }
}

// If CLI script is executed, run the process
if (PHP_SAPI === 'cli') {
    $arguments = $_SERVER['argv'];
    $initialFile = array_shift($arguments);
    if (__FILE__ === $initialFile) {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $config = array_shift($arguments);
        System::runProcess($config);
    }
}