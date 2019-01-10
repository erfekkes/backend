<?php

namespace Drupal\ezac\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

use Drupal\ezac\Model\EzacLid;

/**
 * Controller for DLO administration.
 */
class EzacLedenController extends ControllerBase {

    /**
     * Display the status of the EZAC leden table
     * @return array
     */
  public function status() {
    $content = [];

      //$schema = drupal_get_module_schema('ezac', 'leden');
      //dpm($schema); //debug

    // show record count for each Code value
    $headers = [
      t("Code"),
      t("Aantal"), 
      t("Uitvoer"),
    ];
    
    $total = 0;
    foreach (EzacLid::$lidCode as $code => $description) {
      $count = EzacLid::counter(['code' => $code, 'actief' => TRUE]);
      $total = $total+$count;
      $urlCode = Url::fromRoute(
        'ezac_leden_overzicht_code',
        [
          'code' => $code
        ]
      )->toString();
      $urlExport = Url::fromRoute(
        'ezac_leden_export_code',
        [
          'filename' => "Leden-$code.csv",
          'code' => $code
        ]
      )->toString();
      $rows[] = [
        t("<a href=$urlCode>$description</a>"),
        $count,
        t("<a href=$urlExport>Leden-$code.csv</a>"),
      ];
    }
    // add line for totals
    $urlCode = Url::fromRoute(
      'ezac_leden_overzicht'
    )->toString();
    $urlExport = Url::fromRoute(
      'ezac_leden_export',
      [
        'filename' => "Leden.csv",
      ]
    )->toString();
    $rows[]= [
      t("<a href=$urlCode>Totaal</a>"),
      $total,
      t("<a href=$urlExport>Leden.csv</a>"),
    ];
    //build table
    $content['table'] = [
      '#type' => 'table',
      '#caption' => t("Categorie overzicht van het EZAC LEDEN bestand"),
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => t('Geen gegevens beschikbaar.'),
      '#sticky' => TRUE,
    ];
    
    //toon functie om user-ids voor de EZAC website aan te maken
    $urlCreate = Url::fromRoute(
      'ezac_leden_user_create'
    )->toString();
    $content['users'] = [
      '#type' => 'markup',
      '#markup' => "<a href=$urlCreate>Aanmaken user-id voor EZAC website</a>",
    ];
    
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    //apply css
    //$content['#attached']['library'][] = 'ezac/dlotable'; // of met ['css']
    return $content;
  }

    /**
     * Render a list of entries in the database.
     * @param string
     *  $code - categorie (optional)
     * @return array
     */
  public function overzicht($code = NULL) {
    $content = array();

    $rows = [];
    $headers = [
      t('naam<br>email'),
      t('afkorting'),
      t('code'),
      t('adres<br>postcode<br>plaats<br>land'),
      t('telefoon<br>mobiel'),
      t('opmerking'),
    ];

    // select only leden records where actief == TRUE
    if (isset($code)) {
      $condition =
        [
          'code' => $code,
          'actief' => TRUE
        ];
    }
    else $condition = ['actief' => TRUE];
    
    // prepare pager
    $total = EzacLid::counter($condition);
    $field = 'id';
    $sortkey = 'achternaam';
    $sortdir = 'ASC';
    $range = 50;
    $page = pager_default_initialize($total, $range);
    $from = $range * $page;
    
    $ledenIndex = EzacLid::index($condition, $field, $sortkey, $sortdir, $from, $range);
    foreach ($ledenIndex as $id) {
      $lid = (new EzacLid)->read($id);
      $urlString = Url::fromRoute(
        'ezac_leden_update',  // edit leden record
        ['id' => $lid->id]
      )->toString();
      $rows[] = [
        //link each record to edit route
        t("<a href=$urlString>$lid->voornaam $lid->voorvoeg $lid->achternaam</a><br>$lid->e_mail"),
        t("$lid->afkorting"),
        t("$lid->code"),
        t("$lid->adres<br>$lid->postcode $lid->plaats<br>$lid->land"),
        t("$lid->telefoon<br>$lid->mobiel"),
        t("$lid->opmerking"),
      ];
    }
    $caption = "Overzicht EZAC Leden bestand";
    if (isset($code)) $caption .= " - " . EzacLid::$lidCode[$code];
    $content['table'] = [
        '#type' => 'table',
        '#caption' => $caption,
        '#header' => $headers,
        '#rows' => $rows,
        '#empty' => t('Geen gegevens beschikbaar.'),
        '#sticky' => TRUE,
    ];
    // add pager
    $content['pager'] = [
        '#type' => 'pager',
        '#weight' => 5
    ];
    // Don't cache this page.
    $content['#cache']['max-age'] = 0;

    return $content;
  } // overzicht

    /**
     * Maak exportbestand uit Leden tabel
     * geformatteerd voor input in bestand (csv)
     * Output via html headers naar attachment
     *
     * @param string $filename
     * @param null $code
     * @return mixed Response output text in csv format
     *   output text in csv format
     */
  public function export($filename = 'ezac.txt', $code = NULL) {

    $messenger = \Drupal::messenger();

    if ($filename == '') $filename = 'ezac.txt';

    // Determine CODE categorie from Leden for export
    if (isset($code)) {
      $condition = [
        'code' => $code,
        'actief' => TRUE
      ];
    }
    else $condition = ['actief' => TRUE]; //select all active records

    $records = EzacLid::index($condition); //read records index
    $count = count($records);
    $messenger->addMessage("Export $count records met code [$code] naar bestand [$filename]"); //DEBUG

    $output = ""; // initialize output
    //build header line
    foreach (EzacLid::$fields as $field => $description) {
      $output .= '"' .$field .'";';
    }
    //remove last ";" 
    $output = rtrim($output, ";") ."\r\n";
    
    // export all records
    foreach ($records as $id) {
      $lid = (new EzacLid)->read($id);
      // add all fields
      foreach (EzacLid::$fields as $field => $description) {
        $output .= sprintf('"%s";',$lid->$field);
      }
      //remove last ";" 
      $output = rtrim($output, ";") ."\r\n";
    }

    $response = new Response(
      $output,
      Response::HTTP_OK,
      array(
        'content-type' => 'text/plain',
      )
    );

    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    $response->headers->set('Content-Disposition', $disposition);
    $response->setCharset('UTF-8');

      /** @var mixed $response */
      return $response;
  } // export  
  
} //class EzacLedenController
