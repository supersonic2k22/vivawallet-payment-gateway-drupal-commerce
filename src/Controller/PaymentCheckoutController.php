<?php

namespace Drupal\commerce_viva\Controller;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\CheckoutOrderManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_viva\Plugin\Commerce\PaymentGateway\OffsiteRedirect;
use Drupal\Core\Access\AccessException;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
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
class PaymentCheckoutController implements ContainerInjectionInterface
{

  /**
   * The checkout order manager.
   *
   * @var \Drupal\commerce_checkout\CheckoutOrderManagerInterface
   */
  protected $checkoutOrderManager;

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
   * Constructs a new PaymentCheckoutController object.
   *
   * @param \Drupal\commerce_checkout\CheckoutOrderManagerInterface $checkout_order_manager
   *   The checkout order manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(CheckoutOrderManagerInterface $checkout_order_manager, MessengerInterface $messenger, LoggerInterface $logger)
  {
    $this->checkoutOrderManager = $checkout_order_manager;
    $this->messenger = $messenger;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('commerce_checkout.checkout_order_manager'),
      $container->get('messenger'),
      $container->get('logger.channel.commerce_payment')
    );
  }

  /**
   * Provides the "return" checkout payment page.
   *
   * Redirects to the next checkout page, completing checkout.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function returnSuccessPage(Request $request, RouteMatchInterface $route_match)
  {
    watchdog_exception('test1', new \Exception(print_r(
      $request->query->all(),
      1
    )));
    $transaction_id = $request->query->get('t');
    $order = $this->retrieveTransaction($transaction_id);
    $step = 'success'; //@todo:calculate success step from the order.
    return new RedirectResponse(Url::fromRoute('commerce_payment.checkout.return', ['commerce_order'=>$order->id(),'step'=>$step] )->toString());

  }

  public function returnErrorPage(Request $request, RouteMatchInterface $route_match)
  {
    watchdog_exception('test1', new \Exception(print_r(
      $request->query->all(),
      1
    )));
    return ['#markup' => 'Error'];
  }

  public function retrieveTransaction($transaction_id)
  {
    /** @var \Drupal\commerce_payment\PaymentGatewayStorage $payment_storage */
    $payment_storage = \Drupal::entityTypeManager()->getStorage('commerce_payment_gateway');
    /** @var PaymentGatewayInterface $payment_viva */
    $payment_viva = $payment_storage->load('vivawallet');
    /** @var \Drupal\commerce_viva\Plugin\Commerce\PaymentGateway\OffsiteRedirect $payment_plugin */
    $payment_plugin = $payment_viva->getPlugin();
    $curl = curl_init();
    $url = $payment_plugin->resolveUrl('demo-api','api',"/checkout/v2/transactions/$transaction_id");

    $access_token = $payment_plugin->oauthAccessToken();
    curl_setopt_array($curl, array(
      CURLOPT_URL => $url,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Authorization: Bearer '.$access_token
      ),
    ));
    $response = curl_exec($curl);
    $status_code = curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    $response = json_decode($response,true);
    $order_storage = \Drupal::entityTypeManager()->getStorage('commerce_order');
    $orders = $order_storage->loadByProperties(['field_order_code'=>$response['orderCode']]);
    $order = reset($orders);
    if($response['statusId']==="F"){
      $order->set('state','completed');
      $order->save();
    }
    return $order;

  }


}
