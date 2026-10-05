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

/**
 * [Description LauncherTest]
 * Garde-fous sur les lanceurs. Régression : un bin/schemaspy.bat en fins de ligne LF avec des
 * accents UTF-8 faisait exécuter à cmd.exe un fragment de commentaire comme une commande
 * (« 'SchemaSpy' n'est pas reconnu en tant que commande interne ou externe »).
 */
final class LauncherTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private function read(string $relative): string
    {
        $content = file_get_contents(self::ROOT . '/' . $relative);
        $this->assertNotFalse($content, "{$relative} introuvable");
        return $content;
    }

    public function testWindowsLauncherIsPureAscii(): void
    {
        $this->assertSame(0, preg_match('/[^\x00-\x7F]/', $this->read('bin/schemaspy.bat')),
            'cmd.exe lit le .bat dans la page de code active : aucun caractère accentué (même dans un rem)');
    }

    public function testWindowsLauncherUsesCrlfLineEndings(): void
    {
        $content = $this->read('bin/schemaspy.bat');

        $this->assertStringContainsString("\r\n", $content);
        $this->assertSame(0, preg_match('/(?<!\r)\n/', $content), 'tout saut de ligne doit être CRLF');
    }

    public function testWindowsLauncherDelegatesToPhpEntryPointAndPropagatesExitCode(): void
    {
        $content = $this->read('bin/schemaspy.bat');

        $this->assertStringContainsString('php "%~dp0schemaspy" %*', $content, 'indépendant du répertoire courant');
        $this->assertStringContainsString('exit /b %RC%', $content);
        $this->assertStringContainsString('where php', $content, 'message clair si PHP est absent du PATH');
    }

    public function testPhpEntryPointKeepsShebangAndUnixLineEndings(): void
    {
        $content = $this->read('bin/schemaspy');

        $this->assertStringStartsWith("#!/usr/bin/env php\n", $content, 'shebang suivi d\'un LF (pas de "php\r")');
        $this->assertStringNotContainsString("\r", $content);
        $this->assertStringContainsString("src/bootstrap.php", $content);
    }

    public function testGitAttributesPinLauncherLineEndings(): void
    {
        $attributes = $this->read('.gitattributes');

        $this->assertMatchesRegularExpression('/^\*\.bat\s+text\s+eol=crlf\s*$/m', $attributes);
        $this->assertMatchesRegularExpression('/^bin\/schemaspy\s+text\s+eol=lf\s*$/m', $attributes);
    }
}
