<?php

namespace Drupal\eu_cookie_compliance\Service;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

class ScriptFileManager {
  use StringTranslationTrait;

  /**
   * @var string
   */
  protected string $directory;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected MessengerInterface $messenger;

  /**
   * The file name of the script being saved to.
   * @var string
   *
   */
  protected string $fileName;

  /**
   * Holds the processed disabling script string
   * @var string
   *
   */
  protected string $generatedScript;

  /**
   * @var \Drupal\eu_cookie_compliance\Service\DisabledJavascriptManager
   */
  protected DisabledJavascriptManager $disabledJavascriptManger;

  public function __construct(
    FileSystemInterface $file_system,
    MessengerInterface $messenger,
    DisabledJavascriptManager $disabled_javascript_manager
  ) {
    $this->fileSystem = $file_system;
    $this->messenger = $messenger;
    /* Set this by default, they can over-write it later if needed */
    $this->directory = "public://eu_cookie_compliance";
    $this->fileName = "eu_cookie_compliance.script.js";
    $this->disabledJavascriptManger = $disabled_javascript_manager;
  }

  public function setDirectory($directory): void {
    if (!empty($directory)) {
      $this->directory = $directory;
    }
  }

  public function setFileName($filename): void {
    if (!empty($filename)) {
      $this->fileName = $filename;
    }
  }

  private function absolutePath(): string {
    return $this->directory . '/' . $this->fileName;
  }

  /**
   * Build a disabled javascript snippet.
   *
   * @param string $disabled_javascripts
   *   A non-empty, URL-encoded string of JavaScript file references
   *
   * @return self
   *   $this for method chaining.
   */
  public function buildDisabledJsScript(string $disabled_javascripts) {
    $load_disabled_scripts = [];

    $disabled_items = $this->disabledJavascriptManger->parse($disabled_javascripts);

    foreach ($disabled_items['disabled_js'] as $disabled_javascript) {
      $category = $disabled_javascript['category'];
      $attach_name = $disabled_javascript['attach_name'];
      $library_name = $disabled_javascript['library_name'];

      $scripts_to_load = [$disabled_javascript['script']];
      if ($library_name) {
        // Load the library dependencies.
        $library_scripts = $this->disabledJavascriptManger->collect_js(['library' => [$library_name]]);
        $scripts_to_load = array_merge($scripts_to_load, $library_scripts);
        $scripts_to_load = array_unique($scripts_to_load);
      }

      foreach ($scripts_to_load as $script) {
        if (empty($script)) {
          continue;
        }
        if (!UrlHelper::isExternal($script)) {
          $script = '/' . $script;
        }

        $load_disabled_script = [
          'src' => $script,
        ];
        if ($category !== NULL) {
          $load_disabled_script['categoryName'] = $category;
        }
        if (!empty($attach_name)) {
          $load_disabled_script['attachName'] = $attach_name;
        }

        $load_disabled_scripts[$script] = $load_disabled_script;
      }
    }

    $disabled_json_list = json_encode(array_values($load_disabled_scripts),JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $this->generatedScript = <<<JS
    function createSnippet(scriptToLoad) {
      const {src, attachName} = scriptToLoad;
      const tag = document.createElement('script');
      tag.src = decodeURI(src);
      if (attachName) {
        const intervalId = setInterval(() => {
          if (Drupal.behaviors[attachName]) {
            Drupal.behaviors[attachName].attach(document, drupalSettings);
            clearInterval(intervalId);
          }
        }, 100);
      }
      document.body.appendChild(tag);
    }
    window.euCookieComplianceLoadScripts = function(category) {
      const unverifiedScripts = drupalSettings.eu_cookie_compliance.unverified_scripts;
      const scriptList = {$disabled_json_list};

      scriptList.forEach(scriptToLoad => {
        const {src, categoryName} = scriptToLoad;
        if (!unverifiedScripts.includes(src)) {
          if (!category && !categoryName) {
            // Load scripts without any categories specified.
            createSnippet(scriptToLoad);
          } else if (categoryName === category) {
            // Load scripts for the specific category.
            createSnippet(scriptToLoad);
          }
        }
      });
    }
    JS;

    return $this;
  }

  /**
   * Saves the disabling javascript snippet to a file
   *
   */
  public function save(): bool {
    if (!is_dir($this->directory) || !is_writable($this->directory)) {
      $this->fileSystem->prepareDirectory($this->directory,
        FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    }

    if (is_writable($this->directory)) {
      if ((float) \Drupal::VERSION < 10.3) {
        $this->fileSystem->saveData($this->generatedScript, $this->absolutePath(), FileSystemInterface::EXISTS_REPLACE);
      } else {
        $this->fileSystem->saveData($this->generatedScript, $this->absolutePath(), FileExists::Replace);
      }
    } else {
      $this->messenger->addError($this->t('Could not generate the EU Cookie Compliance JavaScript file that would be used for handling disabled JavaScripts. There may be a problem with your files folder.'));
      return false;
    }
    return true;
  }

  public function delete(): bool {
    if (!empty($this->absolutePath())) {
      return $this->fileSystem->delete($this->absolutePath());
    }
    return false;
  }
}
