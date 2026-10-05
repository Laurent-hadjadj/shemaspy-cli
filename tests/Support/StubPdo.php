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

/**
 * [Description StubPdo]
 * Faux PDO sans pilote ni base : n'a besoin d'aucune extension, mémorise les requêtes reçues et
 * peut être configuré pour échouer. Le constructeur parent n'est volontairement pas appelé.
 */
final class StubPdo extends \PDO
{
    /** @var list<string> */
    public array $queries = [];

    public function __construct(private readonly bool $failOnQuery = false)
    {
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $this->queries[] = $query;
        if ($this->failOnQuery) {
            throw new \PDOException('requête de test refusée');
        }

        return false;
    }

    public function getAttribute(int $attribute): mixed
    {
        return match ($attribute) {
            \PDO::ATTR_DRIVER_NAME => 'stub',
            \PDO::ATTR_SERVER_VERSION => '9.9.9-stub',
            default => null,
        };
    }
}
