<?php declare(strict_types=1);

namespace Framework\Process;

use Framework\Process\Api as ProcessApi;
use ReflectionClass;
use ReflectionException;

use Framework\Process\Abstract\System as ProcessAbstract;

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
 * @class Framework\Process\System
 * @package Framework\Process
 * @link https://tereta.dev
 * @since 2020-2024
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @author Tereta Alexander <tereta.alexander@gmail.com>
 * @copyright 2020-2024 Tereta Alexander
 */
class System
{
    /**
     * @return string
     */
    public static function getClassFile(): string
    {
        return __FILE__;
    }

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
        require_once __DIR__ . '/../../../autoload.php';

        $config = array_shift($arguments);
        System::runProcess($config);
    }
}