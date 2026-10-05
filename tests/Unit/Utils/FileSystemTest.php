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

namespace SchemaSpyCli\Tests\Unit\Utils;

use PHPUnit\Framework\TestCase;
use SchemaSpyCli\Exceptions\FileNotFoundException;
use SchemaSpyCli\Tests\Support\TempDir;
use SchemaSpyCli\Utils\FileSystem;

/**
 * [Description FileSystemTest]
 * Tests unitaires pour FileSystem (création, copie, suppression de dossiers, taille, empreinte).
 */
final class FileSystemTest extends TestCase
{
    private TempDir $tmp;
    private FileSystem $fs;

    protected function setUp(): void
    {
        $this->tmp = new TempDir();
        $this->fs = new FileSystem();
    }

    protected function tearDown(): void
    {
        $this->tmp->remove();
    }

    // --- ensureDirectory ------------------------------------------------

    public function testEnsureDirectoryCreatesNestedFolders(): void
    {
        $path = $this->tmp->path . '/a/b/c';

        $this->fs->ensureDirectory($path);

        $this->assertDirectoryExists($path);
    }

    public function testEnsureDirectoryIsIdempotentAndKeepsExistingContent(): void
    {
        $file = $this->tmp->file('exists/keep.txt', 'contenu');

        $this->fs->ensureDirectory($this->tmp->path . '/exists');
        $this->fs->ensureDirectory($this->tmp->path . '/exists');

        $this->assertSame('contenu', file_get_contents($file));
    }

    /** Régression : mkdir() émettait un avertissement PHP en plus de l'exception. */
    public function testEnsureDirectoryFailureIsAnExceptionWithoutPhpWarning(): void
    {
        $blocker = $this->tmp->file('fichier.txt', 'je suis un fichier');

        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('Impossible de créer le dossier');
        $this->fs->ensureDirectory($blocker . '/sous-dossier');
    }

    public function testEnsureDirectoryOnAnExistingFileIsReportedAsFailure(): void
    {
        $file = $this->tmp->file('plain.txt');

        $this->expectException(FileNotFoundException::class);
        $this->fs->ensureDirectory($file);
    }

    // --- removeDirectory ------------------------------------------------

    public function testRemoveDirectoryReturnsFalseWhenMissing(): void
    {
        $this->assertFalse($this->fs->removeDirectory($this->tmp->path . '/absent'));
    }

    public function testRemoveDirectoryReturnsFalseForAFile(): void
    {
        $file = $this->tmp->file('f.txt');

        $this->assertFalse($this->fs->removeDirectory($file));
        $this->assertFileExists($file, 'un fichier n\'est jamais supprimé par removeDirectory');
    }

    public function testRemoveDirectoryDeletesTheWholeTree(): void
    {
        $this->tmp->file('tree/a.txt', 'a');
        $this->tmp->file('tree/sub/b.txt', 'b');
        $this->tmp->file('tree/sub/deep/c.txt', 'c');
        $this->tmp->dir('tree/vide');

        $this->assertTrue($this->fs->removeDirectory($this->tmp->path . '/tree'));

        $this->assertDirectoryDoesNotExist($this->tmp->path . '/tree');
    }

    public function testRemoveDirectoryOnAnEmptyFolder(): void
    {
        $this->tmp->dir('empty');

        $this->assertTrue($this->fs->removeDirectory($this->tmp->path . '/empty'));
        $this->assertDirectoryDoesNotExist($this->tmp->path . '/empty');
    }

    public function testRemoveDirectoryLeavesSiblingsUntouched(): void
    {
        $this->tmp->file('target/x.txt');
        $sibling = $this->tmp->file('sibling/y.txt', 'garde');

        $this->fs->removeDirectory($this->tmp->path . '/target');

        $this->assertSame('garde', file_get_contents($sibling));
    }

    /**
     * Régression (destructif) : un lien symbolique vers un autre dossier était suivi, et le
     * contenu de sa cible supprimé. Le lien doit être supprimé, jamais parcouru.
     */
    public function testRemoveDirectoryNeverFollowsSymlinks(): void
    {
        $outside = $this->tmp->file('precieux/donnees.txt', 'à conserver');
        $this->tmp->dir('tree');
        $link = $this->tmp->path . '/tree/lien';

        if (!@symlink($this->tmp->path . '/precieux', $link)) {
            $this->markTestSkipped('Création de liens symboliques non autorisée dans cet environnement.');
        }

        $this->assertTrue($this->fs->removeDirectory($this->tmp->path . '/tree'));

        $this->assertDirectoryDoesNotExist($this->tmp->path . '/tree');
        $this->assertFileExists($outside, 'la cible du lien ne doit pas être touchée');
        $this->assertSame('à conserver', file_get_contents($outside));
    }

    /** Variante Windows : une jonction de dossier (mklink /J) ne doit pas faire supprimer sa cible. */
    public function testRemoveDirectoryNeverTouchesTheTargetOfAWindowsJunction(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->markTestSkipped('Jonctions de dossier : spécifique à Windows.');
        }

        $outside = $this->tmp->file('precieux/donnees.txt', 'à conserver');
        $this->tmp->dir('tree');
        $link = str_replace('/', '\\', $this->tmp->path . '/tree/jonction');
        $target = str_replace('/', '\\', $this->tmp->path . '/precieux');
        shell_exec('cmd /c mklink /J ' . escapeshellarg($link) . ' ' . escapeshellarg($target) . ' 2>&1');
        if (!file_exists($link)) {
            $this->markTestSkipped('Création de jonction impossible dans cet environnement.');
        }

        $this->assertTrue($this->fs->removeDirectory($this->tmp->path . '/tree'));

        $this->assertDirectoryDoesNotExist($this->tmp->path . '/tree');
        $this->assertSame('à conserver', file_get_contents($outside), 'la cible de la jonction ne doit pas être touchée');
    }
    // --- copyDirectory --------------------------------------------------

    public function testCopyDirectoryReplicatesFilesAndSubfolders(): void
    {
        $this->tmp->file('src/a.txt', 'A');
        $this->tmp->file('src/sub/b.txt', 'B');
        $this->tmp->file('src/sub/deep/c.txt', 'C');
        $this->tmp->dir('src/vide');

        $this->fs->copyDirectory($this->tmp->path . '/src', $this->tmp->path . '/dst');

        $this->assertSame('A', file_get_contents($this->tmp->path . '/dst/a.txt'));
        $this->assertSame('B', file_get_contents($this->tmp->path . '/dst/sub/b.txt'));
        $this->assertSame('C', file_get_contents($this->tmp->path . '/dst/sub/deep/c.txt'));
        $this->assertDirectoryExists($this->tmp->path . '/dst/vide');
    }

    public function testCopyDirectoryLeavesTheSourceIntact(): void
    {
        $this->tmp->file('src/a.txt', 'A');

        $this->fs->copyDirectory($this->tmp->path . '/src', $this->tmp->path . '/dst');

        $this->assertSame('A', file_get_contents($this->tmp->path . '/src/a.txt'));
    }

    public function testCopyDirectoryOverwritesExistingFilesAndKeepsOthers(): void
    {
        $this->tmp->file('src/a.txt', 'nouveau');
        $this->tmp->file('dst/a.txt', 'ancien');
        $this->tmp->file('dst/autre.txt', 'conservé');

        $this->fs->copyDirectory($this->tmp->path . '/src', $this->tmp->path . '/dst');

        $this->assertSame('nouveau', file_get_contents($this->tmp->path . '/dst/a.txt'));
        $this->assertSame('conservé', file_get_contents($this->tmp->path . '/dst/autre.txt'));
    }

    public function testCopyDirectoryFailsWhenSourceIsMissing(): void
    {
        $this->expectException(FileNotFoundException::class);
        $this->expectExceptionMessage('Dossier source introuvable');
        $this->fs->copyDirectory($this->tmp->path . '/absent', $this->tmp->path . '/dst');
    }

    public function testCopyDirectoryHandlesBinaryFilesByteForByte(): void
    {
        $binary = random_bytes(4096);
        $this->tmp->file('src/data.bin', $binary);

        $this->fs->copyDirectory($this->tmp->path . '/src', $this->tmp->path . '/dst');

        $this->assertSame($binary, file_get_contents($this->tmp->path . '/dst/data.bin'));
    }

    /**
     * Régression : copier un dossier dans l'un de ses sous-dossiers se rappelait indéfiniment
     * sur ses propres copies. La destination est ignorée pendant le parcours.
     */
    public function testCopyDirectoryIntoItsOwnSubfolderTerminatesWithoutRecursion(): void
    {
        $this->tmp->file('src/a.txt', 'A');
        $this->tmp->file('src/sub/b.txt', 'B');

        $this->fs->copyDirectory($this->tmp->path . '/src', $this->tmp->path . '/src/sauvegarde');

        $this->assertSame('A', file_get_contents($this->tmp->path . '/src/sauvegarde/a.txt'));
        $this->assertSame('B', file_get_contents($this->tmp->path . '/src/sauvegarde/sub/b.txt'));
        $this->assertDirectoryDoesNotExist($this->tmp->path . '/src/sauvegarde/sauvegarde', 'pas de copie de la copie');
    }

    public function testCopyDirectoryIntoANestedFolderOfASubfolder(): void
    {
        $this->tmp->file('src/x/f.txt', 'F');

        $this->fs->copyDirectory($this->tmp->path . '/src', $this->tmp->path . '/src/x/y');

        $this->assertSame('F', file_get_contents($this->tmp->path . '/src/x/y/x/f.txt'));
        $this->assertDirectoryDoesNotExist($this->tmp->path . '/src/x/y/x/y', 'la destination est ignorée à tous les niveaux');
    }

    // --- getFileSize ----------------------------------------------------

    /** @dataProvider sizes */
    public function testFileSizeIsHumanReadable(int $bytes, string $expected): void
    {
        $file = $this->tmp->file('s.bin', str_repeat('x', $bytes));

        $this->assertSame($expected, $this->fs->getFileSize($file));
    }

    public static function sizes(): array
    {
        return [
            'vide'               => [0, '0 B'],
            'un octet'           => [1, '1 B'],
            'juste sous 1 KB'    => [1023, '1023 B'],
            '1 KB'               => [1024, '1 KB'],
            '1,5 KB'             => [1536, '1.5 KB'],
            'arrondi à 2 décimales' => [1024 + 5, '1 KB'],
            '1 MB'               => [1024 * 1024, '1 MB'],
            '2,5 MB'             => [(int) (2.5 * 1024 * 1024), '2.5 MB'],
        ];
    }

    public function testFileSizeOfMissingFileOrFolderIsZero(): void
    {
        $this->assertSame('0 B', $this->fs->getFileSize($this->tmp->path . '/absent.bin'));
        $this->assertSame('0 B', $this->fs->getFileSize($this->tmp->dir('dossier')), 'un dossier n\'a pas de taille de fichier');
    }

    // --- getFileHash ----------------------------------------------------

    public function testFileHashIsSha256(): void
    {
        $file = $this->tmp->file('h.txt', 'abc');

        $this->assertSame('ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad', $this->fs->getFileHash($file));
    }

    public function testFileHashOfEmptyFile(): void
    {
        $this->assertSame(
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            $this->fs->getFileHash($this->tmp->file('vide.txt'))
        );
    }

    public function testFileHashDependsOnContentOnly(): void
    {
        $a = $this->fs->getFileHash($this->tmp->file('a.txt', 'même contenu'));
        $b = $this->fs->getFileHash($this->tmp->file('b.txt', 'même contenu'));
        $c = $this->fs->getFileHash($this->tmp->file('c.txt', 'autre contenu'));

        $this->assertSame($a, $b);
        $this->assertNotSame($a, $c);
    }

    /** Régression : hash_file() sur un dossier renvoie false, incompatible avec le type ?string. */
    public function testFileHashIsNullForMissingFileOrFolder(): void
    {
        $this->assertNull($this->fs->getFileHash($this->tmp->path . '/absent.txt'));
        $this->assertNull($this->fs->getFileHash($this->tmp->dir('dossier')));
    }

    // --- extension / nom / basename -------------------------------------

    /** @dataProvider names */
    public function testNameParts(string $path, string $extension, string $name, string $basename): void
    {
        $this->assertSame($extension, $this->fs->getFileExtension($path));
        $this->assertSame($name, $this->fs->getFileName($path));
        $this->assertSame($basename, $this->fs->getFileBasename($path));
    }

    public static function names(): array
    {
        return [
            'simple'               => ['rapport.html', 'html', 'rapport', 'rapport.html'],
            'avec dossiers'        => ['a/b/c.php', 'php', 'c', 'c.php'],
            'double extension'     => ['archive.tar.gz', 'gz', 'archive.tar', 'archive.tar.gz'],
            'sans extension'       => ['Makefile', '', 'Makefile', 'Makefile'],
            'fichier caché'        => ['.env', 'env', '', '.env'],
            'jar versionné'        => ['jdbc/ojdbc11-23.26.3.0.0.jar', 'jar', 'ojdbc11-23.26.3.0.0', 'ojdbc11-23.26.3.0.0.jar'],
            'chemin vide'          => ['', '', '', ''],
        ];
    }

    // --- méthodes d'information sur les fichiers -------------------------

    /** @dataProvider dirnames */
    public function testFileDirname(string $path, string $expected): void
    {
        $this->assertSame($expected, $this->fs->getFileDirname($path));
    }

    public static function dirnames(): array
    {
        return [
            'chemin imbriqué' => ['a/b/c.txt', 'a/b'],
            'nom seul'        => ['c.txt', '.'],
            'racine'          => ['/c.txt', DIRECTORY_SEPARATOR],
        ];
    }

    /** @dataProvider jarNames */
    public function testJarAndPropertiesDetection(string $file, bool $isJar, bool $isProperties): void
    {
        $this->assertSame($isJar, $this->fs->isJarFile($file));
        $this->assertSame($isProperties, $this->fs->isPropertiesFile($file));
    }

    public static function jarNames(): array
    {
        return [
            'jar'                  => ['jdbc/ojdbc11.jar', true, false],
            'JAR en majuscules'    => ['OJDBC11.JAR', true, false],
            'jar mixte'            => ['driver.Jar', true, false],
            'properties'           => ['app.properties', false, true],
            'PROPERTIES majuscule' => ['APP.PROPERTIES', false, true],
            'sans extension'       => ['jar', false, false],
            'jar.txt'              => ['monjar.txt', false, false],
            'double extension'     => ['archive.jar.bak', false, false],
        ];
    }

    public function testTypePredicates(): void
    {
        $file = $this->tmp->file('f.txt');
        $dir = $this->tmp->dir('d');
        $missing = $this->tmp->path . '/absent';

        $this->assertTrue($this->fs->isFile($file));
        $this->assertFalse($this->fs->isFile($dir));
        $this->assertFalse($this->fs->isFile($missing));

        $this->assertTrue($this->fs->isDirectory($dir));
        $this->assertFalse($this->fs->isDirectory($file));
        $this->assertFalse($this->fs->isDirectory($missing));

        $this->assertTrue($this->fs->fileExists($file));
        $this->assertTrue($this->fs->fileExists($dir), 'fileExists accepte aussi les dossiers');
        $this->assertFalse($this->fs->fileExists($missing));
    }

    public function testModificationTime(): void
    {
        $file = $this->tmp->file('m.txt');
        touch($file, 1_000_000_000);
        clearstatcache();

        $this->assertSame(1_000_000_000, $this->fs->getFileMtime($file));
        $this->assertNull($this->fs->getFileMtime($this->tmp->path . '/absent'));
    }

    public function testChangeTime(): void
    {
        $file = $this->tmp->file('c.txt');

        $this->assertEqualsWithDelta(time(), $this->fs->getFileCtime($file), 5);
        $this->assertNull($this->fs->getFileCtime($this->tmp->path . '/absent'));
    }

    public function testPermissionsAreFourOctalDigits(): void
    {
        $file = $this->tmp->file('p.txt');

        $this->assertMatchesRegularExpression('/^\d{4}$/', $this->fs->getFilePermissions($file));
        $this->assertNull($this->fs->getFilePermissions($this->tmp->path . '/absent'));
    }

    public function testPermissionsReflectChmodOnUnix(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('chmod sans effet sous Windows.');
        }
        $file = $this->tmp->file('x.sh');
        chmod($file, 0640);
        clearstatcache();

        $this->assertSame('0640', $this->fs->getFilePermissions($file));
    }
}
