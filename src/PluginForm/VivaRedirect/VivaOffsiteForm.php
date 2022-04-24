<?php

namespace Drupal\commerce_viva\PluginForm\VivaRedirect;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\FormStateInterface;

/**
 * Viva payment off-site form.
 */
class VivaOffsiteForm extends BasePaymentOffsiteForm {

  /**
   * Generate order code for the order entity.
   *
   * @return string|null
   *   Order code.
   *
   * @throws \JsonException
   */
  public function vivawalletOrderCode(): ?string {
    $payment = $this->entity;
    $amount = round(number_format($payment->getAmount()
      ->getNumber(), 2, '.', '') * 100);
    $order_id = \Drupal::routeMatch()->getParameter('commerce_order')->id();
    $order = Order::load($order_id);
    $address = $order->getBillingProfile()->address->first();
    $customer = $payment->getOrder()->getCustomer();
    if ($customer->isAnonymous() === FALSE) {
      $description = $this->t(
        'Customer: @customer_name. Order #: @order_id',
        [
          '@customer_name' => $customer->getAccountName(),
          '@order_id' => $order_id,
        ]);
    }
    else {
      $description = $this->t('Customer: anonymous');
    }

    $curl = curl_init();

    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();

    $website_code = $configuration['website_code'];

    $access_token = $payment_gateway_plugin->oauthAccessToken();

    $customer_info = [
      'email' => $order->getEmail(),
      'fullName' => $address->getGivenName() . ' ' . $address->getFamilyName(),
      // 'phone' => '',
      // 'countryCode' => '',
      'requestLang' => \Drupal::currentUser()->getPreferredLangcode(FALSE) ?: \Drupal::languageManager()->getCurrentLanguage()->getId(),
    ];

    $order_info = Json::encode([
      'amount' => (int) $amount,
      'customerTrns' => $description,
      'customer' => $customer_info,
      // 'paymentTimeout'=> 0,
      // 'preauth'=> true,
      // 'allowRecurring'=> true,
      // 'maxInstallments'=> 0,
      // 'paymentNotification'=> true,
      // 'tipAmount'=> 1,
      // 'disableExactAmount' => true,
      // 'disableCash' => true,
      // 'disableWallet' => true,
      'sourceCode' => $website_code,
      'merchantTrns' => $order_id,
      // 'tags' => "string"
    ]);

    $url = $payment_gateway_plugin->resolveUrl('demo-api', 'api', '/checkout/v2/orders');

    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $order_info,
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $access_token,
        'Content-Type: application/json',
      ],
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $response = Json::decode($response);
    return $response['orderCode'] ?: NULL;
  }

  /**
   * Checkout URL getter.
   *
   * @param string $order_code
   *   Order code.
   *
   * @return string
   *   Checkout url.
   */
  public function generateCheckoutUrl(string $order_code): string {
    $payment = $this->entity;
    $payment_gateway_plugin = $payment->getPaymentGateway()->getPlugin();
    $configuration = $payment_gateway_plugin->getConfiguration();
    $brand_color = $configuration['brand_color'];
    $url = $payment_gateway_plugin->resolveUrl('demo', 'www', '/web/checkout?ref=');
    $url .= $order_code;
    if ($brand_color) {
      $filtered_brand_color = preg_replace("/#/", "", $brand_color);
      $url .= '&color=' . $filtered_brand_color;
    }
    return $url;

  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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

    return $this->buildRedirectForm(
      $form,
      $form_state,
      $redirect_url,
      [],
      $redirect_method
    );
  }

}
