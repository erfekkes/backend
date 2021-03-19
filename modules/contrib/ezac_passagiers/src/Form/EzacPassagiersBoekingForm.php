<?php

namespace Drupal\ezac_reserveringen\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\ezac\Util\EzacUtil;
use Drupal\ezac\Util\EzacMail;
use Drupal\ezac_passagiers\Model\EzacPassagier;

/**
 * UI to show free slots
 */
class EzacPassagiersBoekingForm extends FormBase {

  /**
   * @inheritdoc
   */
  public function getFormId() {
    return 'ezac_passagiers_boeking_form';
  }

  /**
   * buildForm for passagiers boeking
   *
   * @param array $form
   * @param FormStateInterface $form_state
   * @param string $datum
   * @param string $tijd
   *
   * @return array
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $datum = null, string $tijd = null) {
    $messenger = Drupal::messenger();

    // read settings
    $settings = Drupal::config('ezac_passagiers.settings');
    $slots = $settings->get('slots');
    $texts = $settings->get('texts');
    $parameters = $settings->get('parameters');

    // Wrap the form in a div.
    $form = [
      '#prefix' => '<div id="boekingform">',
      '#suffix' => '</div>',
    ];

    if ($texts['mededeling'] != '') {
      $form['mededeling'] = [
        '#type' => 'markup',
        '#markup' => t($texts['mededeling']),
        '#weight' => 0,
      ];
    }
    $datum_delen = explode('-', $datum);
    $jaar  = $datum_delen[0];
    if (isset($datum_delen[1])) $maand = $datum_delen[1];
    if (isset($datum_delen[2])) $dag   = $datum_delen[2];

    $dat_string = EzacUtil::showDate($datum); // dag van de week

    $form['datum'] = array(
      '#type' => 'hidden',
      '#value' => $datum,
    );
    $form['tijd'] = array(
      '#type' => 'hidden',
      '#value' => $tijd,
    );

    // create intro
    $form[0]['#type'] = 'markup';
    $form[0]['#markup'] = '<p><h2>Reserveren meevliegen bij de EZAC</h2></p>';
    $form[0]['#markup'] .= "<p><h3>Datum: $dat_string om $tijd</h3></p>";
    $form[0]['#markup'] .= $texts['advies'];
    $form[0]['#weight'] = 0;
    $form[0]['#prefix'] = '<div class="reserveer-intro-div">';
    $form[0]['#suffix'] = '</div>';

    $form['naam'] = array(
      '#title' => t('Naam van de passagier'),
      '#type' => 'textfield',
      '#description' => t('De naam voor op de reserveringslijst'),
      '#maxlength' => 30,
      '#required' => TRUE,
      '#size' => 30,
      '#weight' => 1,
      '#prefix' => '<div class="reserveer-naam-div">',
      '#suffix' => '</div>',
    );
    $form['telefoon'] = array(
      '#title' => t('Telefoonnummer contactpersoon'),
      '#type' => 'textfield',
      '#description' => t('Het nummer waarop je het best bereikbaar bent voor eventuele wijzigingen'),
      '#maxlength' => 20,
      '#required' => TRUE,
      '#size' => 20,
      '#weight' => 2,
      '#prefix' => '<div class="reserveer-telefoon-div">',
      '#suffix' => '</div>',
    );
    $form['email'] = array(
      '#title' => t('E-mail'),
      '#type' => 'textfield',
      '#description' => t('E-mail adres voor de bevestiging'),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#size' => 50,
      '#weight' => 3,
      '#prefix' => '<div class="reserveer-mail-div">',
      '#suffix' => '</div>',
    );
    $form['gevonden'] = array(
      '#title' => t('Hoe heb je ons gevonden?'),
      '#type' => 'textfield',
      '#description' => t('Geef svp aan hoe je de EZAC hebt gevonden'),
      '#maxlength' => 30,
      '#required' => FALSE,
      '#size' => 30,
      '#weight' => 4,
      '#prefix' => '<div class="reserveer-mail-div">',
      '#suffix' => '</div>',
    );
    $form['mail_list'] = array(
      '#title' => t('Wil je in de toekomst ook berichten van de EZAC ontvangen?'),
      '#type' => 'checkbox',
      '#default_value' => 0,
      '#weight' => 5,
      '#prefix' => '<div class="reserveer-mail-div">',
      '#suffix' => '</div>',
    );

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Reserveer deze vlucht'),
      '#weight' => 10,
      '#prefix' => '<div class="reserveer-submit-div">',
      '#suffix' => '</div>',
    );
    
    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();
    //validate naam
    //validate telefoon
    $telefoon = $form_state->getValue('telefoon');
    if (strlen($telefoon) > 0) {
      $telefoon = str_replace (' ', '', $telefoon);
      //$telefoon = str_replace ('-', '', $telefoon);
      $telefoon = str_replace ('(', '', $telefoon);
      $telefoon = str_replace (')', '', $telefoon);
      $telefoon = str_replace ('[', '', $telefoon);
      $telefoon = str_replace (']', '', $telefoon);
      $telefoon = str_replace ('{', '', $telefoon);
      $telefoon = str_replace ('}', '', $telefoon);
      $form_state->setValue('telefoon', $telefoon); // clean up number
    }
    //validate mail
    /*
    $email = $form_state->getValue('email');
    if (!valid_email_address($email)) {
      form_set_error('mail', t("$email is een ongeldig mail adres"));
    }
    */
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = Drupal::messenger();

    $settings = Drupal::config('ezac_passagiers.settings');
    $texts = $settings->get('texts');
    $parameters = $settings->get('parameters');

    // start D7 code
    // vastleggen reservering
    $naam = $form_state['values']['naam'];
    $telefoon = $form_state['values']['telefoon'];
    $email = $form_state['values']['email'];
    $datum = $form_state['values']['datum'];
    $tijd = $form_state['values']['tijd'];
    $gevonden = $form_state['values']['gevonden'];
    $mail_list = $form_state['values']['mail_list'];

    $afkorting = EzacUtil::getUser();
    if ($afkorting != '') {
      $status = $parameters['reservering_bevestigd']; 
      // indien door EZAC lid ingegeven is bevestiging niet nodig
    }
    else {
      $status = $parameters['reservering_optie']; 
      // indien door gast ingegeven is bevestiging wel nodig
    }

    $passagier = new EzacPassagier();
    $passagier->datum = $datum;
    $passagier->tijd = $tijd;
    $passagier->naam = $naam;
    $passagier->telefoon = $telefoon;
    $passagier->mail = $email;
    $passagier->aanmaker = ($afkorting != '') ? $afkorting : 'anoniem';
    $passagier->soort = 'passagier';
    $passagier->status = $status;
    $passagier->gevonden = $gevonden;
    $passagier->mail_list = $mail_list;
    
    $mail_keuze = ($mail_list == 1) ? t("WEL") : t("NIET");

    // aanmaken reservering
    $id = $passagier->create();

    // versturen bevestiging met link en sleutel voor wijziging / annulering
    //   aanmaken sleutel met hash functie
    $hash_fields = array(
      'id' => $id,
      'datum' => $datum,
      'tijd' => $tijd,
      'naam' => $naam,
      'mail' => $email,
      'telefoon' => $telefoon,
    );
    $data = implode('/', $hash_fields);
    //$hash = drupal_hash_base64($data);
    $hash = hash('sha256', $data, FALSE);

    $eindtijd = date('G:i', strtotime('+1H')); // 1 uur na nu

    // passagiers/edit/id/datum/tijd/naam/telefoon/hash
    
    //$url_bevestiging = $base_url ."/passagiers/confirm/$id/$hash";
    //$url_verwijderen = $base_url ."/passagiers/delete/$id/$hash";

    $url_bevestiging = Url::fromRoute(
      'ezac_passagiers_bevestiging',
      [
        'id' => $id,
        'hash' => $hash,
      ],
    )->toString();
    $url_verwijderen = Url::fromRoute(
      'ezac_passagiers_verwijderen',
      [
        'id' => $id,
        'hash' => $hash,
      ],
    )->toString();
    $show_datum = EzacUtil::showDate($datum);
    // Maak boarding card met disclaimer tekst (pdf)
    //   disclaimer tekst in disclaimer.txt file
    //   EZAC logo in ezaclogo.jpg
    //   Aanmaken html file met de juiste elementen, opmaak met css
    $subject = "Reservering meevliegen EZAC op $show_datum $tijd";
    unset($body);
    $body  = "<html lang='nl'><body>";
    $body .= "<p>Er is voor $naam een reservering voor meevliegen bij de EZAC aangemaakt";
    $body .= "<br>Deze reservering geldt voor 1 persoon";
    $body .= "<br>";
    $body .= "<br>De reservering is voor $show_datum om $tijd";
    $body .= "<br>Graag een kwartier van tevoren aanwezig zijn (Justaasweg 5 in Axel)";
    $body .= "<br>";
    if ($status == $parameters['reservering_optie']) {
      $body .= "<br>Deze reservering dient <strong>voor $eindtijd</strong> te worden bevestigd, anders vervalt deze.";
      $body .= "<br>Bevestig via <a href=$url_bevestiging>DEZE LINK</a>";
      $body .= "<br>";
    }
    $body .= "<br>Mocht het niet mogelijk zijn hiervan gebruik te maken, dan kan deze reservering";
    $body .= "<br>via <a href=$url_verwijderen>DEZE LINK</a> worden geannuleerd ";
    $body .= "<br>";
    $body .= "<br>Je hebt aangegeven $mail_keuze op de EZAC mailing list te willen";
    $body .= "<br>";
    $body .= "<br>Voor verdere contact gegevens: zie de <a href=http://www.ezac.nl>EZAC website</a>";
    $body .= "<br>";
    $body .= "<br>Met vriendelijke groet,";
    $body .= "<br>Eerste Zeeuws Vlaamse Aero Club";
    $body .= "</body></html>";
    //   Genereren PDF
    //   Mailen PDF als attachment of download via button
    EzacMail::mail('ezac_passagiers', 'boeking', $email, $subject, $body);

    //drupal_set_message("Reservering $id aangemaakt met code $hash", 'status');

    // toon bevestiging
    $form_state['redirect'] = "passagiers/bevestigen/$id";
    // end D7 code

  } //submitForm

}
