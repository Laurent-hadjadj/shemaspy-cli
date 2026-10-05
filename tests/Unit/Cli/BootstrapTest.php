<?php

/**
 *  Ma-Moulinette - ShemaSpy-Cli
 *  --------------
 *  Copyright (c) 2015-2026.
 *  Laurent HADJADJ <laurent_h@me.com>.
 *  Licensed Creative Common  CC-BY-NC-SA 4.0.
 *  ---
 *  Vous pouvez obtenir une copie de la licence à l'adresse suivante :
 *  http://creativecommons.org/licenses/by-nc-sa/4.0/
 */

namespace SchemaSpyCli\Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\ProcessRunner;

/**
 * [Description BootstrapTest]
 * Point d'entrée src/bootstrap.php, exécuté en sous-processus : recherche de l'autoloader Composer
 * (ordre des emplacements), message et code de sortie quand il est absent, code de retour de l'application.
 *
 * Le fichier est COPIÉ dans un dossier temporaire avec un faux autoloader : le vrai Application n'est
 * jamais lancé (son constructeur réécrit logs/schemaspy-cli.log).
 */
final class BootstrapTest extends TestCase
{
    private TempDir $tmp;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    /** Copie bootstrap.php à $relativeDir/src/bootstrap.php et retourne son chemin. */
    private function installBootstrap(string $relativeDir): string
    {
        $target = $this->tmp->path . '/' . $relativeDir . '/src/bootstrap.php';

        $this->tmp->file(
            "{$relativeDir}/src/bootstrap.php",
            (string) file_get_contents(dirname(__DIR__, 3) . '/src/bootstrap.php')
        );

        return $target;
    }

    /** Faux autoloader : définit un Application factice qui s'identifie et retourne le code voulu. */
    private function fakeAutoloader(string $path, string $label, int $exitCode): void
    {
        $this->tmp->file($path, "<?php\nnamespace SchemaSpyCli\\Cli;\nfinal class Application\n{\n    public function run(): int\n    {\n        echo \"{$label}\\n\";\n        return {$exitCode};\n    }\n}\n");
    }

    /** @return array{0: int, 1: string} [code de sortie, sortie standard + erreur] */
    private function execute(string $script): array
    {
        $output = '';
        $exit = (new ProcessRunner())->run(
            escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script),
            function (string $text) use (&$output): void {
                $output .= $text;
            }
        );

        return [$exit, $output];
    }

    public function testMissingAutoloaderIsAnErrorWithAHelpfulMessage(): void
    {
        $script = $this->installBootstrap('seul');

        [$exit, $output] = $this->execute($script);

        $this->assertSame(1, $exit, 'un pipeline ne doit pas voir un succès (die("texte") donnait le code 0)');
        $this->assertStringContainsString('Autoloader introuvable', $output);
        $this->assertStringContainsString('composer install', $output);
    }

    /** @dataProvider autoloaderLocations */
    public function testAutoloaderIsFoundInEachSupportedLocation(string $bootstrapDir, string $vendorDir): void
    {
        $script = $this->installBootstrap($bootstrapDir);
        $this->fakeAutoloader("{$vendorDir}/vendor/autoload.php", 'APPLICATION_LANCEE', 0);

        [$exit, $output] = $this->execute($script);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('APPLICATION_LANCEE', $output);
    }

    public static function autoloaderLocations(): array
    {
        return [
            'à côté de src/ (projet autonome)'          => ['proj', 'proj'],
            'deux niveaux au-dessus (paquet installé)'  => ['proj/pkg', 'proj'],
            'trois niveaux au-dessus (monorepo)'        => ['racine/a/b', 'racine'],
        ];
    }

    public function testNearestAutoloaderWinsOverFartherOnes(): void
    {
        $script = $this->installBootstrap('racine/pkg');
        $this->fakeAutoloader('racine/pkg/vendor/autoload.php', 'PROCHE', 0);
        $this->fakeAutoloader('racine/vendor/autoload.php', 'LOINTAIN', 0);

        [, $output] = $this->execute($script);

        $this->assertStringContainsString('PROCHE', $output);
        $this->assertStringNotContainsString('LOINTAIN', $output);
    }

    /** @dataProvider exitCodes */
    public function testApplicationExitCodeBecomesTheProcessExitCode(int $code): void
    {
        $script = $this->installBootstrap('proj');
        $this->fakeAutoloader('proj/vendor/autoload.php', 'FINI', $code);

        [$exit] = $this->execute($script);

        $this->assertSame($code, $exit);
    }

    public static function exitCodes(): array
    {
        return [[0], [1], [2], [7]];
    }
}
