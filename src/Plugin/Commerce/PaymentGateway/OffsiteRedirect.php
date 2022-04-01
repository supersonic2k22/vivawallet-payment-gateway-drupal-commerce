<?php

  namespace Drupal\commerce_viva\Plugin\Commerce\PaymentGateway;

  use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\commerce_order\Entity\OrderInterface;
  use Symfony\Component\HttpFoundation\Request;

  /**
   * Provides the Viva Wallet offsite Checkout payment gateway.
   *
   * @CommercePaymentGateway(
   *   id = "viva_redirect",
   *   label = @Translation("VivaWallet (Redirect to payment page)"),
   *   display_label = @Translation("Viva Wallet"),
   *    forms = {
   *     "offsite-payment" = "Drupal\commerce_viva\PluginForm\OffsiteRedirect\VivaOffsiteForm",
   *   },
   * )
   */
  class OffsiteRedirect extends OffsitePaymentGatewayBase
  {

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration()
    {
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
    public function buildConfigurationForm(array              $form,
                                           FormStateInterface $form_state)
    {
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
    public function submitConfigurationForm(array &$form, FormStateInterface $form_state)
    {
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


    public function onReturn(OrderInterface $order, Request $request)
    {
    $order->set('state', 'completed');
    $order->save();

    }

    public function onCancel(OrderInterface $order, Request $request)
    {


    }
  }
