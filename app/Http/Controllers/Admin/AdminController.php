<?php

namespace App\Http\Controllers\Admin;

use App\Presenter\AdminApiPresenter;
use Laravel\Lumen\Routing\Controller as BaseController;

class AdminController extends BaseController
{
    protected $presenter;

    public function __construct(AdminApiPresenter $presenter)
    {
        $this->presenter = $presenter;
    }
}
