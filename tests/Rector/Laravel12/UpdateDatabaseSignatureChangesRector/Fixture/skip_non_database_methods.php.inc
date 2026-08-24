<?php

namespace App\Fixtures\Laravel12\UpdateDatabaseSignatureChangesRector;

class CustomService
{
    public function setConnection($connection) {}
    public function getTablePrefix() {}
}

function run(CustomService $service)
{
    $service->setConnection('x');
    $service->getTablePrefix();
}
