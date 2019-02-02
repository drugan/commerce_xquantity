<?php

namespace Drupal\Tests\commerce_xquantity\Functional;

use Drupal\Tests\commerce_cart\Functional\CartBrowserTestBase;

/**
 * Tests functionality of the commerce_xquantity module.
 *
 * @covers \Drupal\commerce_xquantity\Form\XquantityAddTocartForm
 * @covers \Drupal\commerce_xquantity\Plugin\views\field\XquantityEditQuantity
 *
 * @group commerce
 */
class XquantityTest extends CartBrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'commerce_xquantity',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order_item form display',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests validation methods.
   *
   * @see XquantityAddTocartForm::validateForm()
   * @see XquantityEditQuantity::viewsFormValidate()
   */
  public function testAddToCartUpdateCartQuantity() {
    $product_url = $this->variation->getProduct()->toUrl();

    $this->drupalGet('admin/commerce/config/order-item-types/default/edit/form-display/add_to_cart');
    // By default, the quantity field is not enabled on the 'Add to cart' mode.
    $assert = $this->assertSession();
    $assert->pageTextNotContains('step: 0.01');
    $assert->pageTextNotContains('default_value: 1');
    $assert->pageTextNotContains('min: 0');
    $assert->pageTextNotContains('max: 9999999999.9999');
    $assert->pageTextNotContains('base_default_value: 1');
    $assert->pageTextNotContains('base_step: 0.0001');
    $assert->pageTextNotContains('floor: 0');
    $assert->pageTextNotContains('ceil: 9999999999.9999');

    $this->drupalGet('admin/commerce/config/order-item-types/default/edit/form-display');
    // The 'Default' form mode has quantity field enabled by default.
    $assert = $this->assertSession();
    $assert->pageTextContains('step: 1');
    $assert->pageTextContains('default_value: 1');
    $assert->pageTextContains('min: 1');
    $assert->pageTextContains('max: 9999999999.9999');
    $assert->pageTextContains('base_default_value: 1');
    $assert->pageTextContains('base_step: 0.0001');
    $assert->pageTextContains('floor: 0');
    $assert->pageTextContains('ceil: 9999999999.9999');

    // All the defaults. Quantity field is not exposed. Default value = 1.
    $this->drupalGet($product_url);
    $this->submitForm([], t('Add to cart'));

    $this->drupalGet('cart');
    $this->assertSession()->fieldValueEquals('edit-edit-quantity-0', '1');

    // Enable quantity on the 'Add to cart' form mode. Default step = 0.01.
    $widget = entity_get_form_display('commerce_order_item', 'default', 'add_to_cart');
    $widget->setComponent('quantity', [
      'type' => 'xnumber',
    ])->save();

    // A client always returns string numbers from the form's quantity, so it is
    // recommended in code to input string numbers. Otherwise unexpected results
    // might be displayed in the case of decimal, float or extra big integer
    // values.
    $this->drupalGet('cart');
    $this->assertSession()->fieldValueEquals('edit-edit-quantity-0', '1');
    $this->submitForm([
      'edit_quantity[0]' => '1.02',
    ], t('Update cart'));
    $this->assertSession()->pageTextContains(t('Your shopping cart has been updated.'));
    $this->assertSession()->fieldValueEquals('edit-edit-quantity-0', '1.02');

    // Attempt to update the order item with 0 quantity. Should be deleted.
    $this->submitForm([
      'edit_quantity[0]' => '0',
    ], t('Update cart'));
    $this->assertSession()->pageTextContains(t('Your shopping cart is empty.'));

    $widget->setComponent('quantity', [
      'settings' => ['default_value' => '1.0015', 'step' => '0.0005'],
    ])->save();

    $this->drupalGet($product_url);

    // Attempt add to cart the order item with empty quantity. As the min
    // property is not set (0), then the step should be dimmed as the minimal
    // allowed quantity.
    $this->submitForm([
      'quantity[0][value]' => '',
    ], t('Add to cart'));
    $this->assertSession()->pageTextContains(t('The quantity should be no less than 0.0005'));

    // Attempt add to cart the order item with 0 quantity.
    $this->submitForm([
      'quantity[0][value]' => '0',
    ], t('Add to cart'));
    $this->assertSession()->pageTextContains(t('The quantity should be no less than 0.0005'));

    $widget->setComponent('quantity', [
      'settings' => [
        'default_value' => '1.0015',
        'step' => '0.0005',
        'min' => '1.001',
      ],
    ])->save();

    $this->drupalGet($product_url);
    $this->submitForm([], t('Add to cart'));
    $this->assertSession()->pageTextContains(t('@label added to your cart.', ['@label' => $this->variation->label()]));

    // The min is set and should be displayed in the error message.
    $this->submitForm([
      'quantity[0][value]' => '',
    ], t('Add to cart'));
    $this->assertSession()->pageTextContains(t('The quantity should be no less than 1.001'));

    $this->submitForm([
      'quantity[0][value]' => '0',
    ], t('Add to cart'));
    $this->assertSession()->pageTextContains(t('The quantity should be no less than 1.001'));

    $this->drupalGet('cart');

    // As the min is set, now the order item is not removed with 0 quantity.
    // Instead, the error message with a minimal quantity is displayed.
    $values = [
      'edit_quantity[0]' => '0',
    ];
    $this->submitForm($values, t('Update cart'));
    $this->assertSession()->pageTextContains(t('Quantity must be higher than or equal to 1.001.'));

    // An attempt to update an order item with empty quantity does not change
    // anything.
    $values = [
      'edit_quantity[0]' => '',
    ];
    $this->submitForm($values, t('Update cart'));
    $this->assertSession()->fieldValueEquals('edit-edit-quantity-0', '1.0015');
  }

}
