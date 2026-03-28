<?php

namespace Illuminate\Bus;

interface BatchRepository
{
    public function get(int $limit, mixed $before): array;

    public function find(string $batchId): ?Batch;

    public function store(PendingBatch $batch): Batch;

    public function incrementTotalJobs(string $batchId, int $amount): void;

    public function decrementPendingJobs(string $batchId, string $jobId): UpdatedBatchJobCounts;

    public function incrementFailedJobs(string $batchId, string $jobId): UpdatedBatchJobCounts;

    public function markAsFinished(string $batchId): void;

    public function cancel(string $batchId): void;

    public function delete(string $batchId): void;

    public function transaction(\Closure $callback): mixed;

    public function rollBack(): void;

    public function prune(\DateTimeInterface $before): int;
}
