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
    /**
     * Exécute la commande et transmet sa sortie à $onOutput, ligne par ligne
     * (saut de ligne inclus). Un fragment sans saut de ligne qui ne contient que
     * des points (progression de SchemaSpy) est transmis immédiatement.
     *
     * @param callable(string): void $onOutput
     * @return int code de sortie du processus (-1 si le processus n'a pas pu démarrer)
     */
    public function run(string $command, callable $onOutput): int
    {
        $process = @proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['redirect', 1],
        ], $pipes);

        if (!is_resource($process)) {
            return -1;
        }

        fclose($pipes[0]);

        $buffer = '';
        while (($chunk = fread($pipes[1], 8192)) !== false && $chunk !== '') {
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
        fclose($pipes[1]);

        $status = proc_get_status($process);
        $closeCode = proc_close($process);

        // proc_close() renvoie -1 si le processus était déjà terminé : on préfère le code lu avant
        return $status['running'] || $status['exitcode'] === -1 ? $closeCode : $status['exitcode'];
    }
}
