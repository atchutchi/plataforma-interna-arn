<?php
/** Recusa PHPUnit parcial quando o iterador do filesystem omite ficheiros. */

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $root = dirname(__DIR__) . '/tests/phpunit';
    $expected = [];
    $scan = static function (string $directory) use (&$scan, &$expected): void {
        $entries = scandir($directory);
        if (false === $entries) {
            throw new RuntimeException('Directório de testes ilegível.');
        }
        foreach ($entries as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }
            $path = $directory . '/' . $entry;
            if (is_link($path)) {
                throw new RuntimeException('Ligação simbólica no catálogo de testes.');
            }
            if (is_dir($path)) {
                $scan($path);
            } elseif (str_ends_with($entry, 'Test.php')) {
                $resolved = realpath($path);
                if (false === $resolved || !is_file($path) || !is_readable($path)) {
                    throw new RuntimeException('Ficheiro de teste indisponível.');
                }
                $expected[] = $resolved;
            }
        }
    };
    $scan($root);
    sort($expected);
    $actual = (new SebastianBergmann\FileIterator\Facade())->getFilesAsArray($root, 'Test.php');
    if ([] === $expected || $expected !== $actual) {
        throw new RuntimeException(sprintf(
            'Descoberta PHPUnit incompleta: %d ficheiros esperados, %d encontrados. Validar numa cópia Linux com hashes conferidos se o mount Windows/WSL omitir entradas.',
            count($expected),
            count($actual)
        ));
    }
    fwrite(STDOUT, sprintf("Descoberta PHPUnit completa: %d ficheiros.\n", count($actual)));
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}
