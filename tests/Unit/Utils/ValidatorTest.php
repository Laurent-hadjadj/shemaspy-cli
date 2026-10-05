<?php
/**
 * Tests unitaires pour la classe Validator
 * 
 * @author  Laurent HADJADJ - maMoulinette
 * @version 3.0.0
 */

namespace SchemaSpyCli\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Utils\Validator;
use SchemaSpyCli\Exceptions\ValidationException;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    public function testValidateDatabaseType(): void
    {
        // Types valides
        $this->validator->validateDatabaseType('postgresql');
        $this->validator->validateDatabaseType('oracle');
        $this->validator->validateDatabaseType('mysql');
        
        // Type invalide
        $this->expectException(ValidationException::class);
        $this->validator->validateDatabaseType('invalid');
    }

    public function testValidateHost(): void
    {
        // Hosts valides
        $this->validator->validateHost('localhost');
        $this->validator->validateHost('192.168.1.1');
        $this->validator->validateHost('db-server.example.com');
        
        // Hosts invalides
        $this->expectException(ValidationException::class);
        $this->validator->validateHost('');
    }

    public function testValidatePort(): void
    {
        // Ports valides
        $this->validator->validatePort(5432);
        $this->validator->validatePort(3306);
        $this->validator->validatePort(1521);
        
        // Ports invalides
        $this->expectException(ValidationException::class);
        $this->validator->validatePort(0);
    }

    public function testValidatePortOutOfRange(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validatePort(70000);
    }

    public function testValidateDatabase(): void
    {
        // Noms de base valides
        $this->validator->validateDatabase('testdb');
        $this->validator->validateDatabase('test_db');
        $this->validator->validateDatabase('test-db');
        $this->validator->validateDatabase('TestDB');
        $this->validator->validateDatabase('testdb123');
        
        // Noms invalides
        $this->expectException(ValidationException::class);
        $this->validator->validateDatabase('');
    }

    public function testValidateDatabaseInvalidChars(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateDatabase('test db');
    }

    public function testValidateSchema(): void
    {
        // Schémas valides
        $this->validator->validateSchema('public');
        $this->validator->validateSchema('my_schema');
        $this->validator->validateSchema('schema-123');
        
        // Schémas invalides
        $this->expectException(ValidationException::class);
        $this->validator->validateSchema('');
    }

    public function testValidateUser(): void
    {
        // Utilisateurs valides
        $this->validator->validateUser('postgres');
        $this->validator->validateUser('admin');
        $this->validator->validateUser('user123');
        
        // Utilisateur invalide
        $this->expectException(ValidationException::class);
        $this->validator->validateUser('');
    }

    public function testValidatePassword(): void
    {
        // Mots de passe valides
        $this->validator->validatePassword('password');
        $this->validator->validatePassword('my_p@ssw0rd');
        $this->validator->validatePassword('123456');
        
        // Mot de passe invalide
        $this->expectException(ValidationException::class);
        $this->validator->validatePassword('');
    }

    public function testValidateOutputDir(): void
    {
        // Dossiers valides
        $this->validator->validateOutputDir('output');
        $this->validator->validateOutputDir('test_output');
        $this->validator->validateOutputDir('output/dir');
        $this->validator->validateOutputDir('output-dir');
        
        // Dossier invalide
        $this->expectException(ValidationException::class);
        $this->validator->validateOutputDir('');
    }

    public function testValidateJdbcFile(): void
    {
        // Créer un fichier JAR temporaire pour le test
        $tempFile = sys_get_temp_dir() . '/test.jar';
        file_put_contents($tempFile, 'dummy');
        
        $this->validator->validateJdbcFile($tempFile);
        
        unlink($tempFile);
        
        // Fichier inexistant
        $this->expectException(ValidationException::class);
        $this->validator->validateJdbcFile('/path/to/nonexistent.jar');
    }

    public function testValidateJdbcFileWrongExtension(): void
    {
        $tempFile = sys_get_temp_dir() . '/test.txt';
        file_put_contents($tempFile, 'dummy');
        
        $this->expectException(ValidationException::class);
        $this->validator->validateJdbcFile($tempFile);
        
        unlink($tempFile);
    }

    public function testValidateJavaVersion(): void
    {
        // Version valide
        $this->validator->validateJavaVersion('11.0.2');
        $this->validator->validateJavaVersion('17.0.1');
        
        // Version invalide
        $this->expectException(ValidationException::class);
        $this->validator->validateJavaVersion('1.8.0');
    }

    /** @dataProvider dottedDatabaseNames */
    public function testValidateDatabaseAcceptsDotsForOracleServiceNames(string $name): void
    {
        $this->validator->validateDatabase($name);
        $this->addToAssertionCount(1);
    }

    public static function dottedDatabaseNames(): array
    {
        return [
            'service avec domaine'  => ['FONDS.exemple.fr'],
            'domaine long'          => ['mon_service.direction.exemple-entreprise.fr'],
            'un seul point'         => ['svc.prod'],
            'SID simple (inchangé)' => ['FONDSPRD'],
        ];
    }

    /** @dataProvider badDottedDatabaseNames */
    public function testValidateDatabaseRejectsMisplacedDots(string $name): void
    {
        $this->expectException(\SchemaSpyCli\Exceptions\ValidationException::class);
        $this->expectExceptionMessage('Nom de base de données invalide');
        $this->validator->validateDatabase($name);
    }

    public static function badDottedDatabaseNames(): array
    {
        return [
            'point initial'      => ['.cache'],
            'point final'        => ['svc.'],
            'points consécutifs' => ['svc..prod'],
            'remontée de chemin' => ['..'],
            'seulement un point' => ['.'],
            'espace après point' => ['svc. prod'],
            'slash'              => ['svc/prod'],
        ];
    }
}
