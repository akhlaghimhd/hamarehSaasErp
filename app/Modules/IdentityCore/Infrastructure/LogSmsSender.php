<?php

namespace App\Modules\IdentityCore\Infrastructure;

use App\Modules\IdentityCore\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Local / non-production SMS driver: writes to log channel only.
 * Replace with real provider binding when SMS account is available.
 */
class LogSmsSender implements SmsSenderInterface
{
    public function send(string $mobile, string $message): void
    {
        Log::channel('single')->info('[SMS:local] to='.$mobile.' body='.$message);
    }
}
