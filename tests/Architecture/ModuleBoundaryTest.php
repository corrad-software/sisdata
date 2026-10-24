<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ADR-03 modular monolith: a module may reference another module only
 * through that module's Contracts\ or Events\ namespace. Reaching into
 * another module's Models, Services, Http… couples them and is rejected.
 */
class ModuleBoundaryTest extends TestCase
{
    private const ALLOWED_FOREIGN_NAMESPACES = ['Contracts', 'Events'];

    public function test_every_enabled_module_lives_under_app_modules(): void
    {
        $config = require __DIR__.'/../../config/modules.php';

        foreach ($config['enabled'] as $provider) {
            $this->assertMatchesRegularExpression('/^App\\\\Modules\\\\[A-Z][A-Za-z]+\\\\[A-Za-z]+ServiceProvider$/', $provider);
            $this->assertTrue(class_exists($provider), "{$provider} is enabled but does not exist");
        }
    }

    #[DataProvider('moduleFiles')]
    public function test_module_only_uses_other_modules_through_contracts_or_events(string $module, string $file): void
    {
        $source = file_get_contents($file);
        $this->assertNotFalse($source);

        preg_match_all('/\\\\?App\\\\Modules\\\\([A-Z][A-Za-z]+)\\\\([A-Za-z]+)/', $source, $matches, PREG_SET_ORDER);

        foreach ($matches as [$reference, $target, $namespace]) {
            if ($target === $module) {
                continue;
            }

            $this->assertContains(
                $namespace,
                self::ALLOWED_FOREIGN_NAMESPACES,
                sprintf('%s references %s — modules may only use another module\'s Contracts\\ or Events\\.', str_replace(dirname(__DIR__, 2).'/', '', $file), ltrim($reference, '\\')),
            );
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function moduleFiles(): iterable
    {
        $root = dirname(__DIR__, 2).'/app/Modules';

        foreach (glob($root.'/*', GLOB_ONLYDIR) as $dir) {
            $module = basename($dir);

            // Plain SPL iterator: data providers run before the app (and its facades) boot.
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() === 'php') {
                    yield $module.'/'.substr($file->getPathname(), strlen($dir) + 1) => [$module, $file->getPathname()];
                }
            }
        }
    }
}
