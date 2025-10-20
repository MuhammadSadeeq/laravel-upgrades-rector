<?php

class RegularService
{
    public function casts()
    {
        // This should not be flagged as it's not a model
        return ['data' => 'array'];
    }
}

class CastHelper
{
    public function casts()
    {
        // This should not be flagged
        return 'cast helper method';
    }
}

class DatabaseConnection
{
    public function casts()
    {
        // This should not be flagged
        return $this->connection;
    }
}

?>