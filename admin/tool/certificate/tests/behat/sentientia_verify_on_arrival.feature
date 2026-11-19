# SENTIENTIA-CORE-MOD: Sentientia-owned scenarios for the verify-on-arrival vendor patch in index.php. Not an
# upstream file: keep it when a tool_certificate release is imported. See
# moodle-enhancement/docs/core-mods/2026-11-19-tool-certificate-verify-on-arrival.md and ADR-041.
@tool @tool_certificate
Feature: A certificate verification link shows the result on arrival
  In order to check a certificate from its QR code, the certificate gallery or a LinkedIn post
  As a visitor who may not be logged in
  I need the verification page to show the result without pressing Verify again

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following certificate templates exist:
      | name          |
      | Certificate 1 |
    And the following certificate issues exist:
      | template      | user     | code         |
      | Certificate 1 | student1 | ABCDEF123456 |

  Scenario: A visitor who is not logged in opens a link with a valid code
    When I visit "/admin/tool/certificate/index.php?code=ABCDEF123456"
    Then I should see "Valid"
    And I should see "Student 1"
    And I should see "Certificate 1"
    And "input[type=hidden][name=code]" "css_element" should not exist

  Scenario: A visitor who is not logged in opens a link with an unknown code
    When I visit "/admin/tool/certificate/index.php?code=NOTAVALIDCODE"
    Then I should see "Not verified"
    And I should not see "Student 1"

  # Before the patch the page a link opened carried a second, hidden "code" input, and editing the field showed a
  # false "Required" (the change-time check read an empty value). The submit-time check never blocked the press, so
  # this scenario covers the submit path on such a page; the hidden-input check in the first scenario catches the defect.
  @javascript
  Scenario: A logged-in user types another code on the page a link opened and presses Verify
    Given I log in as "student1"
    When I visit "/admin/tool/certificate/index.php?code=NOTAVALIDCODE"
    And I should see "Not verified"
    And I set the field "Code" to "ABCDEF123456"
    And I press "Verify"
    Then I should see "Valid"
    And I should not see "Not verified"
