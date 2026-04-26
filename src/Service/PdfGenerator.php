<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class PdfGenerator
{
    private $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function generate(string $template, array $data = [], string $filename = 'document.pdf'): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        
        $dompdf = new Dompdf($options);
        
        $html = $this->twig->render($template, $data);
        $dompdf->loadHtml($html);
        
        // (Optional) Setup paper size
        $dompdf->setPaper('A4', 'portrait');
        
        $dompdf->render();
        
        return $dompdf->output();
    }
}
