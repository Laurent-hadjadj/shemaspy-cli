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

use SchemaSpyCli\Core\Config;

/**
 * [Description OutputNameGenerator]
 * Génération du nom du dossier de sortie.
 *
 * Logique auparavant dupliquée à l'identique dans InteractiveMode et
 * NonInteractiveMode. Centralisée ici pour garantir un comportement unique.
 */
final class OutputNameGenerator
{
    /**
     * Format par défaut utilisé si la configuration ne définit pas
     * defaults.output_name_format.
     */
    private const DEFAULT_FORMAT = '{dbType}_{schema}_{timestamp}';

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * [Description for generate]
     * Génère un nom de dossier de sortie à partir du type de base et du schéma.
     *
     * Les placeholders supportés : {dbType}, {schema}, {timestamp}.
     * Le résultat est nettoyé de tout caractère non sûr pour le système de fichiers.
     *
     * @param string $dbType
     * @param string $schema
     *
     * @return string
     *
     * Created at: 05/10/2026 09:03:10 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function generate(string $dbType, string $schema): string
    {
        $format = $this->config->get('defaults.output_name_format', self::DEFAULT_FORMAT);
        $safeSchema = $schema !== '' ? $schema : 'unknown';
        $timestamp = date('Ymd_His');

        $output = str_replace(
            ['{dbType}', '{schema}', '{timestamp}'],
            [$dbType, $safeSchema, $timestamp],
            $format
        );

        // Nettoyer le nom (supprimer les caractères non alphanumériques/_/-/.)
        $output = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $output);

        return (string) $output;
    }
}
