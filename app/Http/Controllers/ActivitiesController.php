<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ActivitiesController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct() {}

    protected function fetchGoogleSpreadsheet($url)
    {
        $curl = curl_init($url);

        $caPathOrFile = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        if (is_dir($caPathOrFile)) {
            curl_setopt($curl, CURLOPT_CAPATH, $caPathOrFile);
        } else {
            curl_setopt($curl, CURLOPT_CAINFO, $caPathOrFile);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);

        $spreadsheet_raw_content = curl_exec($curl);

        if($spreadsheet_raw_content === false) {
            throw new \Exception('Unable to fetch data');
        }
        
        $spreadsheet_lines = preg_split('/\r\n|\r|\n/', $spreadsheet_raw_content);
        $csv = array_map('str_getcsv', $spreadsheet_lines);
        $items = [];
        $header = $csv[0];

        foreach($csv as $index => $line) {
            if($index == 0) {
                // ignore header
                continue;
            }

            $items[] = array_combine($header, $line);
        }

        return $items;
    }

    /**
     * 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $url = config('data.spreadsheet_url') . '&cache=' . rand(0, 10000);
        $sources = $this->fetchGoogleSpreadsheet($url);
        $activities = [];

        if(count($sources) == 0) {
            return [];
        }

        foreach($sources as $spreadsheet) {
            $id = $spreadsheet['id'];
            $url = $spreadsheet['url'] . '&cache=' . rand(0, 10000);

            try {
                $activities[$id] = $this->fetchGoogleSpreadsheet($url);

            } catch (\Exception $error) {
                // Something bad happened. Let's put up some fake data with
                // info about the error.
                $activities[$id] = array([
                    "step" => "0",
                    "type" => "info",
                    "value" => $id,
                    "desc" => "A planilha com a identificação <em>$id</em> está com problemas no seu conteúdo. <br /><br />Alguém colocou conteúdo em uma linha, porém esqueceu de colocar a coluna para identificar os dados. Talvez isso tenha sido porque alguém escreveu algum comentário em uma célula da planilha fora das colunas esperadas (por exemplo, fora da coluna <code>step</code>, <code>value</code>, etc). Verifique a planilha e atualize os dados do aplicativo.",
                    "icon" => "close.svg"
                ]);
            }
        }

        return $activities;
    }
}