<?php

namespace App\Fixtures\Laravel11\UpdateSpatialTypesRector;

Schema::create('legacy_schema_patterns', function ($blueprint) {
    $blueprint->point('coordinates');
});

?>
