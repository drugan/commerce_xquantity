<?php

namespace Drupal\xquantity_stock\Plugin\Field\FieldType;

use Drupal\xnumber\Plugin\Field\FieldType\XdecimalItem;

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
}
