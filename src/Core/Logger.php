<?php
/**
 * Gestion des logs et de l'affichage
 *
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Core;

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
    public function __construct(private bool $forceColor = false, ?string $logFile = null)
    {
        $this->colorSupport = $this->forceColor || $this->supportsColor();

        if ($logFile !== null && $this->initLogFile($logFile)) {
            $this->logFile = $logFile;
        }
    }

    /**
     * Crée le dossier si besoin et écrase le fichier de log (trace de la
     * dernière exécution uniquement). Échoue silencieusement (pas de dossier
     * accessible en écriture) : la sortie console reste inchangée.
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
     * Écrit une ligne dans le fichier de log, indépendamment du mode
     * quiet/verbose de la console (le fichier garde toujours la trace complète).
     */
    private function writeToFile(string $message): void
    {
        if ($this->logFile === null) {
            return;
        }
        @file_put_contents($this->logFile, '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n", FILE_APPEND);
    }

    public function getLogFile(): ?string
    {
        return $this->logFile;
    }

    /**
     * Détecte si le terminal supporte les couleurs
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

    public function setQuiet(bool $quiet): void
    {
        $this->quiet = $quiet;
        $this->colorSupport = $this->forceColor || $this->supportsColor();
    }

    public function setVerbose(bool $verbose): void
    {
        $this->verbose = $verbose;
    }

    public function setForceColor(bool $forceColor): void
    {
        $this->forceColor = $forceColor;
        $this->colorSupport = $this->forceColor || $this->supportsColor();
    }

    // ✅ Méthodes principales
    public function info(string $message, string $color = 'default'): void
    {
        $this->writeToFile($message);
        if ($this->quiet) {
            return;
        }
        $this->output($message, $color);
    }

    public function error(string $message): void
    {
        $this->writeToFile("✗ " . $message);
        $this->output("✗ " . $message, 'red');
    }

    public function success(string $message): void
    {
        $this->writeToFile("✅ " . $message);
        $this->output("✅ " . $message, 'green');
    }

    public function warning(string $message): void
    {
        $this->writeToFile("⚠️  " . $message);
        $this->output("⚠️  " . $message, 'yellow');
    }

    public function debug(string $message): void
    {
        $this->writeToFile("[DEBUG] " . $message);
        if (!$this->verbose) {
            return;
        }
        $this->output("[DEBUG] " . $message, 'gray');
    }

    public function progress(string $message): void
    {
        $this->writeToFile("🔍 " . $message);
        if ($this->quiet) {
            return;
        }
        $this->output("🔍 " . $message, 'cyan');
    }

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

    public function separator(string $char = '═', int $length = 50): void
    {
        if ($this->quiet) {
            return;
        }
        $this->output(str_repeat($char, $length), 'gray');
    }

    public function blankLine(): void
    {
        $this->writeToFile('');
        if ($this->quiet) {
            return;
        }
        echo "\n";
    }

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
     * Retire les emojis de la sortie console : de nombreux terminaux (cmd.exe,
     * PowerShell selon la codepage) les affichent en "?" illisibles. Le fichier
     * de log (writeToFile) n'appelle pas cette méthode et garde les emojis.
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
     * Demande une saisie à l'utilisateur.
     *
     * Le paramètre $inputStream (principalement pour les tests) permet d'injecter
     * un flux de lecture à la place de STDIN.
     *
     * @param resource|null $inputStream Flux d'entrée (défaut: STDIN)
     */
    public function prompt(string $message, string $default = '', $inputStream = null): string
    {
        if ($this->quiet) {
            return $default;
        }

        $stream = $inputStream ?? STDIN;

        $display = $default ? "{$message} [{$default}]" : $message;
        echo "{$display}: ";
        $line = is_resource($stream) ? fgets($stream) : false;
        $value = trim((string) $line);
        return $value !== '' ? $value : $default;
    }

    public function promptPassword(string $message, $inputStream = null): string
    {
        if ($this->quiet) {
            return '';
        }

        // Sur Unix avec readline, on masque la saisie
        if (PHP_OS_FAMILY !== 'Windows'
            && function_exists('readline')
            && $inputStream === null
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

    // ✅ Getters
    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    public function isVerbose(): bool
    {
        return $this->verbose;
    }

    public function hasColorSupport(): bool
    {
        return $this->colorSupport;
    }

    // ✅ Méthodes utilitaires
    public function clearLine(): void
    {
        if ($this->quiet) {
            return;
        }
        echo "\r\033[K";
    }

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
