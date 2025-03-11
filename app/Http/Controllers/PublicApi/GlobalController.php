<?php

namespace App\Http\Controllers\PublicApi;

use App\Presenter\PublicApiPresenter;
use Laravel\Lumen\Routing\Controller as BaseController;

class GlobalController extends BaseController
{
    protected $presenter;

    public function __construct(PublicApiPresenter $presenter)
    {
        $this->presenter = $presenter;
    }
}