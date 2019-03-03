<?php

namespace Drupal\xquantity_stock\Plugin\Field\FieldType;

use Drupal\xnumber\Plugin\Field\FieldType\XdecimalItem;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\xnumber\Utility\Xnumber as Numeric;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'xquantity_stock' field type.
 *
 * @FieldType(
 *   id = "xquantity_stock",
 *   label = @Translation("Xquantity Stock (decimal)"),
 *   description = @Translation("This field stores a commerce product variation stock quantity."),
 *   category = @Translation("Number"),
 *   default_widget = "xquantity_stock",
 *   default_formatter = "xquantity_stock"
 * )
 */
class XquantityStockItem extends XdecimalItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'threshold' => '1800',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::fieldSettingsForm($form, $form_state);
    $settings = $this->getQuantityWidgetSettings();
    if (!empty($settings['step'])) {
      $element['step']['#step'] = $settings['step'];
      $element['min']['#step'] = $settings['step'];
      $element['max']['#step'] = $settings['step'];
    }
    $element['step']['#min'] = $settings['step'];
    $min = $settings['min'];
    $min = (!is_numeric($min) || ($min < 0)) && $settings['unsigned'] ? '0' : $min;
    $element['min']['#min'] = $min;
    $element['max']['#min'] = $min;

    $element['threshold'] = [
      '#type' => 'number',
      '#step' => '1',
      '#field_suffix' => $this->t('seconds', [], ['context' => 'xquantity stock']),
      '#title' => $this->t('Threshold', [], ['context' => 'xquantity stock']),
      '#description' => $this->t('Stock rotation threshold. Read more: <a href=":href" target="_blank">admin/help/xquantity_stock#stock-rotation</a>', [
        ':href' => '/admin/help/xquantity_stock#stock-rotation',
      ]),
      '#default_value' => $settings['threshold'],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuantityWidgetSettings() {
    $settings = [];
    // If 'Add to cart' form display mode is enabled we prefer its settings
    // because exactly those settings are exposed to and used by a customer.
    $type_id = $this->getEntity()->getOrderItemTypeId();
    $form_display = \Drupal::entityTypeManager()->getStorage('entity_form_display');
    $form_display_mode = $form_display->load("commerce_order_item.{$type_id}.add_to_cart");
    $quantity = $form_display_mode ? $form_display_mode->getComponent('quantity') : NULL;

    if (!$quantity) {
      $form_display_mode = $form_display->load("commerce_order_item.{$type_id}.default");
      $quantity = $form_display_mode ? $form_display_mode->getComponent('quantity') : NULL;
    }

    if (isset($quantity['settings']['step'])) {
      $settings = $form_display_mode->getRenderer('quantity')->getFormDisplayModeSettings();
    }

    return $settings + $this->getSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $settings = $field_definition->getSettings();
    $precision = $settings['precision'] ?: 10;
    $scale = $settings['scale'] ?: 2;
    $max = '10';
    $min = $settings['min'] > 0 ? $settings['min'] : ($settings['unsigned'] ? pow(0.1, $scale) : '-10');
    // Get the number of decimal digits for the $max.
    $decimal_digits = Numeric::getDecimalDigits($max);
    // Do the same for the min and keep the higher number of decimal digits.
    $decimal_digits = max(Numeric::getDecimalDigits($min), $decimal_digits);
    // If $min = 1.234 and $max = 1.33 then $decimal_digits = 3.
    $scale = rand($decimal_digits, $scale);

    // @see "Example #1 Calculate a random floating-point number" in
    // http://php.net/manual/function.mt-getrandmax.php
    $random_decimal = $min + mt_rand() / mt_getrandmax() * ($max - $min);
    $values['value'] = Numeric::truncateDecimal($random_decimal, $scale);

    return $values;
  }

}
