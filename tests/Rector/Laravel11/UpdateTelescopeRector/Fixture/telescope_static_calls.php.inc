<?php

use Laravel\Telescope\Telescope;
use Laravel\Telescope\IncomingEntry;

class TelescopeServiceProvider
{
    public function boot()
    {
        Telescope::filter(function (IncomingEntry $entry) {
            return $entry->isReportableException();
        });

        Telescope::tag(function (IncomingEntry $entry) {
            return ['user:1'];
        });
    }
}

?>