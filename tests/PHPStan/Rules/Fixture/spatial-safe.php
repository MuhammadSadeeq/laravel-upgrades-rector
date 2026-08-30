<?php

namespace App;

final class GeometryFactory
{
    public function geometry(string $column): string
    {
        return $column;
    }
}

function makeGeometry(GeometryFactory $factory): string
{
    return $factory->geometry('location');
}
