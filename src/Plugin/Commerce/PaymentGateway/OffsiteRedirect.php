<?php

  namespace Drupal\commerce_viva\Plugin\Commerce\PaymentGateway;

  use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
  use Drupal\commerce_payment\PaymentMethodTypeManager;
  use Drupal\commerce_payment\PaymentTypeManager;
  use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
  use Drupal\commerce_price\MinorUnitsConverterInterface;
  use Drupal\commerce_viva\PluginForm\OffsiteRedirect\VivaOffsiteForm;
  use Drupal\Component\Datetime\TimeInterface;
  use Drupal\Core\Entity\EntityTypeManagerInterface;
  use Drupal\Core\Form\FormStateInterface;
  use Drupal\commerce_order\Entity\OrderInterface;
  use Drupal\Core\Http\ClientFactory;
  use GuzzleHttp\ClientInterface;
  use GuzzleHttp\RequestOptions;
  use Symfony\Component\DependencyInjection\ContainerInterface;
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
     * Http client factory.
     *
     * @var \Drupal\Core\Http\ClientFactory
     */
    protected $httpClientFactory;


    public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, ClientFactory $http_client_factory, MinorUnitsConverterInterface $minor_units_converter = NULL)
    {
      parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time, $minor_units_converter);
      $this->httpClientFactory = $http_client_factory;
    }

    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
      return new static(
        $configuration,
        $plugin_id,
        $plugin_definition,
        $container->get('entity_type.manager'),
        $container->get('plugin.manager.commerce_payment_type'),
        $container->get('plugin.manager.commerce_payment_method_type'),
        $container->get('datetime.time'),
        $container->get('http_client_factory'),
        //$container->has('commerce_price.minor_units_converter')?
        $container->get('commerce_price.minor_units_converter')//:NULL
      );
    }

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
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
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


    public static function basicAuthAccessToken($payment_gateway_plugin){
      $configuration = $payment_gateway_plugin->getConfiguration();

      $mid = $configuration['merchant_id'];
      $api_key = $configuration['api_key'];

      $b64str = $mid . ':' . $api_key;

      return base64_encode($b64str);
    }


    public function resolveUrl(string $demo, string $live, string $path = ''){

      $url = OffsiteRedirect::getMode() === 'test' ? $demo : $live;

      return 'https://'.$url.'.vivapayments.com'.$path;
    }

    public function oauthAccessToken(){
      $configuration = $this->getConfiguration();

      $client_id = $configuration['client_id'];
      $client_secret = $configuration['client_secret'];

      $url = $this->resolveUrl('demo-accounts','accounts');

      $client = $this->httpClientFactory->fromOptions([
        'base_uri' => $url,

      ]);
      $result =
        $client->post('/connect/token',
        [
            RequestOptions::AUTH=>[$client_id, $client_secret],
            RequestOptions::HEADERS=>[
              'Content-Type'=>'application/x-www-form-urlencoded',
              ],
          RequestOptions::FORM_PARAMS => ['grant_type'=>'client_credentials']
        ]
      );
      $response = json_decode($result->getBody()->getContents(),true);
      return $response['access_token'];


    }
  }
