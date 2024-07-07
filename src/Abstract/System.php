<?php declare(strict_types=1);

namespace Framework\Process\Abstract;

use Framework\Process\Api as ProcessApi;

/**
 * Interface Framework\Process\Abstract\System
 */
abstract class System
{
    public function __construct(protected ProcessApi $api)
    {
    }
}