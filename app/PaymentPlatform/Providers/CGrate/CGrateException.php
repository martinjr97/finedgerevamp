<?php

namespace App\PaymentPlatform\Providers\CGrate;

use Exception;

class CGrateException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly array $context = [],
        ?Exception $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }
}
