<?php

namespace Drupal\commerce_viva\PluginForm\OffsiteRedirect;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\commerce_viva\Plugin\Commerce\PaymentGateway\OffsiteRedirect;
use Drupal\Core\Form\FormStateInterface;

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

    $website_code = $configuration['website_code'];

    $basic_access_token = $payment_gateway_plugin->basicAuthAccessToken($payment_gateway_plugin);
    $access_token  = $payment_gateway_plugin->oauthAccessToken();

    $customer_info = [
      'email' => $order->getEmail(),
      'fullName' => $address->getGivenName(),
      //'phone' => '',
      //'countryCode' => '',
      //'requestLang' => 'en-GB'
      ];

    $order_info = json_encode([
      'amount' => (int) $amount,
      'customerTrns' => 'test',
      'customer'=> $customer_info,
      //'paymentTimeout'=> 0,
      //'preauth'=> true,
      //'allowRecurring'=> true,
      //'maxInstallments'=> 0,
      //'paymentNotification'=> true,
      //'tipAmount'=> 1,
      //'disableExactAmount' => true,
      //'disableCash' => true,
      //'disableWallet' => true,
      'sourceCode' => $website_code,
      'merchantTrns' => $order_id,
      //'tags' => "string"
    ]);

    $url = $payment_gateway_plugin->resolveUrl('demo-api','api','/checkout/v2/orders');

    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $order_info,
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.$access_token,
        'Content-Type: application/json'
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    $response = json_decode($response, true);
    $code_url = $response['orderCode'] ? : NULL;

    return $code_url;

    //Viva
  }

  public function generateCheckoutUrl(string $order_code)
  {
    $payment = $this->entity;
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $url = $payment_gateway_plugin->resolveUrl('demo','www','/web/checkout?ref=');
    $order_code_url = $url.$order_code;
    return $order_code_url;

  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildConfigurationForm($form, $form_state);

    $redirect_method = 'post';
    $order_code = $this->vivawalletOrderCode();
    $redirect_url = $this->generateCheckoutUrl($order_code);

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
