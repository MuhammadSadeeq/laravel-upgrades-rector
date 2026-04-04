<?php

namespace Illuminate\Database;

class Grammar
{
    public function getTablePrefix(): string
    {
        return '';
    }

    public function setTablePrefix(string $prefix): void
    {
    }

    public function setConnection(Connection $connection): void
    {
    }
}
