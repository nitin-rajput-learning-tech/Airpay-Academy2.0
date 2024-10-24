@local @local_biz_cart @javascript

Feature: Test purchase process in shopping cart.
  In order to buy an item
  As a student
  I need to put an item in my cart and proceed to checkout

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | user1    | Username  | 1        |
      | user2    | Username  | 2        |
      | teacher  | Teacher   | 3        |
      | manager  | Manager   | 4        |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | user1    | C1     | student        |
      | user2    | C1     | student        |
      | teacher  | C1     | editingteacher |
    And the following "core_payment > payment accounts" exist:
      | name           |
      | Account1       |
    And the following "local_biz_cart > payment gateways" exist:
      | account  | gateway | enabled | config                                                                                |
      | Account1 | paypal  | 1       | {"brandname":"Test paypal","clientid":"Test","secret":"Test","environment":"sandbox"} |
    And the following "local_biz_cart > plugin setup" exist:
      | account  |
      | Account1 |

  @javascript
  Scenario: Add an item to the shopping cart
    Given I log in as "user1"
    And I visit "/local/biz_cart/test.php"
    And I wait until the page is ready
    And I click on "#btn-local_biz_cart-main-1" "css_element"
    And I click on "#nav-biz_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    And I should see "10.00" in the "ul.shopping-cart-items" "css_element"
    And I reload the page
    And I wait until the page is ready
    And I click on "#nav-biz_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    And I should see "10.00" in the "ul.shopping-cart-items" "css_element"
    And I wait "1" seconds
    And I click on ".popover-region-content-container a" "css_element"
    Then I should see "my test item 1" in the "div.checkoutgrid" "css_element"
    And I should see "10.00" in the "div.checkoutgrid" "css_element"

  @javascript
  Scenario: Delete item from the shopping cart
    Given I log in as "user1"
    And I visit "/local/biz_cart/test.php"
    And I wait until the page is ready
    And I click on "#btn-local_biz_cart-main-1" "css_element"
    And I click on "#nav-biz_cart-popover-container" "css_element"
    Then I should see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    ## And I click on "[data-item=\"biz_cart_item\"] i.fa.fa-trash-o" "css_element"
    And I click on "[data-itemid=\"1\"] i.fa.fa-trash-o" "css_element"
    And I wait "1" seconds
    Then I should not see "my test item 1" in the "ul.shopping-cart-items" "css_element"
    And I reload the page
    And I wait until the page is ready
    And I click on "#nav-biz_cart-popover-container" "css_element"
    Then I should not see "my test item 1" in the "ul.shopping-cart-items" "css_element"
