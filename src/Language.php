<?php

declare(strict_types=1);

namespace Naneynonn;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

use Naneynonn\Memory;

use DirectoryIterator;

class Language
{
  private Translator $translator;

  public function __construct(string $defaultLocale = 'en')
  {
    $this->translator = new Translator($defaultLocale);
    $this->loadTranslations();
  }

  private function loadTranslations(): void
  {
    $directory = 'language';  // Или другой путь к вашему каталогу
    $loader = new YamlFileLoader();
    $this->translator->addLoader('yaml', $loader);

    foreach (new DirectoryIterator($directory) as $file) {
      if ($file->isDot() || !$file->isFile() || $file->getExtension() !== 'yml') {
        continue;
      }

      $filename = $file->getFilename();

      if (preg_match('/^messages\.(.+)\.yml$/', $filename, $matches)) {
        $locale = $matches[1];
        $this->translator->addResource('yaml', $file->getPathname(), $locale);
      } elseif (preg_match('/^messages\+intl-icu\.(.+)\.yml$/', $filename, $matches)) {
        $locale = $matches[1];
        $this->translator->addResource('yaml', $file->getPathname(), $locale, 'messages+intl-icu');
      }
    }

    // Установка резервного языка
    $this->translator->setFallbackLocales(['en']);
  }

  public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
  {
    return $this->translator->trans($id, $parameters, $domain, $locale);
  }

  public function setLocale(string $locale): void
  {
    $this->translator->setLocale($locale);
  }

  public function getLocale(): string
  {
    return $this->translator->getLocale();
  }

  public function __clone()
  {
    // Создаем новый экземпляр Translator
    $this->translator = new Translator($this->translator->getLocale());
    // Перезагружаем переводы для нового экземпляра
    $this->loadTranslations();
  }

  // Другие возможные методы для управления переводами
}
