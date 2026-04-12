<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Interface\CommandInterface;

#[Command(name: 'make:deployment', description: 'Deploiement FTP d\'un projet NeoPHP')]
final class MakeDeploymentCommand implements CommandInterface
{
    private float $startTime;

    private const UNZIP_SCRIPT_NAME = '__neo_unzip.php';

    private const EXCLUDED_CONFIG_FILES = [
        'deploy.config.php',
    ];

    public function getName(): string
    {
        return 'make:deployment';
    }

    public function getDescription(): string
    {
        return 'Deploiement FTP d\'un projet NeoPHP';
    }

    public function getHelp(): string
    {
        return <<<HELP
Commande : make:deployment
Description : Génère les fichiers et la configuration nécessaires pour le déploiement d'un projet.

Usage :
  php bin/neo make:deployment --project=<NomDuProjet>

Options :
  --project=        Nom du projet cible dans ./src/ (requis)

Exemples :
  php bin/neo make:deployment --project=MonProjet
  php bin/neo make:deployment --project=TestApp
HELP;
    }

    public function execute(array $args): void
    {
        $this->startTime = microtime(true);

        $project = $this->getOption($args, '--project');

        if (!$project) {
            echo "Erreur : --project requis.\n";
            echo "Usage : php bin/neo make:deployment --project=MonProjet\n";
            return;
        }

        $deployConfigPath = ROOT_DIR . "src/$project/Config/deploy.config.php";
        if (!file_exists($deployConfigPath)) {
            echo "Erreur : Config introuvable : $deployConfigPath\n";
            return;
        }

        $config = require $deployConfigPath;

        if (!$this->validateConfig($config)) {
            return;
        }

        $ftpHost   = $config['ftp']['host'];
        $ftpUser   = $config['ftp']['user'];
        $ftpPass   = $config['ftp']['pass'];
        $domain    = $config['remote']['domain'];
        $ftpRoot   = '/' . trim($config['remote']['framework_dir'], '/');
        $ftpPublic = '/' . trim($config['remote']['public_dir'], '/');

        $tmpDir = $this->getTempDir($project);
        $this->cleanDir($tmpDir);

        echo "\n[0/6] Patch de app.config.php pour la production...\n";
        $appConfigPath    = ROOT_DIR . "src/$project/Config/app.config.php";
        $tmpAppConfigPath = null;

        if (!file_exists($appConfigPath)) {
            echo "      [WARN] app.config.php introuvable, patch ignore.\n";
        } else {
            $appConfig                = require $appConfigPath;
            $appConfig['environment'] = 'prod';
            $appConfig['access']      = $domain;

            $tmpAppConfigPath = "$tmpDir/app.config.php";
            file_put_contents($tmpAppConfigPath, $this->generateConfigFile($appConfig));

            echo "      environment => prod\n";
            echo "      access      => $domain\n";
        }

        $indexPath    = ROOT_DIR . 'public/index.php';
        $tmpIndexPath = null;

        if (!file_exists($indexPath)) {
            echo "      [WARN] public/index.php introuvable, patch ignore.\n";
        } else {
            $indexContent   = file_get_contents($indexPath);
            $neoDir         = basename(trim($ftpRoot, '/'));
            $patchedContent = str_replace(
                "require_once __DIR__ . '/../vendor/autoload.php'",
                "require_once __DIR__ . '/../{$neoDir}/vendor/autoload.php'",
                $indexContent
            );
            if ($patchedContent !== $indexContent) {
                $tmpIndexPath = "$tmpDir/index.php";
                file_put_contents($tmpIndexPath, $patchedContent);
                echo "      index.php   => $neoDir/vendor/autoload.php\n";
            }
        }

        echo "[1/6] Fusion des composer.json...\n";
        $mergedComposer = $this->buildMergedComposer($project);
        file_put_contents(
            "$tmpDir/composer.json",
            json_encode($mergedComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        echo "[2/6] Installation des dependances (composer update)...\n";
        $composerCmd = 'composer update --working-dir=' . escapeshellarg($tmpDir) . ' --no-dev --optimize-autoloader';
        passthru($composerCmd, $code);

        if ($code !== 0) {
            echo "\nErreur : composer update a echoue. Deploiement annule.\n";
            return;
        }

        echo "[3/6] Compression du vendor/...\n";
        $vendorDir = "$tmpDir/vendor";
        $vendorZip = "$tmpDir/vendor.zip";

        if (!$this->zipDirectory($vendorDir, $vendorZip)) {
            echo "Erreur : Compression du vendor/ echouee.\n";
            return;
        }

        $sizeMb = round(filesize($vendorZip) / 1024 / 1024, 2);
        echo "      vendor.zip : {$sizeMb} Mo\n";

        echo "[4/6] Connexion FTP a $ftpHost...\n";
        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);

        if (!$conn) {
            echo "Erreur : Connexion FTP echouee.\n";
            return;
        }

        echo "[5/6] Upload des fichiers...\n";

        $filesToUpload = [];
        $this->collectFiles(ROOT_DIR . 'neo', "$ftpRoot/neo", [], $filesToUpload);
        $this->collectFiles(
            ROOT_DIR . "src/$project",
            "$ftpRoot/src/$project",
            array_merge(['Storage', '.git', 'vendor'], self::EXCLUDED_CONFIG_FILES),
            $filesToUpload
        );
        $this->collectFiles(ROOT_DIR . 'public', $ftpPublic, [], $filesToUpload);

        if ($tmpAppConfigPath !== null) {
            $remoteAppConfig = "$ftpRoot/src/$project/Config/app.config.php";
            foreach ($filesToUpload as &$entry) {
                if ($entry[1] === $remoteAppConfig) {
                    $entry[0] = $tmpAppConfigPath;
                    break;
                }
            }
            unset($entry);
        }

        if ($tmpIndexPath !== null) {
            $remoteIndex = rtrim($ftpPublic, '/') . '/index.php';
            foreach ($filesToUpload as &$entry) {
                if ($entry[1] === $remoteIndex) {
                    $entry[0] = $tmpIndexPath;
                    break;
                }
            }
            unset($entry);
        }

        $total    = count($filesToUpload);
        $uploaded = 0;
        $errors   = 0;

        foreach ($filesToUpload as [$localPath, $remotePath, $remoteDir]) {
            $this->ftp_mkdir_rec($conn, $remoteDir);
            $ok = @ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
            $ok ? $uploaded++ : $errors++;
            $this->printProgress($uploaded + $errors, $total);
        }

        echo "\n";

        if ($errors > 0) {
            echo "      $uploaded fichiers uploades, $errors erreurs.\n";
        } else {
            echo "      $uploaded fichiers uploades.\n";
        }

        ftp_close($conn);

        // --- composer.json / composer.lock ---

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);
        if (!$conn) {
            echo "Erreur : reconnexion FTP echouee (composer).\n";
            return;
        }

        ftp_put($conn, "$ftpRoot/composer.json", "$tmpDir/composer.json", FTP_BINARY);

        if (file_exists("$tmpDir/composer.lock")) {
            ftp_put($conn, "$ftpRoot/composer.lock", "$tmpDir/composer.lock", FTP_BINARY);
        }

        ftp_close($conn);

        // --- vendor.zip via cURL ---

        echo "      Upload vendor.zip ($sizeMb Mo)...\n";

        $remoteZip = "$ftpRoot/vendor.zip";
        $ok = $this->ftpPutWithCurl($remoteZip, $vendorZip, $ftpHost, $ftpUser, $ftpPass);

        if (!$ok) {
            echo "Erreur critique : upload vendor.zip echoue apres plusieurs tentatives.\n";
            echo "Deploiement annule.\n";
            return;
        }

        echo "      vendor.zip uploade.\n";

        // --- script de dezippage ---

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);
        if (!$conn) {
            echo "Erreur : reconnexion FTP echouee (unzip script).\n";
            return;
        }

        $unzipScript  = $this->generateUnzipScript($ftpRoot, $ftpPublic);
        $tmpScript    = "$tmpDir/" . self::UNZIP_SCRIPT_NAME;
        $remoteScript = rtrim($ftpPublic, '/') . '/' . self::UNZIP_SCRIPT_NAME;

        file_put_contents($tmpScript, $unzipScript);
        ftp_put($conn, $remoteScript, $tmpScript, FTP_BINARY);
        ftp_close($conn);

        echo "[6/6] Dezippage cote serveur...\n";

        $scriptUrl = "https://$domain/" . self::UNZIP_SCRIPT_NAME;
        $response  = $this->httpGet($scriptUrl);

        if ($response === false) {
            $response = $this->httpGet("http://$domain/" . self::UNZIP_SCRIPT_NAME);
        }

        if ($response !== false) {
            $decoded = json_decode(trim($response), true);
            $status  = $decoded['status']  ?? 'unknown';
            $message = $decoded['message'] ?? trim($response);
            echo "      Serveur : $message\n";

            if ($status !== 'success') {
                echo "      Attention : le dezippage a rencontre un probleme.\n";
            }
        } else {
            echo "      Attention : impossible de joindre le script de dezippage.\n";
            echo "      Appelez manuellement : $scriptUrl\n";
        }

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);
        if ($conn) {
            @ftp_delete($conn, $remoteScript);
            @ftp_delete($conn, "$ftpRoot/vendor.zip");
            ftp_close($conn);
        }

        $this->cleanDir($tmpDir);
        rmdir($tmpDir);

        $elapsed = round(microtime(true) - $this->startTime, 2);
        echo "\nDeploiement terminé sur $domain en {$elapsed}s.\n";
    }

    private function ftpConnect(string $host, string $user, string $pass): mixed
    {
        $conn = @ftp_connect($host, 21, 30);
        if (!$conn || !@ftp_login($conn, $user, $pass)) {
            return false;
        }
        ftp_pasv($conn, true);
        return $conn;
    }

    private function ftpPutWithCurl(
        string $remote,
        string $local,
        string $host,
        string $user,
        string $pass,
        int $maxRetries = 3
    ): bool {
        if (!extension_loaded('curl')) {
            echo "      [WARN] cURL non disponible, fallback ftp_put.\n";
            $conn = $this->ftpConnect($host, $user, $pass);
            if (!$conn) {
                return false;
            }
            $ok = @ftp_put($conn, $remote, $local, FTP_BINARY);
            ftp_close($conn);
            return (bool) $ok;
        }

        for ($i = 0; $i < $maxRetries; $i++) {
            if ($i > 0) {
                echo "      [Retry $i/$maxRetries] Nouvel essai dans 3s...\n";
                sleep(3);
            }

            $fp = fopen($local, 'rb');
            if (!$fp) {
                echo "      Erreur : impossible d'ouvrir $local\n";
                return false;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => "ftp://$host$remote",
                CURLOPT_USERPWD        => "$user:$pass",
                CURLOPT_UPLOAD         => true,
                CURLOPT_INFILE         => $fp,
                CURLOPT_INFILESIZE     => filesize($local),
                CURLOPT_FTP_USE_EPSV   => false,
                CURLOPT_FTP_USE_EPRT   => false,
                CURLOPT_FTPSSLAUTH     => CURLFTPAUTH_DEFAULT,
                CURLOPT_TIMEOUT        => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
            ]);

            $ok     = curl_exec($ch);
            $errno  = curl_errno($ch);
            $errmsg = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($ok && $errno === 0) {
                return true;
            }

            echo "      cURL erreur $errno : $errmsg\n";
        }

        return false;
    }

    private function generateConfigFile(array $config): string
    {
        $exported = var_export($config, true);
        return "<?php\ndeclare(strict_types=1);\n\nreturn " . $exported . ";\n";
    }

    private function printProgress(int $current, int $total): void
    {
        $width  = 30;
        $ratio  = $total > 0 ? $current / $total : 1;
        $filled = (int) round($ratio * $width);
        $empty  = $width - $filled;

        $bar = str_repeat('=', max(0, $filled - 1));
        if ($filled < $width) {
            $bar .= '>';
            $bar .= str_repeat('.', $empty);
        } else {
            $bar .= '=';
        }

        $label = "$current/$total";
        echo "\r      [$bar] $label";
    }

    private function collectFiles(
        string $local,
        string $remote,
        array $exclude,
        array &$list
    ): void {
        if (!is_dir($local)) {
            return;
        }

        $entries = scandir($local);
        if ($entries === false) {
            return;
        }

        foreach (array_diff($entries, ['.', '..']) as $file) {
            if (in_array($file, $exclude, true)) {
                continue;
            }

            $localPath  = "$local/$file";
            $remotePath = "$remote/$file";

            if (is_dir($localPath)) {
                $this->collectFiles($localPath, $remotePath, $exclude, $list);
            } else {
                $list[] = [$localPath, $remotePath, $remote];
            }
        }
    }

    private function buildMergedComposer(string $project): array
    {
        $rootComposerPath    = ROOT_DIR . 'composer.json';
        $projectComposerPath = ROOT_DIR . "src/$project/composer.json";

        $root = json_decode(file_get_contents($rootComposerPath), true) ?? [];
        $proj = file_exists($projectComposerPath)
            ? (json_decode(file_get_contents($projectComposerPath), true) ?? [])
            : [];

        $localPackageNames = [];
        foreach ($root['repositories'] ?? [] as $repo) {
            if (($repo['type'] ?? '') === 'path') {
                $localComposerPath = ROOT_DIR . trim($repo['url'], '/') . '/composer.json';
                if (file_exists($localComposerPath)) {
                    $local = json_decode(file_get_contents($localComposerPath), true);
                    if (!empty($local['name'])) {
                        $localPackageNames[] = $local['name'];
                    }
                }
            }
        }

        $rootRequire = $root['require'] ?? [];
        $projRequire = $proj['require'] ?? [];

        foreach ($localPackageNames as $name) {
            unset($rootRequire[$name]);
        }

        $mergedRequire = array_merge($rootRequire, $projRequire);

        $merged = [
            'name'              => $root['name'] ?? 'neo/deployment',
            'description'       => 'Merged composer for deployment - ' . $project,
            'type'              => 'project',
            'license'           => $root['license'] ?? 'MIT',
            'require'           => $mergedRequire,
            'minimum-stability' => $root['minimum-stability'] ?? 'stable',
            'prefer-stable'     => $root['prefer-stable'] ?? true,
        ];

        if (!empty($root['autoload'])) {
            $merged['autoload'] = $root['autoload'];
        }

        if (!empty($proj['autoload'])) {
            foreach ($proj['autoload'] as $type => $map) {
                $merged['autoload'][$type] = array_merge(
                    $merged['autoload'][$type] ?? [],
                    (array) $map
                );
            }
        }

        $externalRepos = array_values(array_filter(
            $root['repositories'] ?? [],
            fn($r) => ($r['type'] ?? '') !== 'path'
        ));

        if (!empty($externalRepos)) {
            $merged['repositories'] = $externalRepos;
        }

        return $merged;
    }

    private function zipDirectory(string $sourceDir, string $outZip): bool
    {
        if (!extension_loaded('zip')) {
            echo "Erreur : extension PHP zip non disponible.\n";
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($outZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $source   = realpath($sourceDir);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath     = $file->getRealPath();
            $relativePath = 'vendor/' . str_replace('\\', '/', substr($filePath, strlen($source) + 1));

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();
        return true;
    }

    private function generateUnzipScript(string $ftpRoot, string $ftpPublic): string
    {
        $root   = '/' . trim($ftpRoot, '/');
        $public = '/' . trim($ftpPublic, '/');

        $relative = ltrim(substr($public, strlen($root)), '/');
        $levels   = ($relative !== '') ? count(array_filter(explode('/', $relative))) : 0;

        $neo      = basename($root);
        $dirnameExpr = ($levels > 0)
            ? "dirname(__DIR__, $levels) . '/$neo'"
            : "__DIR__ . '/$neo'";

        $lines   = [];
        $lines[] = '<?php';
        $lines[] = '$frameworkDir = ' . $dirnameExpr . ';';
        $lines[] = '$vendorZip    = $frameworkDir . \'/vendor.zip\';';
        $lines[] = '$vendorTarget = $frameworkDir . \'/vendor\';';
        $lines[] = '';
        $lines[] = 'if (!file_exists($vendorZip)) {';
        $lines[] = '    http_response_code(404);';
        $lines[] = '    echo json_encode([\'status\' => \'error\', \'message\' => \'vendor.zip introuvable : \' . $vendorZip]);';
        $lines[] = '    exit;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'if (!extension_loaded(\'zip\')) {';
        $lines[] = '    http_response_code(500);';
        $lines[] = '    echo json_encode([\'status\' => \'error\', \'message\' => \'Extension ZIP non disponible sur le serveur.\']);';
        $lines[] = '    exit;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = 'if (is_dir($vendorTarget)) {';
        $lines[] = '    $it = new RecursiveIteratorIterator(';
        $lines[] = '        new RecursiveDirectoryIterator($vendorTarget, RecursiveDirectoryIterator::SKIP_DOTS),';
        $lines[] = '        RecursiveIteratorIterator::CHILD_FIRST';
        $lines[] = '    );';
        $lines[] = '    foreach ($it as $file) {';
        $lines[] = '        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());';
        $lines[] = '    }';
        $lines[] = '    rmdir($vendorTarget);';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '$zip = new ZipArchive();';
        $lines[] = 'if ($zip->open($vendorZip) !== true) {';
        $lines[] = '    http_response_code(500);';
        $lines[] = '    echo json_encode([\'status\' => \'error\', \'message\' => "Impossible d\'ouvrir vendor.zip"]);';
        $lines[] = '    exit;';
        $lines[] = '}';
        $lines[] = '';
        $lines[] = '$zip->extractTo($frameworkDir);';
        $lines[] = '$zip->close();';
        $lines[] = 'unlink($vendorZip);';
        $lines[] = 'unlink(__FILE__);';
        $lines[] = '';
        $lines[] = 'echo json_encode([\'status\' => \'success\', \'message\' => \'vendor/ deploye avec succes.\', \'path\' => $frameworkDir]);';

        return implode("\n", $lines) . "\n";
    }

    private function ftp_mkdir_rec(mixed $conn, string $path): void
    {
        $parts = explode('/', trim($path, '/'));
        @ftp_chdir($conn, '/');

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if (!@ftp_chdir($conn, $part)) {
                @ftp_mkdir($conn, $part);
                @ftp_chdir($conn, $part);
            }
        }

        @ftp_chdir($conn, '/');
    }

    private function httpGet(string $url): string|false
    {
        $ctx = stream_context_create([
            'http' => [
                'timeout'         => 120,
                'follow_location' => 1,
                'ignore_errors'   => true,
            ],
            'ssl'  => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        return @file_get_contents($url, false, $ctx);
    }

    private function getTempDir(string $project): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'neo_deploy_' . strtolower($project);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
    }

    private function validateConfig(array $config): bool
    {
        $required = [
            'ftp.host'             => $config['ftp']['host']             ?? null,
            'ftp.user'             => $config['ftp']['user']             ?? null,
            'ftp.pass'             => $config['ftp']['pass']             ?? null,
            'remote.domain'        => $config['remote']['domain']        ?? null,
            'remote.framework_dir' => $config['remote']['framework_dir'] ?? null,
            'remote.public_dir'    => $config['remote']['public_dir']    ?? null,
        ];

        foreach ($required as $key => $value) {
            if (empty($value)) {
                echo "Erreur : cle de config manquante '$key'\n";
                return false;
            }
        }

        return true;
    }

    private function getOption(array $args, string $option): ?string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, "$option=")) {
                return explode('=', $arg, 2)[1];
            }
        }
        return null;
    }
}