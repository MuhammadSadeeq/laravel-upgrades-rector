<?php

namespace App\Services;

class DataProcessor
{
    public function mergeIfMissing(array $data): void {}

    public function process()
    {
        $processor = new DataProcessor();
        $processor->mergeIfMissing(['other' => 'data']);
    }
}

?>
