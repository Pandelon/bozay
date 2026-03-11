<?php

/**
 * @file
 * Post update functions for Eu Cookie Compliance.
 */

use Drupal\user\Entity\Role;

/**
 * @addtogroup updates-8.x-1.0-beta5-to-8.x-1.0-beta6
 * @{
 */

/**
 * Update permissions keys to standardize permission machine name.
 */
function eu_cookie_compliance_post_update_permission_keys_to_lowercase() {
  /** @var \Drupal\user\RoleInterface $role */
  foreach (Role::loadMultiple() as $role) {
    if ($role->hasPermission('administer EU Cookie Compliance popup')) {
      $role->revokePermission('administer EU Cookie Compliance popup');
      $role->grantPermission('administer eu cookie compliance popup');
    }
    if ($role->hasPermission('display EU Cookie Compliance popup')) {
      $role->revokePermission('display EU Cookie Compliance popup');
      $role->grantPermission('display eu cookie compliance popup');
    }
    $role->save();
  }
}

/**
 * @} End of "addtogroup updates-8.x-1.0-beta5-to-8.x-1.0-beta6".
 */

/**
 * Update configuration key from whitelist to allowlist.
 */
function eu_cookie_compliance_post_update_whitelist_to_allowlist() {
  $configuration = \Drupal::configFactory()->getEditable('eu_cookie_compliance.settings');

  $configuration->set('allowed_cookies', $configuration->get('whitelisted_cookies'));
  $configuration->clear('whitelisted_cookies');
  $configuration->save();
}

/**
 * Update script loader so it picks up category scripts properly.
 */
function eu_cookie_compliance_post_update_load_category_scripts() {
  return _eu_cookie_compliance_regenerate_disabled_javascript_loader();
}

/**
 * Regenerate disabled javascript loader script.
 */
function _eu_cookie_compliance_regenerate_disabled_javascript_loader(bool $delete_script = FALSE) {
  $config = Drupal::config('eu_cookie_compliance.settings');
  $disabled_javascripts = $config->get('disabled_javascripts');
  if (!empty($disabled_javascripts)) {
    /** @var \Drupal\eu_cookie_compliance\Service\ScriptFileManager $script_manager */
    $script_manager = \Drupal::service('eu_cookie_compliance.script_file_manager');
    $saved = $script_manager->buildDisabledJsScript($disabled_javascripts)->save();
    if ($saved) {
      $message = 'Updated the disabled javascript loader.';
      \Drupal::logger('eucc')->info($message);
      return $message;
    }

    $message = 'Failed to update the disabled javascript loader script, you should visit the settings page immediately.';
    \Drupal::logger('eucc')->warning($message);
    return $message;
  }
  if ($delete_script) {
    /** @var \Drupal\eu_cookie_compliance\Service\ScriptFileManager $script_manager */
    $script_manager = \Drupal::service('eu_cookie_compliance.script_file_manager');
    $saved = $script_manager->delete();
    $message = $saved ?
      'Deleted the disabled javascript loader script.' :
      'Failed to delete the disabled javascript loader script, there may be problems with your file system permissions.';
    \Drupal::logger('eucc')->info($message);
    return $message;
  }
  return NULL;
}
