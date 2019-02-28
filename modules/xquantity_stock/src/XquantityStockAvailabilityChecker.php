<?php

namespace Drupal\xquantity_stock;

use Drupal\commerce\AvailabilityCheckerInterface;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce\Context;
use Drupal\xnumber\Utility\Xnumber as Numeric;

/**
 * Xquantity availability checker.
 */
class XquantityStockAvailabilityChecker implements AvailabilityCheckerInterface {

  /**
   * Determines whether the checker applies to the given purchasable entity.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The purchasable entity.
   *
   * @return bool
   *   TRUE if the checker applies to the given purchasable entity, FALSE
   *   otherwise.
   */
  public function applies(PurchasableEntityInterface $entity) {
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
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The purchasable entity.
   * @param int $quantity
   *   The quantity.
   * @param \Drupal\commerce\Context $context
   *   The context.
   *
   * @return bool|null
   *   TRUE if the entity is available, FALSE if it's unavailable,
   *   or NULL if it has no opinion.
   */
  public function check(PurchasableEntityInterface $entity, $quantity, Context $context) {
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
