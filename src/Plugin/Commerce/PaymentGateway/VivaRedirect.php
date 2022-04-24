<?php

namespace Drupal\commerce_viva\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentTypeManager;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Url;
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
 *     "offsite-payment" = "Drupal\commerce_viva\PluginForm\VivaRedirect\VivaOffsiteForm",
 *   },
 * )
 */
class VivaRedirect extends OffsitePaymentGatewayBase {
  /**
   * Http client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $httpClientFactory;

  /**
   * Construct offsite redirect.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin ID.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\commerce_payment\PaymentTypeManager $payment_type_manager
   *   Payment type manager.
   * @param \Drupal\commerce_payment\PaymentMethodTypeManager $payment_method_type_manager
   *   Payment method type manager.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Http\ClientFactory $http_client_factory
   *   Http client factory.
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface|null $minor_units_converter
   *   Minor units converter service.
   */
  public function __construct(array $configuration, string $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, PaymentTypeManager $payment_type_manager, PaymentMethodTypeManager $payment_method_type_manager, TimeInterface $time, ClientFactory $http_client_factory, MinorUnitsConverterInterface $minor_units_converter = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $payment_type_manager, $payment_method_type_manager, $time, $minor_units_converter);
    $this->httpClientFactory = $http_client_factory;
  }

  /**
   * {@inheritdoc}
   */
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
      $container->get('commerce_price.minor_units_converter')
    );
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
      'brand_color' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $description = 'Use <a href=":link" target="_blank">:link</a> to manage appropriate account.';
    $form['mode']['live']['#description'] = $this->t(
      $description,
      [
        ':link' => 'https://www.vivapayments.com/selfcare/en/sources/paymentsources',
      ]
    );
    $form['mode']['test']['#description'] = $this->t(
      $description,
      [
        ':link' => 'https://demo.vivapayments.com/selfcare/en/sources/paymentsources',
      ]
    );

    $payment_gateway = $form_state->getFormObject()->getEntity();
    if (!$payment_gateway->isNew()) {
      $form['info'] = [
        '#type' => 'details',
        '#title' => $this->t('Account configuration info'),
        '#open' => TRUE,
        'redirect_link' => [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t(
            'To successful work of plugin please use in redirect that links:<br /> - <b>%success_redirect</b> (for success) <br /> - <b>%error_redirect</b> (for failure)',
            [
              '%success_redirect' => Url::fromRoute(
                'commerce_viva.order_success',
                [
                  'payment_gateway' => $payment_gateway->id(),
                ],
                [
                  'absolute' => TRUE,
                ]
              )->toString(),
              '%error_redirect' => Url::fromRoute(
                'commerce_viva.order_error',
                [
                  'payment_gateway' => $payment_gateway->id(),
                ],
                [
                  'absolute' => TRUE,
                ]
              )->toString(),
            ]
          ),
        ],
      ];
    }

    $form['donation'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'donate-button-container'],
      'button' => [
        '#type' => 'container',
        '#attributes' => ['id' => 'donate-button'],
        '#attached' => ['library' => ['commerce_viva/donate-sdk-inline']],
      ],
    ];

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

    $form['brand_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Brand color'),
      '#description' => $this->t('This is the Brand color for the Viva Wallet smart checkout (Hexadecimal)'),
      '#default_value' => $this->configuration['brand_color'],
      '#required' => FALSE,
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
      $this->configuration['brand_color'] = $values['brand_color'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $order->set('state', 'completed');
    $order->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onCancel(OrderInterface $order, Request $request) {
    // Order will be invalidated on timeout.
  }

  /**
   * API URL resolver.
   *
   * @param string $demo
   *   Demo API subdomain.
   * @param string $live
   *   Live API subdomain.
   * @param string $path
   *   Path of API.
   *
   * @return string
   *   Resolved API URL.
   */
  public function resolveUrl(string $demo, string $live, string $path = ''): string {
    $url = $this->getMode() === 'test' ? $demo : $live;
    return 'https://' . $url . '.vivapayments.com' . $path;
  }

  /**
   * To get authentication access token.
   */
  public function oauthAccessToken() {
    $configuration = $this->getConfiguration();

    $client_id = $configuration['client_id'];
    $client_secret = $configuration['client_secret'];

    $url = $this->resolveUrl('demo-accounts', 'accounts');

    $client = $this->httpClientFactory->fromOptions([
      'base_uri' => $url,

    ]);
    $result = $client->post('/connect/token',
      [
        RequestOptions::AUTH => [$client_id, $client_secret],
        RequestOptions::HEADERS => [
          'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        RequestOptions::FORM_PARAMS => ['grant_type' => 'client_credentials'],
      ]
    );
    $response = Json::decode($result->getBody()->getContents());
    return $response['access_token'];
  }

}
