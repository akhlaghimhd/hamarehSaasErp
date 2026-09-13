<?php

namespace App\Modules\IdentityCore\Contracts;

interface SmsSenderInterface
{
    /**
     * Send plain-text SMS. Implementations must not log secrets in production.
     */
    public function send(string $mobile, string $message): void;
}
