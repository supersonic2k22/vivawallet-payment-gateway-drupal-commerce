<?php

  namespace Drupal\commerce_viva\Plugin\Commerce\PaymentGateway;

  use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\commerce_viva\PluginForm\OffsiteRedirect\FondyOffsiteForm;
  use Drupal\Core\Language\LanguageInterface;
  use Drupal\commerce_order\Entity\OrderInterface;
  use Symfony\Component\HttpFoundation\Request;
  use Drupal\commerce_order\Entity\Order;

  /**
   * Provides the Viva Wallet offsite Checkout payment gateway.
   *
   * @CommercePaymentGateway(
   *   id = "viva_redirect",
   *   label = @Translation("VivaWallet (Redirect to payment page)"),
   *   display_label = @Translation("Viva Wallet"),
   *    forms = {
   *     "offsite-payment" = "Drupal\commerce_viva\PluginForm\OffsiteRedirect\FondyOffsiteForm",
   *   },
   * )
   */
  class OffsiteRedirect extends OffsitePaymentGatewayBase {


    public function success_transaction(){
      $curl = curl_init();
    
      curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://demo.vivapayments.com/api/messages/config/token',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'Authorization: Basic MTQ3OTBhZGUtOWZkMS00MDlkLTkxZTQtMDBiOTA2OTNiMTRiOjk3R0NnXg=='
        ),
      ));
      
      $response = curl_exec($curl);
      
      curl_close($curl);
      echo $response;
      return $response;
    
    }
    
    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
      return [
        'merchant_id' => '',
        'api_key' => '',
        'website_code' => '',
        'client_id' => '',
        'client_secret' => '',
      ] + parent::defaultConfiguration();
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form,
                                           FormStateInterface $form_state) {
      $form = parent::buildConfigurationForm($form, $form_state);

      $form['merchant_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Merchant ID'),
        '#description' => $this->t('This is the Merchant ID from the Viva Wallet.'),
        '#default_value' => $this->configuration['merchant_id'],
        '#required' => TRUE,
      ];

      $form['api_key'] = [
        '#type' => 'textfield',
        '#title' => $this->t('API key'),
        '#description' => $this->t('This is the API key from the Viva Wallet.'),
        '#default_value' => $this->configuration['api_key'],
        '#required' => TRUE,
      ];

      $form['website_code'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Website code'),
        '#description' => $this->t('This is the website code (4 digit) from the Viva Wallet.'),
        '#default_value' => $this->configuration['website_code'],
        '#required' => TRUE,
      ];

      $form['client_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Client ID'),
        '#description' => $this->t('This is the Client ID from the Viva Wallet(Smart Checkout Credentials).'),
        '#default_value' => $this->configuration['client_id'],
        '#required' => TRUE,
      ];

      $form['client_secret'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Client Secret'),
        '#description' => $this->t('This is the Client ID from the Viva Wallet (Smart Checkout Credentials).'),
        '#default_value' => $this->configuration['client_secret'],
        '#required' => TRUE,
      ];

      return $form;
    }

    /**
     * {@inheritdoc}
     */
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
      parent::submitConfigurationForm($form, $form_state);
      if (!$form_state->getErrors()) {
        $values = $form_state->getValue($form['#parents']);
        $this->configuration['merchant_id'] = $values['merchant_id'];
        $this->configuration['api_key'] = $values['api_key'];
        $this->configuration['website_code'] = $values['website_code'];
        $this->configuration['client_id'] = $values['client_id'];
        $this->configuration['client_secret'] = $values['client_secret'];
      }
    }

    /**
     * @param OrderInterface $order
     * @param Request $request
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function onReturn(OrderInterface $order, Request $request) {

      $settings = [
        'api_key' => $this->configuration['api_key'],
        'merchant_id' => $this->configuration['merchant_id'],
        'website_code' => $this->configuration['website_code'],
        'client_id' => $this->configuration['client_id'],
        'client_secret' => $this->configuration['client_secret']
      ];
      $data = $request->request->all();
      list($orderId,) = explode(FondyOffsiteForm::ORDER_SEPARATOR, $data['order_id']);
      if ($this->isPaymentValid($settings, $data, $order) !== TRUE) {
        $this->messenger()->addMessage($this->t('Invalid Transaction. Please try again'), 'error');
        return $this->onCancel($order, $request);
      }
      else {
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        $payment = $payment_storage->create([
          'state' => 'completed',
          'amount' => $order->getTotalPrice(),
          'payment_gateway' => $this->entityId,
          'order_id' => $orderId,
          'remote_id' => $data['payment_id'],
          'remote_state' => $data['order_status']
        ]);
        $payment->save();
        $this->messenger()->addMessage(
          $this->t('Your payment was successful with Order id : @orderid and Transaction id : @payment_id',
            [
              '@orderid' => $order->id(),
              '@payment_id' => $data['payment_id']
            ]
          ));
      }
    }

    /**
     * @param Request $request
     * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
     * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
     * @throws \Drupal\Core\Entity\EntityStorageException
     */
    public function onNotify(Request $request) {
      $settings = [
        'api_key' => $this->configuration['api_key'],
        'merchant_id' => $this->configuration['merchant_id'],
        'website_code' => $this->configuration['website_code'],
        'client_id' => $this->configuration['client_id'],
        'client_secret' => $this->configuration['client_secret']
      ];
      $data = $request->request->all();
      if (!$data)
        $data = $request->getContent();
      list($orderId,) = explode(FondyOffsiteForm::ORDER_SEPARATOR, $data['order_id']);
      $order = Order::load($orderId);
      if ($this->isPaymentValid($settings, $data, $order) !== TRUE) {
        die($this->t('Invalid Transaction. Please try again'));
      }
      else {
        $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
        if ($data['order_status'] == 'expired' or $data['order_status'] == 'declined') {
          $order->set('state', 'cancelled');
          $order->save();
        }
        $last = $payment_storage->loadByProperties([
          'payment_gateway' => $this->entityId,
          'order_id' => $orderId,
          'remote_id' => $data['payment_id']
        ]);
        if (!empty($last)) {
          $payment_storage->delete($last);
        }
        $payment = $payment_storage->create([
          'state' => 'completed',
          'amount' => $order->getTotalPrice(),
          'payment_gateway' => $this->entityId,
          'order_id' => $orderId,
          'remote_id' => $data['payment_id'],
          'remote_state' => $data['order_status']
        ]);
        $payment->save();
        die('Ok');
      }
    }

    /**
     * @param $settings
     * @param $response
     *
     * @return bool
     */

    public function isPaymentValid($settings, $response, $order) {
      if (!$response) {
        return FALSE;
      }
      if ($settings['merchant_id'] != $response['merchant_id']) {
        return FALSE;
      }
      $transaction_currency = $response['currency'];
      $transaction_amount = $response['amount'] / 100;
      $order_currency = $order->getTotalPrice()->getCurrencyCode();
      $order_amount = $order->getTotalPrice()->getNumber();

      if (!$this->validateSum($transaction_currency, $order_currency,
        $transaction_amount, $order_amount)
      ) {
        return FALSE;
      }

      return TRUE;
    }

    /**
     * @param $transaction_currency
     * @param $order_currency
     * @param $transaction_amount
     * @param $order_amount
     *
     * @return bool
     */
    protected function validateSum($transaction_currency, $order_currency,
                                   $transaction_amount, $order_amount) {
      if ($transaction_currency != $order_currency) {
        return FALSE;
      }
      if ($transaction_amount != $order_amount) {
        return FALSE;
      }

      return TRUE;
    }

  }
