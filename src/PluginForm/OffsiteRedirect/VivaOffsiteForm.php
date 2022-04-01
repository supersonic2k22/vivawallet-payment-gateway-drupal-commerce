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

public function get_code_url(){
  
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
  
        $b64str = $mid.':'.$api_key;
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
        'Authorization: Basic '.$b64str_encode
      ),
    ));
  
    $response = curl_exec($curl);
  
    curl_close($curl);
    $response = json_decode($response, true);
    $code_url = $response['OrderCode'];
    $order_code_url = 'https://demo.vivapayments.com/web/checkout?ref='.$code_url;
    return $order_code_url;
    
  //Viva
}
      /**
       * additional cons
       */
      const ORDER_SEPARATOR = '#';
      const SIGNATURE_SEPARATOR = '|';

      /**
       * @param array $form
       * @param FormStateInterface $form_state
       * @return array
       * @throws \Drupal\commerce\Response\NeedsRedirectException
       */



  public function buildConfigurationForm(array $form, FormStateInterface $form_state)
  {
    $form = parent::buildConfigurationForm($form, $form_state);

    $payment = $this->entity;
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();

    $configuration = $payment_gateway_plugin->getConfiguration();

    $redirect_method = 'post';
    $redirect_url = $this->get_code_url();
    $mid = $configuration['merchant_id'];
    $api_key = $configuration['api_key'];

    $order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();
    $amount = round(number_format($payment->getAmount()
        ->getNumber(), 2, '.', '') * 100);
    $currency_code = $payment->getAmount()->getCurrencyCode();
    if ($payment->getOrder()->getCustomer()->isAnonymous() === FALSE) {
      $description = t('Customer: ') . $payment->getOrder()
          ->getCustomer()
          ->getAccountName() . '. ' . t('Order #: ') . $order_id;
      $subscriber_id = $payment->getOrder()->getCustomerId();
    } else {
      $description = t('Customer: anonymous');
      $subscriber_id = '';
    }
    // $callbackurl = $payment_gateway_plugin->getNotifyUrl()->toString();
    // $responseurl = Url::FromRoute('commerce_payment.checkout.return', [
    //   'commerce_order' => $order_id,
    //   'step' => 'payment'
    // ], ['absolute' => TRUE])->toString();

    $order = Order::load($order_id);
    $address = $order->getBillingProfile()->address->first();
    // Get the language of credit card form.
    // $language = $configuration['language'];
    // if ($language == LanguageInterface::LANGCODE_NOT_SPECIFIED &&
    //   $customer = $order->getCustomer()
    // ) {
    //   // Use account preferred language.
    //   $language = $customer->getPreferredLangcode();
    // }
    $f_data = [
      // 'merchant_id' => $mid,
      // 'order_id' => $order_id . self::ORDER_SEPARATOR . time(),
      // 'order_desc' => $description,
      // 'amount' => $amount,
      // 'merchant_data' => json_encode([
      //   'subscriber_id' => $subscriber_id,
      //   'custom_field_customer_name' => $address->getGivenName(),
      //   'custom_field_customer_address' => $address->getAddressLine1(),
      //   'custom_field_customer_city' => $address->getLocality(),
      //   'custom_field_customer_country' => $address->getAdministrativeArea(),
      //   'custom_field_customer_state' => $address->getCountryCode(),
      //   'custom_field_customer_zip' => $address->getPostalCode(),
      //   'custom_field_sender_email' => $order->getEmail()
      // ]),
      // 'currency' => $currency_code,
      // 'response_url' => $responseurl,
      // 'server_callback_url' => $callbackurl,
      // 'sender_email' => $order->getEmail()
    ];

    // $ecommercePaymentRequest->setCancelurl($form['/en/error-payment']);
    // $ecommercePaymentRequest->setBackurl($form['/en/error-payment']);

    // $ecommercePaymentRequest->setAccepturl($form['/en/success-payment']);
    // $ecommercePaymentRequest->setDeclineurl($form['/en/success-payment']);
    // $ecommercePaymentRequest->setExceptionurl($form['/en/success-payment']);

    // $f_data['signature'] = self::getSignature($f_data,
    //   $api_key);


      //get code url

    return $this->buildRedirectForm($form, $form_state, $redirect_url,
      $f_data, $redirect_method);

  }

  /**
   * Signature generator
   * @param      $data
   * @param      $password
   * @param bool $encoded
   * @return string
   *
   */
  // public static function getSignature($data, $password, $encoded = TRUE)
  // {
  //   $data = array_filter($data, function ($var) {
  //     return $var !== '' && $var !== NULL;
  //   });
  //   ksort($data);
  //   $str = $password;
  //   foreach ($data as $v) {
  //     $str .= self::SIGNATURE_SEPARATOR . $v;
  //   }
  //   if ($encoded) {
  //     return sha1($str);
  //   } else {
  //     return $str;
  //   }
  // }
}