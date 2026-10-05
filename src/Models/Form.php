<?php

namespace HaithamMaznai\FormStepper\Models;

use HaithamMaznai\FormStepper\Enums\RequestType;
use HaithamMaznai\FormStepper\Interfaces\Requester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Form extends Model
{

  public function getTable(): string
  {
    return config('form-stepper.tables.forms_table_name', 'forms');
  }

//   protected $fillable = ['creator_id', 'tenant_type', 'tenant_id', 'requester_type', 'requester_id', 'data', 'current_step', 'type', 'extra'];

  protected $casts = [
    'data' => 'array'
  ];

//   protected $appends = [
//     'values'
//   ];

//   public function getValuesAttribute()
//   {
//     return json_decode($this->data, true);
//   }

  protected static function boot()
  {
    parent::boot();

    static::creating(function ($model) {
      $model->uuid = Str::uuid();
      $model->data ??= json_encode([]);
    });
  }

  public function tenant() : MorphTo
  {
    return $this->morphTo('tenant', 'tenant_type', 'tenant_id');
  }

  public function requester() : MorphTo
  {
    return $this->morphTo('requester', 'requester_type', 'requester_id');
  }

  public function creator() : BelongsTo
  {
    $creatorModel = new (config('form-stepper.creator.model'))();
    $foreignKey = config('form-stepper.creator.foreignKey', 'creator_id');
    $ownerKey = config('form-stepper.creator.ownerKey', $creatorModel->getkeyName());

    return $this->belongsTo($creatorModel::class, $foreignKey, $ownerKey);
  }

  public function scopeGuest($query)
  {
    $foreignKey = config('form-stepper.creator.foreignKey', 'creator_id');

    $query->whereNull($foreignKey)
    ->whereNull('tenant_id')
    ->whereNull('tenant_type')
    ->whereNull('requester_id')
    ->whereNull('requester_type');
  }

  public function scopeBy($query, $user = null)
  {
    $creatorModel = new (config('form-stepper.creator.model'))();
    $foreignKey = config('form-stepper.creator.foreignKey', 'creator_id');
    $ownerKey = config('form-stepper.creator.ownerKey', $creatorModel->getkeyName());

    $query->where($foreignKey, $user?->$ownerKey);
  }

  public function scopeOnTenant($query, Requester $tenant)
  {
    $query->where('tenant_type', $tenant->getObjectType())->where('tenant_id', $tenant->getObjectKey());
  }

  public function scopeFor($query, Requester $requester)
  {
    $query->where('requester_type', $requester->getObjectType())->where('requester_id', $requester->getObjectKey());
  }

  public function scopeIn($query, RequestType $type = RequestType::Customer)
  {
    $query->where('type', $type->cases());
  }

  public function scopeUuid($query, $uuid = null)
  {
    $query->where('uuid', $uuid);
  }

  // public function products()
  // {
  //   return $this->morphedByMany(Product::class, 'saleable');
  // }

  public function saleables(): array
  {

    $saleables = [];

    foreach(config('business_steper.saleables') as $alias => $model){
      $saleables[$alias] = $this->morphedByMany($model, 'saleable')->withPivot(['qty']);
    }

    return $saleables;
  }

  public function allSaleables(): Collection
  {

    $saleables = $this->saleables();
    $all = collect([]);

    foreach($saleables as $model){
      $all = $all->merge($model->get());
    }

    return $all;
  }
}
