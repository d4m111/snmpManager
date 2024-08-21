<?php

namespace D4m111\SnmpManager\App\Utils;

use Carbon\Carbon;
use Illuminate\Log\Logger;

class JsonFormatter
{
    /**
     * Customize the given logger instance.
     */
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new CustomLineFormatter(
                '{"date":"'. Carbon::now().'","severity":"%level_name%",%context%'."\n"
            ));
        }
    }
}