<?php

namespace App\Services;

class NonRequestProcessor
{
    public function mergeIfMissing(array $data): void {}

    public function process()
    {
        $processor = new NonRequestProcessor();
        $processor->mergeIfMissing(['other' => 'data']);
    }
}

?>
