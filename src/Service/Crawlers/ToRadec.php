<?php

namespace App\Service\Crawlers;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Path;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ToRadec {

    private $client;
    private $folder;
    private $fileIndex = 'index_craw.json';
    
    /** */
    public function __construct(ParameterBagInterface $container, HttpClientInterface $client)
    {
        $this->client = $client;
        $this->folder = $container->get('recRadec');
        if(!is_dir($this->folder)) {
            mkdir($this->folder);
        }
    }

    /** */
    public function load(String $uriCall): String
    {
        $elementAnet = '<tr class="source-anet"><td></td></tr>';
        $elementRadec = '<tr class="source-radec"><td></td></tr>';

        if($uriCall != '') {
            
            $index = [];
            $name = base64_encode($uriCall);
            $indexFile = null;
            $path = Path::normalize($this->folder . $this->fileIndex);
            if(is_file($path)) {
                $indexFile = file_get_contents($path);
            }
            if($indexFile) {
                $index = json_decode($indexFile);
            }

            if(in_array($name, $index)) {
                $pathHtml = Path::normalize($this->folder . $name.'.html');
                $res = file_get_contents($pathHtml);
                return $elementAnet . $res;
            }else{

                $index[] = $name;    
                file_put_contents($path, json_encode($index));
                $response = $this->client->request(
                    'GET', 'https://www.radec.com.mx/catalogo', [
                        'query' => ['search' => $uriCall, 'op' => 'Buscar']
                    ]
                );

                $statusCode = $response->getStatusCode();
                if($statusCode == 200) {

                    $body = $this->extraerBody($response->getContent());
                    $pathHtml = Path::normalize($this->folder . $name.'.html');
                    file_put_contents($pathHtml, $body);
                    return $elementRadec . $body;
                }
            }
        }

        return '';
    }

    /** */
    private function extraerBody(String $html)
    {
        $htmlRes = '';
        $crawler = new Crawler($html);
        $body = $crawler->filter('.catalog-list-product');

        if($body) {
            foreach ($body as $domElement) {
                $htmlRes .= $domElement->ownerDocument->saveHTML($domElement);
            }
        }
        return $htmlRes;
    }
}