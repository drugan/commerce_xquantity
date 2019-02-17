<?php

namespace Drupal\xquantity_stock\EventSubscriber;

use Drupal\commerce\Context;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderItemEvent;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\commerce\AvailabilityManagerInterface;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;

/**
 * Commerce order event subscriber.
 */
class XquantityStockOrderEventSubscriber implements EventSubscriberInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The availability manager.
   *
   * @var \Drupal\commerce\AvailabilityManagerInterface
   */
  public $availabilityManager;

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   * @param \Drupal\Core\Session\AccountProxyInterface $user
   *   The current user.
   * @param \Drupal\commerce\AvailabilityManagerInterface $availability_manager
   *   The availability manager.
   */
  public function __construct(RouteMatchInterface $route_match, AccountProxyInterface $user, AvailabilityManagerInterface $availability_manager) {
    $this->routeMatch = $route_match;
    $this->currentUser = $user;
    $this->availabilityManager = $availability_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      'commerce_order.cancel.post_transition' => ['onOrderCancel', -100],
      OrderEvents::ORDER_PREDELETE => ['onOrderDelete', -100],
      OrderEvents::ORDER_ITEM_UPDATE => ['onOrderItemUpdate', -100],
      OrderEvents::ORDER_ITEM_DELETE => ['onOrderItemDelete', -100],
    ];

    return $events;
  }

  /**
   * Performs a stock transaction for an order Cancel event.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The order workflow event.
   */
  public function onOrderCancel(WorkflowTransitionEvent $event) {
    $order = $event->getEntity();
    foreach ($order->getItems() as $order_item) {
      $this->updateStock($order, $order_item, TRUE);
    }
  }

  /**
   * Performs a stock transaction on an order delete event.
   *
   * This happens on PREDELETE since the items are not available after DELETE.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onOrderDelete(OrderEvent $event) {
    $order = $event->getOrder();
    foreach ($order->getItems() as $order_item) {
      $this->updateStock($order, $order_item, TRUE);
    }
  }

  /**
   * Performs a stock transaction on an order item update.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   *   The order item event.
   */
  public function onOrderItemUpdate(OrderItemEvent $event) {
    // Prevent on the Add to cart and Shopping cart update forms.
    if ($this->routeMatch->getParameter('commerce_order')) {
      if (($order_item = $event->getOrderItem()) && ($order = $order_item->getOrder())) {
        $this->updateStock($order, $order_item);
      }
    }
  }

  /**
   * Returns quantity to the stock when an order item is deleted.
   *
   * @param \Drupal\commerce_order\Event\OrderItemEvent $event
   *   The order item event.
   */
  public function onOrderItemDelete(OrderItemEvent $event) {
    $order_item = $event->getOrderItem();
    // Do not run on order delete event as the order don't exist there.
    if ($order = $order_item->getOrder()) {
      $this->updateStock($order, $order_item, TRUE);
    }
  }

  /**
   * Calls xquantity_stock availability checker to update the entity stock.
   *
   * @param \Drupal\commerce_order\Entity\Order $order
   *   The commerce order.
   * @param \Drupal\commerce_order\Entity\OrderItem $order_item
   *   The commerce order item.
   * @param bool $delete
   *   Whether the order item is going to be deleted.
   */
  protected function updateStock(Order $order, OrderItem $order_item, $delete = FALSE) {
    $state = $order->getState()->value;
    if (($state != 'canceled') && ($state != 'completed')) {
      $quantity = $delete ? '0' : $order_item->getQuantity();
      $old = $delete ? $order_item->getQuantity() : $order_item->original->getQuantity();
      $context = new Context($this->currentUser, $order->getStore(), time(), [
        'xquantity' => $delete ? 'delete' : 'update',
        'old' => $old,
      ]);
      $purchased_entity = $order_item->getPurchasedEntity();
      $available = $purchased_entity && $this->availabilityManager->check($purchased_entity, $quantity, $context);
      if (!$available && $purchased_entity) {
        throw new \InvalidArgumentException("The quantity {$quantity} to update on the {$order_item->getTitle()} order item is not available on the stock.");
      }
    }
  }

}
