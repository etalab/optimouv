<?php

namespace Optimouv\AdminBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class UserController extends Controller
{
    public function userAddAction()
    {

        return $this->render('AdminBundle:User:add.html.twig');
    }
}
