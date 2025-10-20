<?php

class CustomLimit
{
    private $max;
    private $period;

    public function __construct($max, $period)
    {
        $this->max = $max;
        $this->period = $period;
    }
}

class SomeService
{
    public function configure()
    {
        return new CustomLimit(100, 5);
    }
}

?>