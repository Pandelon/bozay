<?php

namespace Drupal\eu_cookie_compliance\Service;

/**
 * Parses the disabled JavaScript configuration.
 */
class DisabledJavascriptManager {

  /**
   * Splits a return delimited text string into an array.
   *
   * @param string $text
   *   Text to split.
   *
   * @return array
   *   Text split into an array.
   */
  private function explode_multiple_lines($text) {
    $text = explode("\r\n", $text ?? "");
    if (count($text) == 1) {
      $text = explode("\r", $text[0]);
    }
    if (count($text) == 1) {
      $text = explode("\n", $text[0]);
    }

    array_walk($text, [$this, 'convert_relative_uri']);
    return $text;
  }

  /**
   * Convert uri to relative path.
   *
   * Example public://file.js to /sites/default/files/file.js.
   *
   * @param string $element
   *   Url to transform.
   */
  private function convert_relative_uri(&$element) {
    if ((float) \Drupal::VERSION < 9.3) {
      $element = preg_replace('/^\//', '', file_url_transform_relative(file_create_url($element)));
    }
    else {
      $url = \Drupal::service('file_url_generator')->generateString($element);
      $element = preg_replace('/^\//', '', $url);
    }
  }

  /**
   * Collects all JS from the library and html_head attachments.
   *
   * @param array $attachments
   *   The render array attachments, ie $attachments['#attached'].
   *
   * @return array
   *   An array of JavaScript file paths (external URLs or relative paths).
   */
  public function collect_js(array $attachments): array {
    $all_js = [];

    // Process modules attaching via library
    if (!empty($attachments['library'])) {
      $library_discovery = \Drupal::service('library.discovery');
      foreach ($attachments['library'] as $library) {
        [$extension, $name] = explode('/', $library);
        /* TODO: If the extension isn't installed, and the admin is trying to
           disable the javascript for it, there is a User warning that it is
           missing from the file system, should we catch this? */
        $lib = $library_discovery->getLibraryByName($extension, $name);
        if (!empty($lib['js'])) {
          foreach ($lib['js'] as $js) {
            $all_js[] = $js['data'];
          }
        }
      }
    }

    // Some modules attach via the html_head, process those scripts
    if (!empty($attachments['html_head'])) {
      foreach ($attachments['html_head'] as $item) {
        $element = $item[0];
        if (!empty($element['#tag']) && $element['#tag'] === 'script') {
          $src = $element['#attributes']['src'] ?? null;
          if ($src) {
            // Strip query string if present
            $src = strtok($src, '?');
            $this->convert_relative_uri($src);
            $all_js[] = $src;
          }
        }
      }
    }

    return $all_js;
  }

  /**
   * Parse disabled javascript configuration.
   *
   * @param string $disabled_js
   *   The disabled javascript configuration.
   *
   * @return array{disabled_js: array<array{script: string, category: string, attach_name: string, library_name: string}>, disabled_libraries: string[]}
   *   A list of the disabled javascript and libraries.
   */
  public function parse(string $disabled_js): array {
    $disabled_javascripts = $this->explode_multiple_lines($disabled_js);
    $disabled_javascripts = array_filter($disabled_javascripts);

    $result = [
      'disabled_js' => [],
      'disabled_libraries' => [],
    ];

    foreach ($disabled_javascripts as $script) {
      if (empty($script)) {
        continue;
      }
      // Decode first, to normalize:
      $script_name = urldecode($script);

      // Retrieve the 'category:' if present.
      $parts = explode(':', $script_name);
      $category = NULL;
      if (count($parts) > 1) {
        // Take into account scripts with ':' present, such as https://.
        if (count($parts) > 2 || !in_array($parts[0], ['http', 'https'], TRUE)) {
          $category = array_shift($parts);
        }
        $script_name = implode(':', $parts);
      }

      @[$script_name, $attach_name, $library_name] = explode('|', $script_name);
      $this->convert_relative_uri($script_name);

      // Remove URL decoding from the script url.
      $script_name = urldecode(urldecode($script_name));

      $disabled_javascript = [
        'script' => $script_name,
        'category' => $category,
        'attach_name' => $attach_name,
        'library_name' => $library_name,
      ];
      $result['disabled_js'][] = $disabled_javascript;
      if ($library_name) {
        $result['disabled_libraries'][] = $library_name;
      }
    }

    return $result;
  }

}