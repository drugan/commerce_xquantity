<?php

namespace Drupal\xquantity_stock\Plugin\Field\FieldType;

use Drupal\xnumber\Plugin\Field\FieldType\XdecimalItem;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\xnumber\Utility\Xnumber as Numeric;

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
