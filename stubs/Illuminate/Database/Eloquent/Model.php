<?php

namespace Illuminate\Database\Eloquent;

abstract class Model
{
    /** @var array<int, string> */
    protected $fillable = [];

    /** @var array<int, string> */
    protected $hidden = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<static>
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<static>
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null)
    {
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<static, static>
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null)
    {
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<static>
     */
    public function belongsToMany(string $related, ?string $table = null)
    {
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<static>
     */
    public function morphMany(string $related, string $name)
    {
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne<static>
     */
    public function morphOne(string $related, string $name)
    {
    }

    public function morphTo(?string $name = null, ?string $type = null, ?string $id = null)
    {
    }

    public function morphToMany(string $related, string $name)
    {
    }

    public function morphedByMany(string $related, string $name)
    {
    }

    public function hasManyThrough(string $related, string $through)
    {
    }

    public function hasOneThrough(string $related, string $through)
    {
    }
}
