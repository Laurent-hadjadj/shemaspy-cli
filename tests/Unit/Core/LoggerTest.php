<?php
/**
 * Tests unitaires pour la classe Logger
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Core\Logger;

final class LoggerTest extends TestCase
{
    private Logger $logger;

    protected function setUp(): void
    {
        $this->logger = new Logger();
    }

    public function testSetQuiet(): void
    {
        $this->logger->setQuiet(true);
        $this->assertTrue($this->logger->isQuiet());
        
        $this->logger->setQuiet(false);
        $this->assertFalse($this->logger->isQuiet());
    }

    public function testSetVerbose(): void
    {
        $this->logger->setVerbose(true);
        $this->assertTrue($this->logger->isVerbose());
        
        $this->logger->setVerbose(false);
        $this->assertFalse($this->logger->isVerbose());
    }

    public function testInfoInQuietMode(): void
    {
        // Capturer la sortie
        ob_start();
        
        $this->logger->setQuiet(true);
        $this->logger->info('Test message');
        
        $output = ob_get_clean();
        $this->assertEmpty($output);
    }

    public function testInfoNotInQuietMode(): void
    {
        ob_start();
        
        $this->logger->setQuiet(false);
        $this->logger->info('Test message');
        
        $output = ob_get_clean();
        $this->assertStringContainsString('Test message', $output);
    }

    public function testError(): void
    {
        ob_start();
        
        $this->logger->error('Error message');
        
        $output = ob_get_clean();
        $this->assertStringContainsString('✗ Error message', $output);
    }

    public function testSuccess(): void
    {
        ob_start();
        
        $this->logger->success('Success message');
        
        $output = ob_get_clean();
        $this->assertStringContainsString('✅ Success message', $output);
    }

    public function testWarning(): void
    {
        ob_start();
        
        $this->logger->warning('Warning message');
        
        $output = ob_get_clean();
        $this->assertStringContainsString('⚠️  Warning message', $output);
    }

    public function testDebugInVerboseMode(): void
    {
        ob_start();
        
        $this->logger->setVerbose(true);
        $this->logger->debug('Debug message');
        
        $output = ob_get_clean();
        $this->assertStringContainsString('[DEBUG] Debug message', $output);
    }

    public function testDebugNotInVerboseMode(): void
    {
        ob_start();
        
        $this->logger->setVerbose(false);
        $this->logger->debug('Debug message');
        
        $output = ob_get_clean();
        $this->assertEmpty($output);
    }

    public function testTitle(): void
    {
        ob_start();
        
        $this->logger->title('Test Title');
        
        $output = ob_get_clean();
        $this->assertStringContainsString('Test Title', $output);
        $this->assertStringContainsString('════', $output);
    }

    public function testPrompt(): void
    {
        // Injecter un flux mémoire simulant la saisie utilisateur
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "test_input\n");
        rewind($stream);

        ob_start();
        $result = $this->logger->prompt('Test prompt', 'default', $stream);
        ob_end_clean();

        fclose($stream);
        $this->assertEquals('test_input', $result);
    }

    public function testPromptWithDefault(): void
    {
        // Saisie vide -> retour à la valeur par défaut
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "\n");
        rewind($stream);

        ob_start();
        $result = $this->logger->prompt('Test prompt', 'default_value', $stream);
        ob_end_clean();

        fclose($stream);
        $this->assertEquals('default_value', $result);
    }

    public function testPromptPassword(): void
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, "secret_password\n");
        rewind($stream);

        ob_start();
        $result = $this->logger->promptPassword('Password', $stream);
        ob_end_clean();

        fclose($stream);
        $this->assertEquals('secret_password', $result);
    }

    public function testFileLoggingDisabledByDefault(): void
    {
        // new Logger() sans chemin explicite : aucune sortie fichier (utilisé par les tests)
        $this->assertNull($this->logger->getLogFile());
    }

    public function testFileLoggingCapturesFullTraceEvenInQuietMode(): void
    {
        $logFile = sys_get_temp_dir() . '/logger_test_' . uniqid() . '.log';
        $logger = new Logger(false, $logFile);

        $logger->setQuiet(true);
        $logger->setVerbose(false);

        ob_start();
        $logger->info('Message info');
        $logger->warning('Message warning');
        $logger->debug('Message debug'); // pas affiché en console (verbose=false), mais tracé en fichier
        $output = ob_get_clean();

        $this->assertEmpty($output, 'La console doit rester silencieuse en mode quiet');

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('Message info', $content);
        $this->assertStringContainsString('Message warning', $content);
        $this->assertStringContainsString('Message debug', $content);

        unlink($logFile);
    }

    public function testFileLoggingIsOverwrittenOnConstruction(): void
    {
        $logFile = sys_get_temp_dir() . '/logger_test_' . uniqid() . '.log';
        file_put_contents($logFile, 'contenu de la précédente exécution');

        new Logger(false, $logFile);

        $content = file_get_contents($logFile);
        $this->assertStringNotContainsString('contenu de la précédente exécution', $content);

        unlink($logFile);
    }
}
