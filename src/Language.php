<?php

namespace Naneynonn;

class Language
{
  private const DEFAULT_LANG = 'en';
  private const DIR_LANG = 'lang/';
  private const GLOBAL_FILE = 'global';

  private string $lang;

  public function __construct(?string $lang = null)
  {
    $this->lang = $lang ?? self::DEFAULT_LANG;
  }

  public function get(string $key): string
  {
    $lang_array = $this->load_lang(lang: $this->lang);
    $default_lang_array = $this->load_lang(lang: self::DEFAULT_LANG);
    $global_array = $this->load_lang(lang: self::GLOBAL_FILE);

    $merged_array = array_replace_recursive($default_lang_array, $lang_array, $global_array);

    return $this->getValue(key: $key, array: $merged_array);
  }

  public function get_global(string $key): string
  {
    $global_array = $this->load_lang(lang: self::GLOBAL_FILE);

    return $this->getValue(key: $key, array: $global_array);
  }

  private function getValue(string $key, array $array): string
  {
    $keys = explode('.', $key);
    $current = $array;
    foreach ($keys as $k) {
      $current = $current[$k] ?? null;
    }
    return $current ?? '';
  }

  private function load_lang(string $lang): array
  {
    $file = self::DIR_LANG . $lang . '.php';
    return require $file ?? [];
  }
}
