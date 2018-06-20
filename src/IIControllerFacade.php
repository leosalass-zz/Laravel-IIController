<?php

namespace Immersioninteractive\GenericController;

use Illuminate\Support\Facades\Facade;

class IIControllerFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'IIController';
    }
}
