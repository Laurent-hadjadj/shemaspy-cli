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

use SchemaSpyCli\Exceptions\ValidationException;

/**
 * [Description GenerationOptions]
 * Options de génération SchemaSpy (moteur de rendu, HTML, Markdown, filtres...)
 *
 * Les valeurs proviennent, par ordre de priorité croissante, de config.json
 * ("generation"), puis de la ligne de commande.
 */
final class GenerationOptions
{
    public const ENGINE_AUTO = 'auto';
    public const ENGINE_GRAPHVIZ = 'graphviz';
    public const ENGINE_VIZJS = 'vizjs';

    public function __construct(
        public readonly string $engine = self::ENGINE_AUTO,
        public readonly bool $html = true,
        public readonly bool $markdown = false,
        public readonly bool $orphans = true,
        public readonly bool $views = true,
        public readonly bool $rows = true,
        public readonly bool $implied = true,
        public readonly ?int $degree = null,
        public readonly ?string $include = null,
        public readonly ?string $exclude = null
    ) {
        if (!in_array($engine, [self::ENGINE_AUTO, self::ENGINE_GRAPHVIZ, self::ENGINE_VIZJS], true)) {
            throw new ValidationException("engine doit être auto, graphviz ou vizjs (reçu : {$engine})");
        }
        if ($degree !== null && !in_array($degree, [1, 2], true)) {
            throw new ValidationException("degree doit valoir 1 ou 2 (reçu : {$degree})");
        }
        foreach (['include' => $include, 'exclude' => $exclude] as $name => $pattern) {
            if ($pattern !== null && @preg_match('/' . str_replace('/', '\/', $pattern) . '/', '') === false) {
                throw new ValidationException("{$name} n'est pas une expression régulière valide : {$pattern}");
            }
        }
        if (!$html && !$markdown) {
            throw new ValidationException(
                "--no-html sans --markdown ne produirait rien : ajoutez --markdown ou retirez --no-html."
            );
        }
    }

    /**
     * [Description for fromArrays]
     * Construit les options à partir de tableaux "plats" (config.json puis CLI).
     * Clés reconnues : engine, html, markdown, orphans, views, rows, implied,
     * degree, include, exclude. Les valeurs null/absentes sont ignorées.
     *
     * @param array ...$layers
     *
     * @return self
     *
     * Created at: 04/10/2026 22:46:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public static function fromArrays(array ...$layers): self
    {
        $merged = [];
        foreach ($layers as $layer) {
            foreach ($layer as $key => $value) {
                if ($value !== null && $value !== '') {
                    $merged[$key] = $value;
                }
            }
        }

        return new self(
            engine: strtolower((string) ($merged['engine'] ?? self::ENGINE_AUTO)),
            html: self::toBool($merged['html'] ?? true),
            markdown: self::toBool($merged['markdown'] ?? false),
            orphans: self::toBool($merged['orphans'] ?? true),
            views: self::toBool($merged['views'] ?? true),
            rows: self::toBool($merged['rows'] ?? true),
            implied: self::toBool($merged['implied'] ?? true),
            degree: isset($merged['degree']) ? (int) $merged['degree'] : null,
            include: isset($merged['include']) ? (string) $merged['include'] : null,
            exclude: isset($merged['exclude']) ? (string) $merged['exclude'] : null,
        );
    }

    /**
     * [Description for toProperties]
     * Propriétés SchemaSpy (clés "schemaspy.xxx") correspondant à ces options.
     * Le choix du moteur de rendu est géré par PropertiesGenerator.
     *
     * @return array
     *
     * Created at: 04/10/2026 22:46:51 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function toProperties(): array
    {
        $props = [];

        if (!$this->html) {
            $props['schemaspy.nohtml'] = 'true';
        }
        if ($this->markdown) {
            // Option du fork SchemaSpy (sortie Markdown + Mermaid dans <sortie>/markdown/)
            $props['schemaspy.markdown'] = 'true';
        }
        if (!$this->orphans) {
            $props['schemaspy.no-orphans'] = 'true';
        }
        if (!$this->views) {
            $props['schemaspy.noviews'] = 'true';
        }
        if (!$this->rows) {
            $props['schemaspy.norows'] = 'true';
        }
        if (!$this->implied) {
            $props['schemaspy.noimplied'] = 'true';
        }
        if ($this->degree !== null) {
            $props['schemaspy.degree'] = (string) $this->degree;
        }
        if ($this->include !== null) {
            $props['schemaspy.i'] = $this->include;
        }
        if ($this->exclude !== null) {
            $props['schemaspy.I'] = $this->exclude;
        }

        return $props;
    }

    /**
     * [Description for toBool]
     *
     * @param mixed $value
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:47:06 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'oui', 'on'], true);
    }
}
