<?php

namespace Laravel\Telescope;

class IncomingEntry
{
    public string $type = '';

    public function isReportableException(): bool
    {
        return true;
    }
}
