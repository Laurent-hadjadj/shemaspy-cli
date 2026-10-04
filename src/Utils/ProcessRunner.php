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

namespace SchemaSpyCli\Utils;

/**
 * [Description ProcessRunner]
 * Exécution d'une commande avec relais de sa sortie (stdout + stderr) au fil de l'eau
 */
class ProcessRunner
{
    /** Cause de l'échec de proc_open, conservée pour le message d'erreur final. */
    private string $procOpenError = '';

    /**
     * Exécute la commande et transmet sa sortie à $onOutput, ligne par ligne
     * (saut de ligne inclus). Un fragment sans saut de ligne qui ne contient que
     * des points (progression de SchemaSpy) est transmis immédiatement.
     *
     * proc_open() est utilisé en priorité ; s'il est refusé (ex: sous Windows, terminal
     * intégré de VS Code : « proc_open(): Command conversion failed »), on se rabat sur
     * popen(), qui offre le même affichage en direct.
     *
     * @param callable(string): void $onOutput
     * @return int code de sortie du processus
     * @throws \RuntimeException si le processus n'a pas pu être démarré
     */
    public function run(string $command, callable $onOutput): int
    {
        $started = $this->startWithProcOpen($command) ?? $this->startWithPopen($command);

        if ($started === null) {
            throw new \RuntimeException(
                "Impossible de lancer le processus (proc_open : {$this->procOpenError} ; popen indisponible). " .
                "Vérifiez disable_functions dans php.ini."
            );
        }

        [$stream, $finish] = $started;
        $this->relay($stream, $onOutput);

        return $finish();
    }

    /**
     * @return array{0: resource, 1: callable(): int}|null flux de sortie + fermeture retournant le code de sortie
     */
    protected function startWithProcOpen(string $command): ?array
    {
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ], $pipes);

        if (!is_resource($process)) {
            $this->procOpenError = error_get_last()['message'] ?? 'cause inconnue';
            return null;
        }

        fclose($pipes[0]);

        return [$pipes[1], static function () use ($process, $pipes): int {
            fclose($pipes[1]);
            $status = proc_get_status($process);
            $closeCode = proc_close($process);

            // proc_close() renvoie -1 si le processus était déjà terminé : on préfère le code lu avant
            return $status['running'] || $status['exitcode'] === -1 ? $closeCode : $status['exitcode'];
        }];
    }

    /**
     * @return array{0: resource, 1: callable(): int}|null
     */
    protected function startWithPopen(string $command): ?array
    {
        $handle = @popen($command . ' 2>&1', 'r');
        if (!is_resource($handle)) {
            return null;
        }

        return [$handle, static fn(): int => pclose($handle)];
    }

    /**
     * @param resource $stream
     * @param callable(string): void $onOutput
     */
    private function relay($stream, callable $onOutput): void
    {
        $buffer = '';
        while (($chunk = fread($stream, 8192)) !== false && $chunk !== '') {
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n")) !== false) {
                $onOutput(substr($buffer, 0, $pos + 1));
                $buffer = substr($buffer, $pos + 1);
            }

            if ($buffer !== '' && preg_match('/^\.+$/', $buffer) === 1) {
                $onOutput($buffer);
                $buffer = '';
            }
        }
        if ($buffer !== '') {
            $onOutput($buffer);
        }
    }
}
