<?php

Schema::create('legacy_schema_patterns', function ($blueprint) {
    $blueprint->double('amount', 8, 2);
    $blueprint->unsignedFloat('ratio', 5, 2);
});

?>
-----
<?php

Schema::create('legacy_schema_patterns', function ($blueprint) {
    $blueprint->double('amount', 8, 2);
    $blueprint->unsignedFloat('ratio', 5, 2);
});

?>
