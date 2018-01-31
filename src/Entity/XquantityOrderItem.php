<?php

namespace Drupal\commerce_xquantity\Entity;

use Drupal\Core\Form\FormState;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\commerce_order\Entity\OrderItem;

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
    $step = isset($settings['#step']) && is_numeric($settings['#step']) ? $settings['#step'] + 0 : 1;
    $quantity = $this->getQuantity();
    return (string) is_int($step) ? $quantity : (is_float($step) && $quantity > 0 ? '1' : $quantity);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantityWidgetSettings() {
    $settings = [];
    $settings['#disable_on_cart'] = FALSE;
    // If 'Add to cart' form display mode is enabled we prefer its settings
    // because exactly those settings are exposed to and used by a customer.
    $form_display = entity_get_form_display($this->getEntityTypeId(), $this->bundle(), 'add_to_cart');
    $quantity = $form_display->getComponent('quantity');

    if (!$quantity) {
      $form_display = entity_get_form_display($this->getEntityTypeId(), $this->bundle(), 'default');
      $quantity = $form_display->getComponent('quantity');
    }

    if (isset($quantity['settings']['step'])) {
      $mode_settings = $form_display->getRenderer('quantity')->getFormDisplayModeSettings();
      foreach ($mode_settings as $key => $value) {
        $settings["#{$key}"] = $value;
      }
    }
    else {
      // If $settings has no 'step' it means that some unknown mode is used, so
      // $form_display->getRenderer('quantity')->getSettings() is useless here.
      // We use $quantity->defaultValuesForm() to get an array with #min, #max,
      // #step, #field_prefix, #field_suffix and #default_value elements.
      $form_state = new FormState();
      $form = [];
      $form = $this->get('quantity')->defaultValuesForm($form, $form_state);
      $settings += (array) NestedArray::getValue($form, ['widget', 0, 'value']);
      // Make prefix/suffix settings accessible through #prefix/#suffix keys.
      $settings['#prefix'] = isset($settings['#prefix']) ? $settings['#prefix'] : FALSE;
      $settings['#suffix'] = isset($settings['#suffix']) ? $settings['#suffix'] : FALSE;
      $settings['#prefix'] = $settings['#prefix'] ?: (isset($settings['#field_prefix']) ? $settings['#field_prefix'] : '');
      $settings['#suffix'] = $settings['#suffix'] ?: (isset($settings['#field_suffix']) ? $settings['#field_suffix'] : '');
    }

    return $settings;
  }

}
