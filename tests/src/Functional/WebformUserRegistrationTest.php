<?php

namespace Drupal\Tests\webform_user_registration\Functional;

use Drupal\Tests\webform\Functional\WebformBrowserTestBase;

/**
 * Functional tests for the webform_user_registration webform plugin.
 *
 * @group webform_user_registration
 */
class WebformUserRegistrationTest extends WebformBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'user',
    'webform_user_registration_test',
    'webform',
  ];

  /**
   * {@inheritdoc}
   */
  protected static $testWebforms = [
    'webform_user_registration_test',
  ];

  /**
   * Webform Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface*/
  protected $webformEntity;

  /**
   * Test user registration without admin approval/email verification.
   */
  public function testUserRegistration() {
    $webform = $this->loadWebform('webform_user_registration_test');

    // Allow registration by site visitors without administrator approval.
    $edit = [
      'first_name' => 'Dru',
      'last_name' => 'Paul',
      'email' => 'dru.paul@example.com',
      'phone' => '123-456-7890',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertSession()->responseContains(t('Registration successful. You are now logged in.'));
    $this->drupalLogout();
  }

  /**
   * Test user registration with email verification.
   */
  public function testUserRegistrationWithEmailVerification() {
    $config = $this->config('webform.webform.webform_user_registration_test');
    $webform = $this->loadWebform('webform_user_registration_test');

    // Require email verification.
    $config->set('handlers.user_registration.settings.create_user.email_verification', TRUE)->save();

    // Allow registration by site visitors without administrator approval.
    $edit = [
      'first_name' => 'Dru',
      'last_name' => 'Paul',
      'email' => 'dru.paul@example.com',
      'phone' => '123-456-7890',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertSession()->responseContains(t('A welcome message with further instructions has been sent to your email address.'));

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $account_name = str_replace('@', '.', $edit['email']);
    $accounts = $storage->loadByProperties(['name' => $account_name, 'mail' => $edit['email']]);
    $new_user = reset($accounts);

    // Assert new user is active.
    $this->assertTrue($new_user->isActive(), 'New account is active after registration.');

    // Has phone number.
    $this->assertEqual($new_user->field_phone->value, $edit['phone']);

    // And can set password.
    $resetURL = user_pass_reset_url($new_user);
    $this->drupalGet($resetURL);
    $this->assertSession()->responseContains(t('Set password | Drupal'));
  }

  /**
   * Test user registration with admin approval.
   */
  public function testUserRegistrationWithAdminApproval() {
    $config = $this->config('webform.webform.webform_user_registration_test');
    $webform = $this->loadWebform('webform_user_registration_test');

    // Require email verification.
    $config->set('handlers.user_registration.settings.create_user.admin_approval', TRUE)->save();

    // Allow registration by site visitors without administrator approval.
    $edit = [
      'first_name' => 'Dru',
      'last_name' => 'Paul',
      'email' => 'dru.paul@example.com',
      'phone' => '123-456-7890',
    ];
    $sid = $this->postSubmission($webform, $edit);
    $this->assertSession()->responseContains(t('Your account is pending approval.'));

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $account_name = str_replace('@', '.', $edit['email']);
    $accounts = $storage->loadByProperties(['name' => $account_name, 'mail' => $edit['email']]);
    $new_user = reset($accounts);
    $this->assertFalse($new_user->isActive(), 'New account is blocked until approved by an administrator.');
  }

  /**
   * Test user account update behavior for authenticated users.
   */
  public function testUpdateUserAccount() {
    // Start a user session.
    $this->drupalLogin($this->createUser(['access content'], 'dru.paul'));

    $webform = $this->loadWebform('webform_user_registration_test');

    // Assert user fields have been updated.
    $edit = [
      'first_name' => 'Dru',
      'last_name' => 'Paul',
      'email' => 'dru.paul_new@example.com',
      'phone' => '206-123-4567',
    ];
    $sid = $this->postSubmission($webform, $edit);

    /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
    $storage = $this->container->get('entity_type.manager')->getStorage('user');
    $accounts = $storage->loadByProperties(['name' => 'dru.paul']);
    $user = reset($accounts);

    // Assert email was not changed.
    $this->assertNotEqual($user->getEmail(), $edit['email'], 'E-mail cannot be updated.');

    // Assert custom fields are updated.
    $this->assertEqual($user->field_phone->value, $edit['phone']);
  }

}
