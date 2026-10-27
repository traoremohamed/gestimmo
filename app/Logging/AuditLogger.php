<?php

namespace App\Logging;

use Monolog\Logger;

class AuditLogger
{
    public function __invoke(array $config): Logger
    {
        return (new Logger('audit'))->pushHandler(new AuditLoggingHandler());
    }
}
