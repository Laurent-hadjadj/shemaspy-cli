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

use SchemaSpyCli\Core\Environment;

/**
 * [Description ScriptedEnvironment]
 * Environment dont les commandes shell sont scriptées : chaque appel à runCommand() renvoie la
 * sortie suivante de la liste (la dernière est répétée) et la commande demandée est mémorisée.
 * Contrairement à FakeEnvironment, la vraie logique d'Environment (analyse des sorties, chemins,
 * cache) est exécutée : seul le lancement de processus est remplacé.
 */
final class ScriptedEnvironment extends Environment
{
    /** @var list<string> commandes reçues, dans l'ordre */
    public array $commands = [];

    /** @param list<string> $outputs */
    public function __construct(private array $outputs = [''], ?string $osFamily = null)
    {
        parent::__construct($osFamily);
    }

    protected function runCommand(string $command): string
    {
        $this->commands[] = $command;

        return count($this->outputs) > 1 ? array_shift($this->outputs) : ($this->outputs[0] ?? '');
    }
}
