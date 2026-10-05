<?php

namespace HaithamMaznai\FormStepper\Interfaces;

// use HaithamMaznai\FormStepper\Enums\RequestType;
use Illuminate\Database\Eloquent\Relations\MorphMany;

interface Requester
{
  public function requests() : MorphMany;

  public function getDefaultRequestType() : mixed;

  public function getAvailableRequestTypes() : array;

  public function getObjectType() : string;

  public function getObjectKey();
}
