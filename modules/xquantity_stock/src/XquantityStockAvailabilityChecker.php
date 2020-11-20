<?php

namespace Drupal\xquantity_stock;

use Drupal\commerce_order\AvailabilityManagerInterface;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce\Context;
use Drupal\xnumber\Utility\Xnumber as Numeric;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Xquantity availability checker.
 */
class XquantityStockAvailabilityChecker implements AvailabilityManagerInterface {

  /**
   * Determines whether the checker applies to the given purchasable entity.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   *
   * @return bool
   *   TRUE if the checker applies to the given purchasable entity, FALSE
   *   otherwise.
   */
  public function applies(OrderItemInterface $order_item) {
    $entity = $order_item->getPurchasedEntity();
    $applies = FALSE;
    foreach (array_reverse($entity->getFieldDefinitions()) as $definition) {
      if ($definition->getType() == 'xquantity_stock') {
        $applies = TRUE;
        break;
      }
    }
    // Allow modules to apply their own logic.
    \Drupal::moduleHandler()->alter("xquantity_availability_applies", $applies, $entity);

    return $applies;
  }

  /**
   * Checks the availability of the given purchasable entity.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item.
   * @param \Drupal\commerce\Context $context
   *   The context.
   * @param int $quantity
   *   The quantity.
   *
   * @return bool|null
   *   TRUE if the entity is available, FALSE if it's unavailable,
   *   or NULL if it has no opinion.
   */
  public function check(OrderItemInterface $order_item, Context $context, $quantity = 0) {
    $entity = $order_item->getPurchasedEntity();
    $available = $xquantity_stock = NULL;
    if ($context->getData('xquantity')) {
      foreach (array_reverse($entity->getFieldDefinitions()) as $definition) {
        if ($definition->getType() == 'xquantity_stock') {
          $field_name = $definition->getName();
          $xquantity_stock = $entity->get($field_name);
          $value = $xquantity_stock->value;
          break;
        }
      }
      if (!$xquantity_stock) {
        return;
      }
      $scale = Numeric::getDecimalDigits($xquantity_stock->getSetting('step'));
      if ($old = $context->getData('old')) {
        // Return some quantity to the stock.
        if ($available = (bccomp($old, $quantity, $scale) !== -1)) {
          $diff = bcsub($old, $quantity, $scale);
          $entity->set($field_name, bcadd($value, $diff, $scale))->save();
          $quantity = '0';
        }
        else {
          $quantity = bcsub($quantity, $old, $scale);
        }
      }
      $stock = bcsub($value, $quantity, $scale);
      if (!$available && $available = (bccomp($stock, 0, $scale) !== -1)) {
        $entity->set($field_name, $stock)->save();
      }
      // Allow modules to apply their own logic.
      $context = [
        'entity' => $entity,
        'context' => $context,
        'xquantity_stock' => $xquantity_stock,
      ];
      \Drupal::moduleHandler()->alter("xquantity_availability_check", $available, $quantity, $context);
    }

    return $available;
  }

}
