<?php

namespace Drupal\commerce_xquantity\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_price\Calculator;
use Drupal\xnumber\Utility\Xnumber as Numeric;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormInterface;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce\Context;

/**
 * Overrides the order item entity class.
 */
class XquantityOrderItem extends OrderItem {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['quantity'] = BaseFieldDefinition::create('xdecimal')
      ->setLabel(t('Quantity'))
      ->setDescription(t('The number of purchased units.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE)
      ->setSetting('precision', 14)
      ->setSetting('scale', 4)
      ->setSetting('min', 0)
      ->setDefaultValue(1)
      ->setDisplayOptions('form', [
        'type' => 'xnumber',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemsQuantity() {
    $settings = $this->getQuantityWidgetSettings();
    // The #step value defines the actual type of the current order item's
    // quantity field. If that is int then we consider the quantity as a sum of
    // order items. If float, then we consider the quantity as one item
    // consisting of multiple units. For example: 1 + 2 T-shirts are counted as
    // 3 separate items but 1.000 + 2.000 kg of butter is counted as 1 item
    // consisting of 3000 units. Hence, this method must be used only to count
    // items on an order. The $this->getQuantity() must be used for getting real
    // quantity disregarding of whatever the type of this number is, for example
    // to calculate the price of order items.
    $step = isset($settings['step']) && is_numeric($settings['step']) ? $settings['step'] + 0 : 1;
    $quantity = $this->getQuantity();
    return (string) is_int($step) ? $quantity : (is_float($step) && $quantity > 0 ? '1' : $quantity);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantityWidgetSettings() {
    $settings = [];
    $settings['disable_on_cart'] = FALSE;
    // If 'Add to cart' form display mode is enabled we prefer its settings
    // because exactly those settings are exposed to and used by a customer.
    $form_display = $this->getFormDisplayWidget();
    $quantity = $form_display ? $form_display->getComponent('quantity') : NULL;

    if (!$quantity) {
      $form_display = $this->getFormDisplayWidget('default');
      $quantity = $form_display ? $form_display->getComponent('quantity') : NULL;
    }

    if (isset($quantity['settings']['step'])) {
      $settings = $form_display->getRenderer('quantity')->getFormDisplayModeSettings();
    }
    else {
      // Fallback if 'default' or 'add_to_cart' form modes don't exist.
      $settings += (array) $this->get('quantity')->getSettings();
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function setQuantityPrices(FormInterface &$form_object, $widget, FormStateInterface $form_state) {
    $settings = $this->getQuantityWidgetSettings();
    if (empty($settings['qty_prices']) || !($count = count($settings['qty_price'])) || !($purchased_entity = $this->getPurchasedEntity())) {
      return $settings;
    }
    $lis = $notify = '';
    $price = $purchased_entity->getPrice();
    $variation_type = $purchased_entity->bundle();
    $product = $purchased_entity->getProduct();
    $product_stores = $product->getStores();
    array_walk($product_stores, function (&$store) {
      $store = $store->bundle();
    });
    $list_price = $purchased_entity->getListPrice();
    $data = [
      'variation_id' => $purchased_entity->id(),
      'variation_type' => $purchased_entity->bundle(),
      'product_id' => $product->id(),
      'product_type' => $product->bundle(),
      'list_price' => $list_price,
      'product_stores' => $product_stores,
      'current_roles' => \Drupal::currentUser()->getRoles(),
    ];
    $arguments = [];
    $form_object->quantityScale = Numeric::getDecimalDigits($settings['step']);
    $formatter = \Drupal::service('commerce_price.currency_formatter');
    // Roll back to an initial price.
    $form_object->quantityPrices[] = [
      'price' => $price,
      'qty_start' => $settings['min'] ?: $settings['step'],
      'qty_end' => '',
    ];
    foreach ($settings['qty_price'] as $index => $qty_price) {
      extract($qty_price);
      if ($qty_start && ($settings['qty_prices'] > $index) && $this->quantityPriceApplies($qty_price, $data)) {
        $new = $list ? $list_price : $price;
        if (is_numeric($adjust_value)) {
          if ($adjust_type == 'fixed_number') {
            $adjust_price = new $new($adjust_value, $new->getCurrencyCode());
          }
          else {
            $adjust_price = $new->divide('100')->multiply($adjust_value);
          }
          $new = $new->$adjust_op($adjust_price);
        }
        if ($new->isNegative()) {
          $new = $new->multiply('0');
        }
        $form_object->quantityPrices[] = [
          'price' => $new,
        ] + $qty_price;
        $new = $new->toArray();
        if (($this->isNew() && !empty($notify['add_to_cart'])) || ($this->id() && !empty($notify['shopping_cart']))) {
          $args = [];
          foreach ($qty_price as $key => $value) {
            if ($key == 'notify') {
              $value = implode(', ', array_values($qty_price[$key]));
            }
            $args["%{$key}"] = $value;
          }
          $arguments[] = [
            '%price' => $formatter->format(Calculator::round($new['number'], 2), $new['currency_code']),
          ] + $args;
          $li = new TranslatableMarkup('Buy <span style="color:yellow;font-weight: bolder;">%qty_start</span> or more and get <span style="color:yellow;font-weight: bolder;">%price</span> price for an item', end($arguments));
          $lis .= "<li>{$li}</li>";
        }
      }
    }
    $module_handler = \Drupal::moduleHandler();
    $module_handler->alter("xquantity_add_to_cart_qty_prices", $form_object, $widget, $form_state);
    $form_state->setFormObject($form_object);
    if ($lis) {
      $msg = new TranslatableMarkup("Price adjustments for the %label:<br><ul>{$lis}</ul><hr>", [
        '%label' => $this->label(),
        'qty_arguments' => $arguments,
      ]);
      $module_handler->alter("xquantity_add_to_cart_qty_prices_msg", $msg, $widget, $form_state);
      $messenger = \Drupal::service('messenger');
      $messages = $messenger->messagesByType('status');
      $messenger->deleteByType('status');
      // Make sure the 'Added to cart' message displayed the last.
      $added_to_cart_msg = NULL;
      foreach ($messages as $message) {
        if (preg_match('/\<a href\="\/cart"\>.*\<\/a\>/', $message->__toString(), $matches)) {
          $added_to_cart_msg = $message;
        }
        else {
          $messenger->addMessage($message);
        }
      }
      $msg && $messenger->addMessage($msg);
      $added_to_cart_msg && $messenger->addMessage($added_to_cart_msg);
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantityPrice(FormInterface $form_object, PurchasableEntityInterface $purchased_entity, $quantity = NULL) {
    $price = NULL;
    if (isset($form_object->quantityPrices) && ($qty_prices = $form_object->quantityPrices)) {
      $scale = $form_object->quantityScale ?: 0;
      $product = $purchased_entity->getProduct();
      $product_stores = $product->getStores();
      array_walk($product_stores, function (&$store) {
        $store = $store->bundle();
      });
      $data = [
        'variation_id' => $purchased_entity->id(),
        'variation_type' => $purchased_entity->bundle(),
        'product_id' => $product->id(),
        'product_type' => $product->bundle(),
        'list_price' => $purchased_entity->getListPrice(),
        'product_stores' => $product_stores,
        'current_roles' => \Drupal::currentUser()->getRoles(),
      ];
      foreach ($qty_prices as $qty_price) {
        $start = bccomp($qty_price['qty_start'], $quantity, $scale);
        $end = $qty_price['qty_end'] ? bccomp($quantity, $qty_price['qty_end'], $scale) : 0;
        if (($end === 1) || ($start === 1)) {
          continue;
        }
        if ($this->quantityPriceApplies($qty_price, $data)) {
          $price = $qty_price['price'];
        }
      }
    }

    return $price;
  }

  /**
   * {@inheritdoc}
   */
  public function quantityPriceApplies(array $qty_price, array $data) {
    $list = $week_days = $time_start = $time_end = $date_start = $date_end = $variation_ids = $product_ids =
      $variation_types = $product_types = $stores = $roles = NULL;
    extract($qty_price + $data);
    $current = time();
    if (
      $list && !$list_price ||
      $week_days && !in_array(date('l'), array_map('trim', explode(PHP_EOL, $week_days))) ||
      $time_start && (strtotime($time_start) > $current) ||
      $time_end && (strtotime($time_end) < $current) ||
      $date_start && (strtotime($date_start) > $current) ||
      $date_end && (strtotime($date_end) < $current) ||
      $variation_ids && !in_array($variation_id, array_map('trim', explode(PHP_EOL, $variation_ids))) ||
      $product_ids && !in_array($product_id, array_map('trim', explode(PHP_EOL, $product_ids))) ||
      $variation_types && !in_array($variation_type, array_map('trim', explode(PHP_EOL, $variation_types))) ||
      $product_types && !in_array($product_type, array_map('trim', explode(PHP_EOL, $product_types))) ||
      $stores && !array_intersect($product_stores, array_map('trim', explode(PHP_EOL, $stores))) ||
      $roles && !array_intersect($current_roles, array_map('trim', explode(PHP_EOL, $roles)))
    ) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormDisplayWidget($mode = 'add_to_cart') {
    return $this->entityTypeManager()
      ->getStorage('entity_form_display')
      ->load("commerce_order_item.{$this->bundle()}.{$mode}");
  }

  /**
   * {@inheritdoc}
   */
  public function rotateStock(PurchasableEntityInterface $entity, $quantity, Context $context) {
    foreach (array_reverse($entity->getFieldDefinitions()) as $definition) {
      if ($definition->getType() == 'xquantity_stock') {
        $field_name = $definition->getName();
        $xquantity_stock = $entity->get($field_name);
        $value = $xquantity_stock->value;
        break;
      }
    }
    if (empty($xquantity_stock) || !($threshold = $xquantity_stock->getSetting('threshold'))) {
      return;
    }
    $scale = Numeric::getDecimalDigits($xquantity_stock->getSetting('step'));
    $storage = $this->entityTypeManager()->getStorage('commerce_order');
    $query = $storage->getQuery();
    $query->accessCheck(FALSE);
    $time = time() - $threshold;
    $query->condition('changed', $time, '<');
    $query->condition('cart', '1', '=');
    $query->condition('locked', '0', '=');
    $order_type_id = \Drupal::service('commerce_order.chain_order_type_resolver')->resolve($this);
    $store = $context->getStore();
    $cart = \Drupal::service('commerce_cart.cart_provider')->getCart($order_type_id, $store);
    if ($cart) {
      $query->condition('order_id', $cart->id(), '<>');
    }
    if ($orders = $query->execute()) {
      $storage = $this->entityTypeManager()->getStorage('commerce_order_item');
      $query = $storage->getQuery();
      $query->accessCheck(FALSE);
      $query->condition('order_id', $orders, 'IN');
      $query->condition('purchased_entity', $entity->id(), '=');
      $query->sort('changed');
      if ($order_items = $query->execute()) {
        $cart_manager = \Drupal::service('commerce_cart.cart_manager');
        $qty = 0;
        foreach ($storage->loadMultiple($order_items) as $order_item) {
          $qty = bcadd($qty, $order_item->getQuantity(), $scale);
          $cart_manager->removeOrderItem($order_item->getOrder(), $order_item);
          if ((bccomp($qty, $quantity, $scale) !== -1)) {
            return TRUE;
          }
        }
      }
    }
  }

}
