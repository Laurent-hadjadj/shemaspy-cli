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

namespace SchemaSpyCli\Tests\Support;

use SchemaSpyCli\Utils\ProcessRunner;

/**
 * [Description FakeProcessRunner]
 * Remplace le vrai processus Java : rejoue des lignes de sortie et un code de retour,
 * et mémorise ce que SchemaSpy aurait reçu (commande, fichier properties).
 */
final class FakeProcessRunner extends ProcessRunner
{
    public ?string $command = null;
    public bool $propertiesFileExistedDuringRun = false;
    public ?string $propertiesContent = null;
    public ?string $javaHomeDuringRun = null;

    /** @param list<string> $lines */
    public function __construct(private readonly array $lines = [], private readonly int $exitCode = 0)
    {
    }

    public function run(string $command, callable $onOutput): int
    {
        $this->command = $command;
        $this->javaHomeDuringRun = getenv('JAVA_HOME') ?: null;

        $file = $this->propertiesFile();
        $this->propertiesFileExistedDuringRun = $file !== null && is_file($file);
        $this->propertiesContent = $this->propertiesFileExistedDuringRun ? (string) file_get_contents($file) : null;

        foreach ($this->lines as $line) {
            $onOutput($line);
        }
        return $this->exitCode;
    }

    public function propertiesFile(): ?string
    {
        return preg_match('/-configFile\s+"?([^"\s]+)"?/', (string) $this->command, $m) ? $m[1] : null;
    }
}
