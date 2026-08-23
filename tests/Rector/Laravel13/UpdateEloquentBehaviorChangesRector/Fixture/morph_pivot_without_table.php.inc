<?php

use Illuminate\Database\Eloquent\Relations\MorphPivot;

class TaggablePivot extends MorphPivot
{
}

?>
-----
<?php

use Illuminate\Database\Eloquent\Relations\MorphPivot;

// @laravel-upgrade eloquent-behavior inferred morph pivot table names are now pluralized. Define protected $table explicitly if you relied on the previous singular inferred name.
class TaggablePivot extends MorphPivot
{
}

?>
