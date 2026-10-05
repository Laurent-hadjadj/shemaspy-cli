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

namespace SchemaSpyCli\Core;

/**
 * [Description Logger]
 * Gestion des logs et de l'affichage
 */
final class Logger
{
    private bool $quiet = false;
    private bool $verbose = false;
    private bool $colorSupport = false;
    private ?string $logFile = null;
    private array $colorMap = [
        'red'    => "\033[31m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'cyan'   => "\033[36m",
        'white'  => "\033[37m",
        'gray'   => "\033[90m",
        'default'=> "\033[0m",
    ];

    /**
     * @param string|null $logFile Chemin d'un fichier de trace complète (texte brut,
     *   sans couleurs, indépendant du mode quiet/verbose). Écrasé à chaque exécution.
     *   Null (défaut) désactive la sortie fichier — c'est le cas dans les tests.
     */
    /** @var resource|null */
    private $inputStream = null;

    public function __construct(private bool $forceColor = false, ?string $logFile = null)
    {
        $this->colorSupport = $this->forceColor || $this->supportsColor();

        if ($logFile !== null && $this->initLogFile($logFile)) {
            $this->logFile = $logFile;
        }
    }

    /**
     * [Description for initLogFile]
     * Crée le dossier si besoin et écrase le fichier de log (trace de la
     * dernière exécution uniquement). Échoue silencieusement (pas de dossier
     * accessible en écriture) : la sortie console reste inchangée.
     *
     * @param string $logFile
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:36:16 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function initLogFile(string $logFile): bool
    {
        $dir = dirname($logFile);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }

        return @file_put_contents(
            $logFile,
            "=== SchemaSpy CLI - " . date('Y-m-d H:i:s') . " ===\n"
        ) !== false;
    }

    /**
     * [Description for writeToFile]
     * Écrit une ligne dans le fichier de log, indépendamment du mode
     * quiet/verbose de la console (le fichier garde toujours la trace complète).
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:36:34 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function writeToFile(string $message): void
    {
        if ($this->logFile === null) {
            return;
        }
        @file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    /**
     * [Description for setInputStream]
     * Remplace STDIN comme source des saisies (prompts) : utile pour les tests et les scripts.
     *
     * @param resource|null $stream
     *
     * @return void
     *
     * Created at: 04/10/2026 22:36:49 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function setInputStream($stream): void
    {
        $this->inputStream = $stream;
    }

    /**
     * [Description for getLogFile]
     *
     * @return string|null
     *
     * Created at: 04/10/2026 22:37:20 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function getLogFile(): ?string
    {
        return $this->logFile;
    }

    /**
     * [Description for logOnly]
     * Écrit dans le fichier de log uniquement (rien sur la console) : trace complète
     * des lignes que l'affichage choisit de masquer.
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:37:23 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function logOnly(string $message): void
    {
        $this->writeToFile("[SCHEMASPY] " . $message);
    }

    /**
     * [Description for supportsColor]
     * Détecte si le terminal supporte les couleurs
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:37:40 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function supportsColor(): bool
    {
        // Si on est en mode quiet, pas de couleurs
        if ($this->quiet) {
            return false;
        }

        // Vérifier si les couleurs sont forcées
        if (getenv('FORCE_COLOR') !== false) {
            return true;
        }

        // Sur Windows
        if (PHP_OS_FAMILY === 'Windows') {
            // Vérifier si on est dans PowerShell
            if (getenv('PSModulePath') !== false) {
                // PowerShell 5.1+ supporte les couleurs
                return true;
            }

            // Windows 10+ avec ANSI support
            if (function_exists('sapi_windows_vt100_support')) {
                return sapi_windows_vt100_support(STDOUT);
            }

            // Windows Terminal / Conhost
            if (getenv('WT_SESSION') !== false) {
                return true;
            }

            return false;
        }

        // Sur Unix/Linux
        if (getenv('TERM') === 'dumb') {
            return false;
        }

        // Vérifier si on est dans un terminal interactif
        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }

    /**
     * [Description for setQuiet]
     *
     * @param bool $quiet
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:06 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function setQuiet(bool $quiet): void
    {
        $this->quiet = $quiet;
        $this->colorSupport = $this->forceColor || $this->supportsColor();
    }

    /**
     * [Description for setVerbose]
     *
     * @param bool $verbose
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:09 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    /**
     * [Description for setForceColor]
     *
     * @param bool $forceColor
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:11 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function setForceColor(bool $forceColor): void
    {
        $this->forceColor = $forceColor;
        $this->colorSupport = $this->forceColor || $this->supportsColor();
    }

    /**
     * [Description for info]
     * Méthodes principales
     *
     * @param string $message
     * @param string $color
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:14 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function info(string $message, string $color = 'default'): void
    {
        $this->writeToFile("[INFO]     ℹ️" . $message);
        if ($this->quiet) {
            return;
        }
        $this->output("ℹ️" . $message, $color);
    }

    /**
     * [Description for error]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function error(string $message): void
    {
        $this->writeToFile("[ERROR]    ❌ " . $message);
        $this->output("❌ " . $message, 'red');
    }

    /**
     * [Description for success]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:35 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function success(string $message): void
    {
        $this->writeToFile("[SUCCESS] ✅ " . $message);
        $this->output("✅ " . $message, 'green');
    }

    /**
     * [Description for warning]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:37 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function warning(string $message): void
    {
        $this->writeToFile("[WARN]     ⚠️ " . $message);
        $this->output("⚠️ " . $message, 'yellow');
    }

    /**
     * [Description for critical]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 05/10/2026 08:52:23 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function critical(string $message): void
    {
        $this->writeToFile("[CRITICAL] 🔴 " . $message);
        if (!$this->verbose) {
            return;
        }
        $this->output("🔴 " . $message, 'gray');
    }

    /**
     * [Description for debug]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:42 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function debug(string $message): void
    {
        $this->writeToFile("[DEBUG]    🛠️" . $message);
        if (!$this->verbose) {
            return;
        }
        $this->output("DEBUG 🛠️ " . $message, 'gray');
    }

    /**
     * [Description for progress]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:46 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function progress(string $message): void
    {
        $this->writeToFile("[PROGRESS] ⚙️ " . $message);
        if ($this->quiet) {
            return;
        }
        $this->output("PROGRESS ⚙️ " . $message, 'cyan');
    }

    /**
     * [Description for title]
     *
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:49 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function title(string $message): void
    {
        $this->writeToFile(str_repeat('=', 50));
        $this->writeToFile($message);
        $this->writeToFile(str_repeat('=', 50));
        if ($this->quiet) {
            return;
        }
        $this->output("\n" . str_repeat("═", 50), 'gray');
        $this->output("   " . $message, 'white');
        $this->output(str_repeat("═", 50), 'gray');
    }

    /**
     * [Description for blankLine]
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:53 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function separator(string $char = '═', int $length = 50): void
    {
        if ($this->quiet) {
            return;
        }
        $this->output(str_repeat($char, $length), 'gray');
    }

    /**
     * [Description for blankLine]
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:56 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function blankLine(): void
    {
        $this->writeToFile('');
        if ($this->quiet) {
            return;
        }
        echo "\n";
    }

    /**
     * [Description for table]
     *
     * @param array $headers
     * @param array $rows
     *
     * @return void
     *
     * Created at: 04/10/2026 22:38:58 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function table(array $headers, array $rows): void
    {
        $this->writeToFile(implode(' | ', $headers));
        foreach ($rows as $row) {
            $this->writeToFile(implode(' | ', array_map(fn($v) => (string) ($v ?? ''), $row)));
        }

        if ($this->quiet) {
            return;
        }

        // Calculer la largeur des colonnes
        $colWidths = [];
        foreach ($headers as $i => $header) {
            $colWidths[$i] = strlen($header);
            foreach ($rows as $row) {
                if (isset($row[$i])) {
                    $colWidths[$i] = max($colWidths[$i], strlen((string)$row[$i]));
                }
            }
        }

        // Afficher l'en-tête
        $headerLine = '| ';
        foreach ($headers as $i => $header) {
            $headerLine .= str_pad($header, $colWidths[$i]) . ' | ';
        }
        $this->output($headerLine, 'white');

        $separator = '+' . str_repeat('-', array_sum($colWidths) + (count($headers) * 3) + 1) . '+';
        $this->output($separator, 'gray');

        // Afficher les lignes
        foreach ($rows as $row) {
            $line = '| ';
            foreach ($headers as $i => $header) {
                $value = $row[$i] ?? '';
                $line .= str_pad($value, $colWidths[$i]) . ' | ';
            }
            $this->output($line, 'default');
        }
    }

    /**
     * [Description for output]
     *
     * @param string $message
     * @param string $color
     *
     * @return void
     *
     * Created at: 04/10/2026 22:39:04 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function output(string $message, string $color = 'default'): void
    {
        if ($this->quiet) {
            return;
        }

        $message = $this->stripEmoji($message);

        if ($this->colorSupport && isset($this->colorMap[$color])) {
            $reset = "\033[0m";
            echo $this->colorMap[$color] . $message . $reset . "\n";
        } else {
            echo $message . "\n";
        }
    }

    /**
     * [Description for stripEmoji]
     * Retire les emojis de la sortie console : de nombreux terminaux (cmd.exe,
     * PowerShell selon la codepage) les affichent en "?" illisibles. Le fichier
     * de log (writeToFile) n'appelle pas cette méthode et garde les emojis.
     *
     * @param string $message
     *
     * @return string
     *
     * Created at: 04/10/2026 22:39:08 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    private function stripEmoji(string $message): string
    {
        $stripped = preg_replace(
            '/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{2139}\x{FE0F}\x{200D}]/u',
            '',
            $message
        );

        if ($stripped === null) {
            // preg_replace échoue sur une entrée qui n'est pas de l'UTF-8 valide : on garde l'original.
            return $message;
        }

        // Un emoji en tête de ligne est presque toujours suivi d'un espace séparateur
        // ("✅ message") : on ne retire que cet espace résiduel, pas l'indentation volontaire.
        return $stripped !== $message ? ltrim($stripped, ' ') : $stripped;
    }

    /**
     * [Description for prompt]
     * Demande une saisie à l'utilisateur.
     *
     * Le paramètre $inputStream (principalement pour les tests) permet d'injecter
     * un flux de lecture à la place de STDIN.
     *
     * @param resource|null $inputStream Flux d'entrée (défaut: STDIN)
     *
     * @return string
     *
     * Created at: 04/10/2026 22:39:36 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function prompt(string $message, string $default = '', $inputStream = null): string
    {
        if ($this->quiet) {
            return $default;
        }

        $stream = $inputStream ?? $this->inputStream ?? STDIN;

        $display = $default ? "{$message} [{$default}]" : $message;
        echo "{$display}: ";
        $line = is_resource($stream) ? fgets($stream) : false;
        $value = trim((string) $line);
        return $value !== '' ? $value : $default;
    }

    /**
     * [Description for promptPassword]
     *
     * @param string $message
     * @param null $inputStream
     *
     * @return string
     *
     * Created at: 04/10/2026 22:40:04 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function promptPassword(string $message, $inputStream = null): string
    {
        if ($this->quiet) {
            return '';
        }

        // Sur Unix avec readline, on masque la saisie
        if (PHP_OS_FAMILY !== 'Windows'
            && function_exists('readline')
            && $inputStream === null
            && $this->inputStream === null
        ) {
            system('stty -echo');
            echo "{$message}: ";
            $password = trim((string) fgets(STDIN));
            system('stty echo');
            echo "\n";
            return $password;
        }

        // Sur Windows, ou en test (flux injecté), fallback sur prompt standard
        return $this->prompt($message, '', $inputStream);
    }

    /**
     * [Description for promptConfirmation]
     *
     * @param string $message
     * @param bool $default
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:40:07 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function promptConfirmation(string $message, bool $default = true): bool
    {
        if ($this->quiet) {
            return $default;
        }

        $defaultText = $default ? 'O/n' : 'o/N';
        $response = strtolower($this->prompt("{$message} ({$defaultText})"));

        if ($response === '') {
            return $default;
        }

        return $response === 'o' || $response === 'oui' || $response === 'yes';
    }

    // Getters

    /**
     * [Description for isQuiet]
     * @return bool
     *
     * Created at: 04/10/2026 22:40:15 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    /**
     * [Description for isVerbose]
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:40:33 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    /**
     * [Description for hasColorSupport]
     *
     * @return bool
     *
     * Created at: 04/10/2026 22:40:36 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function hasColorSupport(): bool
    {
        return $this->colorSupport;
    }

    // Méthodes utilitaires

    /**
     * [Description for clearLine]
     *
     * @return void
     *
     * Created at: 04/10/2026 22:40:39 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function clearLine(): void
    {
        if ($this->quiet) {
            return;
        }
        echo "\r\033[K";
    }

    /**
     * [Description for progressBar]
     *
     * @param int $current
     * @param int $total
     * @param string $message
     *
     * @return void
     *
     * Created at: 04/10/2026 22:41:15 (Europe/Paris)
     * @author     Laurent HADJADJ <laurent_h@me.com>
     * @copyright  Licensed Ma-Moulinette - Creative Common CC-BY-NC-SA 4.0.
     */
    public function progressBar(int $current, int $total, string $message = ''): void
    {
        if ($this->quiet) {
            return;
        }

        $percent = round(($current / $total) * 100);
        $barLength = 40;
        $filled = round(($current / $total) * $barLength);
        $empty = $barLength - $filled;

        $bar = '[' . str_repeat('█', $filled) . str_repeat('░', $empty) . ']';
        $display = $message ? "{$message} {$bar} {$percent}%" : "{$bar} {$percent}%";

        $this->clearLine();
        echo $display;

        if ($current === $total) {
            echo "\n";
        }
    }
}
