<?php

namespace App\MorphPivotFixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

final class ExplicitPostTagPivot extends MorphPivot
{
    protected $table = 'post_tags';
}

final class ExplicitPost extends Model
{
    public function tags(): mixed
    {
        return $this->morphToMany(ExplicitTag::class, 'tag')->using(ExplicitPostTagPivot::class);
    }
}

final class ExplicitTag extends Model {}

final class ExplicitRelationTablePivot extends MorphPivot {}

function explicitRelationTable(Model $model): mixed
{
    return $model->morphToMany(ExplicitTag::class, 'tag', 'post_tags')->using(ExplicitRelationTablePivot::class);
}

final class DynamicRelationTablePivot extends MorphPivot {}

function dynamicRelationTable(Model $model, string $table): mixed
{
    return $model->morphToMany(ExplicitTag::class, 'tag', $table)->using(DynamicRelationTablePivot::class);
}

final class MorphUnrelatedService
{
    public function morphToMany(string $related, string $name): mixed
    {
        return null;
    }
}

function unrelatedMorphCall(MorphUnrelatedService $service): mixed
{
    return $service->morphToMany(ExplicitTag::class, 'tag');
}
