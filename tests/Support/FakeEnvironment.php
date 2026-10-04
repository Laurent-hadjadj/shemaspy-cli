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
 * [Description FakeEnvironment]
 * Environment piloté par les tests : JDK/Graphviz simulés, sans rien installer.
 *
 * Les "exécutables" sont de simples fichiers vides créés par les tests ; seules
 * les versions renvoyées sont simulées.
*/
final class FakeEnvironment extends Environment
{
    /**
     * @param string|null          $systemJavaHome JAVA_HOME simulé (null = aucun JDK système)
     * @param array<string,string> $javaVersions   dossier JDK => version renvoyée par `java -version`
     * @param string|null          $dotInPath      chemin de `dot` trouvé dans le PATH (null = absent)
     */
    public function __construct(
        private readonly ?string $systemJavaHome = null,
        private readonly array $javaVersions = [],
        private readonly ?string $dotInPath = null,
        private readonly ?string $graphvizVersion = '16.1.0'
    ) {
        parent::__construct();
    }

    public function getJavaHome(): ?string
    {
        return $this->systemJavaHome;
    }

    public function getJavaExecutable(string $javaHome): string
    {
        return $javaHome . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'java.fake';
    }

    public function getJavaVersion(?string $javaExe = null): ?string
    {
        $home = dirname(dirname((string) $javaExe));
        return $this->javaVersions[$home] ?? null;
    }

    public function findGraphvizInPath(): ?string
    {
        return $this->dotInPath;
    }

    public function getGraphvizVersion(?string $dotExe = null): ?string
    {
        return $this->graphvizVersion;
    }
}
