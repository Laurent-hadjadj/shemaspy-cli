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
    private bool $forceColor = false;
    private array $colorMap = [
        'red'    => "\033[31m",
        'green'  => "\033[32m",
        'yellow' => "\033[33m",
        'cyan'   => "\033[36m",
        'white'  => "\033[37m",
        'gray'   => "\033[90m",
        'default'=> "\033[0m",
    ];

    public function __construct(bool $forceColor = false)
    {
        $this->forceColor = $forceColor;
        $this->colorSupport = $this->forceColor || $this->supportsColor();
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
        if ($this->quiet) {
            return;
        }
        $this->output($message, $color);
    }

    public function error(string $message): void
    {
        $this->output("✗ " . $message, 'red');
    }

    public function success(string $message): void
    {
        $this->output("✅ " . $message, 'green');
    }

    public function warning(string $message): void
    {
        $this->output("⚠️  " . $message, 'yellow');
    }

    public function debug(string $message): void
    {
        if (!$this->verbose) {
            return;
        }
        $this->output("[DEBUG] " . $message, 'gray');
    }

    public function progress(string $message): void
    {
        if ($this->quiet) {
            return;
        }
        $this->output("🔍 " . $message, 'cyan');
    }

    public function title(string $message): void
    {
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
        if ($this->quiet) {
            return;
        }
        echo "\n";
    }

    public function table(array $headers, array $rows): void
    {
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

        if ($this->colorSupport && isset($this->colorMap[$color])) {
            $reset = "\033[0m";
            echo $this->colorMap[$color] . $message . $reset . "\n";
        } else {
            echo $message . "\n";
        }
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
