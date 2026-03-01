<?php

namespace App\Models;

use App\Models\Traits\CompanyableTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Watson\Validating\ValidatingTrait;

class ConsumableAssignment extends Model
{
    use CompanyableTrait;
    use ValidatingTrait;

    protected $table = 'consumables_users';

    protected $fillable = [
        'consumable_id',
        'assigned_to',
        'assigned_type',
        'note',
    ];

    public $rules = [
        'assigned_to' => 'required|integer',
    ];

    public function consumable()
    {
        return $this->belongsTo(\App\Models\Consumable::class);
    }

    /**
     * Get the target this consumable is checked out to (polymorphic)
     */
    public function assignedTo()
    {
        return $this->morphTo('assigned', 'assigned_type', 'assigned_to')->withTrashed();
    }

    /**
     * Legacy relationship - kept for backward compatibility
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    public function adminuser()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by')->withTrashed();
    }

    /**
     * Gets the lowercased name of the type of target the consumable is assigned to
     */
    public function assignedType()
    {
        return $this->assigned_type ? strtolower(class_basename($this->assigned_type)) : null;
    }

    public function checkedOutToUser(): bool
    {
        return $this->assigned_type === User::class;
    }

    public function checkedOutToAsset(): bool
    {
        return $this->assigned_type === Asset::class;
    }

    public function scopeUserAssigned(Builder $query): void
    {
        $query->where('assigned_type', '=', User::class);
    }

    public function scopeAssetsAssigned(Builder $query): void
    {
        $query->where('assigned_type', '=', Asset::class);
    }
}
