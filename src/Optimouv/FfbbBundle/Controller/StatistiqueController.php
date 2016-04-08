<?php

namespace Optimouv\FfbbBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;

class StatistiqueController extends Controller
{
    public function indexAction()
    {


        return $this->render('FfbbBundle:Statistique:index.html.twig', array(
            ));
    }

    public function exportPdfAction()
    {

//        return $this->render('FfbbBundle:Statistique:exportPdf.html.twig', [
//        ]);


        $html = $this->renderView('FfbbBundle:Statistique:exportPdf.html.twig', [
        ]);


        return new Response(
            $this->get('knp_snappy.pdf')->getOutputFromHtml($html),
            200,
            array(
                'Content-Type'          => 'application/pdf',
                'Content-Disposition'   => 'attachment; filename="graph.pdf"',
                'print-media-type'      => false,
                'outline'               => true,

            )
        );



    }

}
