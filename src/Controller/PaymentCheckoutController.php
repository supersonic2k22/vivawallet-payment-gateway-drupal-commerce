<?php

namespace Drupal\commerce_viva\Controller;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides checkout endpoints for off-site payments.
 */
class PaymentCheckoutController implements ContainerInjectionInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new PaymentCheckoutController object.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    MessengerInterface $messenger,
    LoggerInterface $logger,
    EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->messenger = $messenger;
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger'),
      $container->get('logger.channel.commerce_payment'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Provides the "return" checkout payment page.
   *
   * Redirects to the next checkout page, completing checkout.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway
   *   Payment getaway responsible to process request to the controller.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function returnSuccessPage(PaymentGatewayInterface $payment_gateway, Request $request) {
    $transaction_id = $request->query->get('t');
    $order = $this->retrieveTransaction($payment_gateway, $transaction_id);
    // @todo calculate success step from the order.
    $step = 'success';
    return new RedirectResponse(Url::fromRoute('commerce_payment.checkout.return', [
      'commerce_order' => $order->id(),
      'step' => $step,
    ])->toString());

  }

  /**
   * Error page redirect.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request instance.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   Route match service.
   *
   * @return string[]
   *   Response.
   */
  public function returnErrorPage(Request $request, RouteMatchInterface $route_match) {
    return ['#markup' => 'Error'];
  }

  /**
   * Order entity getter based on the transaction ID.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway
   *   Payment getaway responsible to process request to the controller.
   * @param string $transaction_id
   *   Transaction ID.
   *
   * @return false|mixed
   *   Order instance.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function retrieveTransaction(PaymentGatewayInterface $payment_gateway, string $transaction_id) {
    /** @var \Drupal\commerce_viva\Plugin\Commerce\PaymentGateway\OffsiteRedirect $payment_plugin */
    $payment_plugin = $payment_gateway->getPlugin();
    $curl = curl_init();
    $url = $payment_plugin->resolveUrl('demo-api', 'api', "/checkout/v2/transactions/$transaction_id");

    $access_token = $payment_plugin->oauthAccessToken();
    curl_setopt_array($curl, [
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => TRUE,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => TRUE,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $access_token,
      ],
    ]);
    $response = curl_exec($curl);

    curl_close($curl);
    $response = Json::decode($response, TRUE);
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $orders = $order_storage->loadByProperties(['field_order_code' => $response['orderCode']]);
    $order = reset($orders);
    if ($response['statusId'] === "F") {
      $order->set('state', 'completed');
      $order->save();
    }
    return $order;
  }

}
