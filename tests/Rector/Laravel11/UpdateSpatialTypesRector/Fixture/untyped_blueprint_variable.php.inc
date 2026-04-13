<?php

Schema::create('legacy_schema_patterns', function ($blueprint) {
    $blueprint->point('coordinates');
});

?>
-----
<?php

Schema::create('legacy_schema_patterns', function ($blueprint) {
    // Laravel 11: spatial types now use geometry() or geography(). Review whether geometry() or geography() is the correct replacement for this column.
    $blueprint->geometry('coordinates', subtype: 'point');
});

?>
