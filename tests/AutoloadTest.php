<?php

declare(strict_types=1);

namespace Reevit\Tests;

use PHPUnit\Framework\TestCase;
use Reevit\Internal\ListEnvelope;
use Reevit\Reevit;
use Reevit\Webhooks\SignatureVerifier;

/**
 * Guards the published package against symbols that cannot be loaded.
 *
 * `src/Primeflow.php` used to declare `class Reevit extends Reevit` inside
 * `namespace Reevit` — a fatal on load, and under an optimised classmap a
 * fatal for the whole SDK. No test loaded it, so `php -l` (syntax only) let
 * it ship. These tests load every public entry point and check that the
 * `src/` tree maps one-to-one onto PSR-4 class names.
 */
final class AutoloadTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function publicClassProvider(): array
    {
        $classes = [
            Reevit::class,
            SignatureVerifier::class,
            ListEnvelope::class,
            \Reevit\Services\CheckoutSessionsService::class,
            \Reevit\Services\ConnectionsService::class,
            \Reevit\Services\CustomersService::class,
            \Reevit\Services\FraudService::class,
            \Reevit\Services\InvoicesService::class,
            \Reevit\Services\PaymentLinksService::class,
            \Reevit\Services\PaymentsService::class,
            \Reevit\Services\PayoutsService::class,
            \Reevit\Services\RoutingRulesService::class,
            \Reevit\Services\SubscriptionsService::class,
            \Reevit\Services\WebhooksService::class,
        ];

        $cases = [];
        foreach ($classes as $class) {
            $cases[$class] = [$class];
        }

        return $cases;
    }

    /**
     * @dataProvider publicClassProvider
     */
    public function testClassIsAutoloadable(string $class): void
    {
        $this->assertTrue(
            class_exists($class),
            sprintf('%s could not be autoloaded', $class)
        );
    }

    public function testClientCanBeConstructed(): void
    {
        $client = new Reevit('pfk_test_key', 'org_123');

        $this->assertInstanceOf(Reevit::class, $client);
        $this->assertInstanceOf(\Reevit\Services\PaymentsService::class, $client->payments);
    }

    public function testVersionConstantIsSemver(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Reevit::VERSION);
    }

    /**
     * Every class declared under `src/` must be unique and must live at the
     * PSR-4 path implied by its name. A duplicate declaration (or a class in
     * the wrong file) is exactly what an optimised classmap turns fatal.
     */
    public function testSourceTreeHasNoDuplicateOrMisplacedSymbols(): void
    {
        $srcDir = \dirname(__DIR__) . '/src';
        $declarations = [];

        /** @var iterable<\SplFileInfo> $files */
        $files = new \RegexIterator(
            new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($srcDir)),
            '/\.php$/'
        );

        foreach ($files as $file) {
            $path = $file->getPathname();
            foreach (self::declaredClassesIn($path) as $class) {
                $declarations[$class][] = $path;
            }
        }

        $this->assertNotEmpty($declarations, 'no classes were found under src/');

        foreach ($declarations as $class => $paths) {
            $this->assertCount(
                1,
                $paths,
                sprintf('%s is declared more than once: %s', $class, implode(', ', $paths))
            );

            $relative = str_replace('\\', '/', substr($class, \strlen('Reevit\\'))) . '.php';
            $this->assertSame(
                $srcDir . '/' . $relative,
                $paths[0],
                sprintf('%s does not follow the PSR-4 mapping for Reevit\\ => src/', $class)
            );

            $this->assertTrue(class_exists($class) || interface_exists($class) || trait_exists($class));
        }
    }

    /**
     * @return list<string>
     */
    private static function declaredClassesIn(string $path): array
    {
        $tokens = token_get_all((string) file_get_contents($path));
        $namespace = '';
        $classes = [];
        $count = \count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!\is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                $namespace = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j] === ';' || $tokens[$j] === '{') {
                        break;
                    }
                    if (\is_array($tokens[$j]) && $tokens[$j][0] !== T_WHITESPACE) {
                        $namespace .= $tokens[$j][1];
                    }
                }
                continue;
            }

            if ($token[0] === T_CLASS || $token[0] === T_INTERFACE || $token[0] === T_TRAIT) {
                // Skip `::class` and anonymous classes.
                $previous = $tokens[$i - 1] ?? null;
                if (\is_array($previous) && $previous[0] === T_DOUBLE_COLON) {
                    continue;
                }

                for ($j = $i + 1; $j < $count; $j++) {
                    if (\is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                        continue;
                    }
                    if (\is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                        $classes[] = $namespace === '' ? $tokens[$j][1] : $namespace . '\\' . $tokens[$j][1];
                    }
                    break;
                }
            }
        }

        return $classes;
    }
}
