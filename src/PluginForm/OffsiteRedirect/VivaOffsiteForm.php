<?php

namespace Drupal\commerce_viva\PluginForm\OffsiteRedirect;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Language\LanguageInterface;
use Drupal\commerce_viva\PluginForm\OffsiteRedirect;

class VivaOffsiteForm extends BasePaymentOffsiteForm
{

  public function vivawalletOrderCode()
  {

    //Viva
    $payment = $this->entity;
    $amount = round(number_format($payment->getAmount()
        ->getNumber(), 2, '.', '') * 100);
    $order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();
    $order = Order::load($order_id);
    $address = $order->getBillingProfile()->address->first();
    if ($payment->getOrder()->getCustomer()->isAnonymous() === FALSE) {
      $description = t('Customer: ') . $payment->getOrder()
          ->getCustomer()
          ->getAccountName() . '. ' . t('Order #: ') . $order_id;
    } else {
      $description = t('Customer: anonymous');
    }

    $curl = curl_init();

    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();

    $mid = $configuration['merchant_id'];
    $api_key = $configuration['api_key'];
    $website_code = $configuration['website_code'];

    $b64str = $mid . ':' . $api_key;
    $b64str_encode = base64_encode($b64str);

    $order_info = json_encode([
      'amount' => $amount,
      'email' => $order->getEmail(),
      'fullName' => $address->getGivenName(),
      'customerTrns' => $description,
      'requestLang' => 'en-GB',
      'sourceCode' => $website_code
    ]);


    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://demo.vivapayments.com/api/orders',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $order_info,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Basic ' . $b64str_encode
      ),
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $response = json_decode($response, true);
    $code_url = $response['OrderCode'];

    return $code_url;

    //Viva
  }

  public function generateCheckoutUrl(string $order_code)
  {

    $order_code_url = 'https://demo.vivapayments.com/web/checkout?ref=' . $order_code;
    return $order_code_url;

  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildConfigurationForm($form, $form_state);

    $redirect_method = 'post';
    $order_code = $this->vivawalletOrderCode();
    $redirect_url = $this->generateCheckoutUrl();

    $order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();
    $order = Order::load($order_id);
    if ($order instanceof Order) {
      $order->set('field_order_code', $order_code);
      $order->save();
    }

    $f_data = [];


    return $this->buildRedirectForm(
      $form,
      $form_state,
      $redirect_url,
      $f_data,
      $redirect_method
    );

  }

}
