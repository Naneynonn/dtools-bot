<?php

namespace Naneynonn;

class Language
{
  private const DEFAULT_LANG = 'en';
  private const DIR_LANG = 'lang/';
  private const GLOBAL_FILE = 'global';

  private string $lang;
  private array $langArray;

  public function __construct(?string $lang = null)
  {
    $this->lang = $lang ?? self::DEFAULT_LANG;
    $this->langArray = $this->loadLangArray();
  }

  private function loadLangArray(): array
  {
    $lang_array = $this->load_lang(lang: $this->lang);
    $default_lang_array = $this->load_lang(lang: self::DEFAULT_LANG);
    $global_array = $this->load_lang(lang: self::GLOBAL_FILE);

    return array_replace_recursive($default_lang_array, $lang_array, $global_array);
  }

  public function get(string $key, ...$args): string
  {
    $value = $this->getValue(key: $key, array: $this->langArray);
    if (!empty($args)) {
      $argValues = array_map(function ($arg) use ($key) {
        return $this->getValue(key: $arg, array: $this->langArray);
      }, $args);
      $value = vsprintf($value, $argValues);
    }
    return $value;
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
