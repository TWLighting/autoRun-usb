<?php

namespace App\Http\Controllers;

use App\Presenter\ApiPresenter;
use Laravel\Lumen\Routing\Controller as BaseController;

class Controller extends BaseController
{
    protected $presenter;

    public function __construct(ApiPresenter $presenter)
    {
        $this->presenter = $presenter;
    }
}
