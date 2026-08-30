<?php

namespace App;

final class SyncJob
{
    public bool $afterCommit = true;
}

function dispatchSyncJob(object $job): void
{
    $job->afterCommit();
    $enabled = $job->afterCommit;
    $job->beforeCommit();
}
