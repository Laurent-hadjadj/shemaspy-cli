<?php
/**
 * Génération du nom du dossier de sortie.
 *
 * Logique auparavant dupliquée à l'identique dans InteractiveMode et
 * NonInteractiveMode. Centralisée ici pour garantir un comportement unique.
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.1.0
 */

namespace SchemaSpyCli\Utils;

use SchemaSpyCli\Core\Config;

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
     * Génère un nom de dossier de sortie à partir du type de base et du schéma.
     *
     * Les placeholders supportés : {dbType}, {schema}, {timestamp}.
     * Le résultat est nettoyé de tout caractère non sûr pour le système de fichiers.
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
