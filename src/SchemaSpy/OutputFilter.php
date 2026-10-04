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

namespace SchemaSpyCli\SchemaSpy;

/**
 * [Description OutputFilter]
 * Filtre de la sortie de SchemaSpy : masque le bruit répétitif des avertissements
 * Graphviz (ex: "Warning: cell size too small for content") et les compte.
*/
final class OutputFilter
{
    /**
     * Avertissements émis par dot pour chaque table (Graphviz >= 15) : sans
     * conséquence sur les diagrammes produits. SchemaSpy préfixe parfois la
     * ligne avec ses points de progression ("..WARN  - ...").
     */
    private const NOISE_PATTERN =
        '/^(?<dots>\.*)WARN\s+-\s+.*\bdot\b.*:\s(?:Warning: cell size too small for content|in label of node .*)\s*$/u';

    private int $suppressed = 0;

    /**
     * [Description for __construct]
     *
     * @param (callable(string): void)|null $onSuppressed appelé avec chaque ligne masquée (ex: écriture dans le log)
     *
     * Created at: 04/10/2026 22:47:30 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function __construct(private readonly mixed $onSuppressed = null)
    {
    }

    /**
     * [Description for filter]
     * Retourne le texte à afficher pour une ligne (avec son saut de ligne éventuel),
     * ou une chaîne vide si la ligne est masquée. Les points de progression en tête
     * d'une ligne masquée sont conservés pour ne pas casser l'affichage.
     *
     * @param string $line
     *
     * @return string
     *
     * Created at: 04/10/2026 22:47:48 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function filter(string $line): string
    {
        if (preg_match(self::NOISE_PATTERN, rtrim($line, "\r\n"), $m) !== 1) {
            return $line;
        }

        $this->suppressed++;
        if ($this->onSuppressed !== null) {
            ($this->onSuppressed)(rtrim($line, "\r\n"));
        }

        return $m['dots'];
    }

    /**
     * [Description for getSuppressedCount]
     *
     * @return int
     *
     * Created at: 04/10/2026 22:48:01 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getSuppressedCount(): int
    {
        return $this->suppressed;
    }
}
