<?php

use core\Application;
use core\Controller;
use core\Url;

class IndexController extends Controller
{
    function actionDefault()
    {
        Application::redirect(new Url('user/login'));
    }
}
