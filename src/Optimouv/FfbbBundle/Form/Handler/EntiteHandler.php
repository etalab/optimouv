<?php

namespace Test\FrontBundle\Form\Handler;

use Doctrine\ORM\EntityManager;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;

class SheetHandler
{
    protected $form;
    protected $request;
    protected $em;

    /**
     * @param Form $form
     * @param Request $request
     */
    public function __construct(Form $form, Request $request, EntityManager $em)
    {
        $this->form = $form;
        $this->request = $request;
        $this->em = $em;
    }

    /**
     * @return bool
     */
    public function process()
    {
        $this->form->handleRequest($this->request);

        if ($this->request->isMethod('post') && $this->form->isValid()) {
            $this->onSuccess();

            return true;
        }

        return false;
    }

    /**
     * @return Form
     */
    public function getForm()
    {
        return $this->form;
    }

    /**
     *
     */
    protected function onSuccess()
    {
        $this->em->persist($this->form->getData());
        $this->em->flush();
    }
}