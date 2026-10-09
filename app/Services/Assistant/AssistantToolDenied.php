<?php

namespace App\Services\Assistant;

use RuntimeException;

class AssistantToolDenied extends RuntimeException
{
    public function __construct(public readonly string $tool)
    {
        parent::__construct("Alat {$tool} tidak diizinkan.");
    }
}
