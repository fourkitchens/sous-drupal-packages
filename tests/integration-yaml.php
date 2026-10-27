<?php

declare(strict_types=1);

/** Parse every package YAML using Drupal's actual Symfony dependency. */

use Symfony\Component\Yaml\Yaml;

require $argv[1];
$packages = realpath($argv[2]);
$web = realpath($argv[3]);
if (!$packages || !$web) {
  throw new InvalidArgumentException('Usage: php integration-yaml.php vendor/autoload.php packages web');
}
$count = 0;
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($packages, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
  if ($file->getExtension() !== 'yml') {
    continue;
  }
  $data = Yaml::parseFile($file->getPathname());
  if (!is_array($data)) {
    throw new RuntimeException('Expected a YAML mapping: ' . $file);
  }
  $count++;
  if ($file->getFilename() !== 'recipe.yml') {
    continue;
  }
  if (!is_string($data['name'] ?? NULL) || !is_array($data['recipes'] ?? [])) {
    throw new RuntimeException('Invalid recipe metadata: ' . $file);
  }
  $package = explode(DIRECTORY_SEPARATOR, substr($file->getPathname(), strlen($packages) + 1))[0];
  $manifest = json_decode(file_get_contents($packages . '/' . $package . '/composer.json'), TRUE, 512, JSON_THROW_ON_ERROR);
  foreach ($data['recipes'] ?? [] as $include) {
    if (!is_string($include) || !preg_match('@^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$@', $include)) {
      throw new RuntimeException('Unsafe recipe include in ' . $file);
    }
    if (str_starts_with($include, 'core/recipes/')) {
      $target = $web . '/' . $include;
    }
    else {
      $parts = explode('/', $include);
      if ($parts[0] === 'recipes') {
        array_shift($parts);
      }
      $dependency = array_shift($parts);
      if ($dependency !== $package && !isset($manifest['require']['fourkitchens/' . $dependency])) {
        throw new RuntimeException('Recipe include has no direct Composer dependency: ' . $file . ' -> ' . $include);
      }
      $target = $packages . '/' . $dependency . ($parts ? '/' . implode('/', $parts) : '');
    }
    if (!is_file($target . '/recipe.yml')) {
      throw new RuntimeException('Recipe include cannot resolve: ' . $file . ' -> ' . $include);
    }
  }
}
print 'PASS: native Symfony YAML syntax and contained/declared recipe includes across ' . $count . " files.\n";
