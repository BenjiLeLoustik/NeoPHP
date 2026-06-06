<?php
declare(strict_types=1);

namespace Neo\Core\Console\Commands;

use Neo\Core\Console\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Helper\Args;
use Neo\Core\Console\Helper\Fs;
use Neo\Core\Console\Helper\Output;

#[Command(
    name: 'app:make:deployment',
    description: 'Deploy a NeoPHP project via FTP',
    category: 'Deployment'
)]
final class MakeDeploymentCommand extends AbstractCommand
{
    private float $startTime;

    private const UNZIP_SCRIPT_NAME = '__neo_unzip.php';

    private const EXCLUDED_CONFIG_FILES = [
        'deploy.config.php',
    ];

    public function execute(array $args): void
    {
        $this->startTime = microtime(true);

        $project = Args::option($args, '--project');

        if (!$project) {
            Output::error('Missing required option: --project');
            Output::muted('Usage: php bin/neo make:deployment --project=<name>');
            return;
        }

        $deployConfigPath = ROOT_DIR . "src/$project/Config/deploy.config.php";

        if (!file_exists($deployConfigPath)) {
            Output::error("deploy.config.php not found: $deployConfigPath");
            return;
        }

        $config = require $deployConfigPath;

        if (!$this->validateConfig($config)) {
            return;
        }

        $ftpHost = $config['ftp']['host'];
        $ftpUser = $config['ftp']['user'];
        $ftpPass = $config['ftp']['pass'];
        $domain = $config['remote']['domain'];
        $ftpRoot = '/' . trim($config['remote']['framework_dir'], '/');
        $ftpPublic = '/' . trim($config['remote']['public_dir'], '/');

        $tmpDir = $this->getTempDir($project);
        $this->prepareDir($tmpDir);

        Output::step('0/6', 'Patching app.config.php for production…');

        $appConfigPath = ROOT_DIR . "src/$project/Config/app.config.php";
        $tmpAppConfigPath = null;

        if (!file_exists($appConfigPath)) {
            Output::skip('app.config.php not found, patch skipped.');
        } else {
            $appConfig = require $appConfigPath;
            $appConfig['environment'] = 'prod';
            $appConfig['access'] = $domain;

            $tmpAppConfigPath = "$tmpDir/app.config.php";
            file_put_contents($tmpAppConfigPath, $this->generateConfigFile($appConfig));

            Output::label('  environment', 'prod');
            Output::label('  access', $domain);
        }

        $indexPath = ROOT_DIR . 'public/index.php';
        $tmpIndexPath = null;

        if (!file_exists($indexPath)) {
            Output::skip('public/index.php not found, patch skipped.');
        } else {
            $indexContent = file_get_contents($indexPath);
            $neoDir = basename(trim($ftpRoot, '/'));
            $patchedContent = str_replace(
                "require_once __DIR__ . '/../vendor/autoload.php'",
                "require_once __DIR__ . '/../{$neoDir}/vendor/autoload.php'",
                $indexContent
            );
            if ($patchedContent !== $indexContent) {
                $tmpIndexPath = "$tmpDir/index.php";
                file_put_contents($tmpIndexPath, $patchedContent);
                Output::label('  index.php', "$neoDir/vendor/autoload.php");
            }
        }

        Output::step('1/6', 'Merging composer.json…');

        $mergedComposer = $this->buildMergedComposer($project);
        file_put_contents(
            "$tmpDir/composer.json",
            json_encode($mergedComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Output::step('2/6', 'Installing dependencies (composer update --no-dev)…');

        $composerCmd = 'composer update --working-dir=' . escapeshellarg($tmpDir) . ' --no-dev --optimize-autoloader';
        passthru($composerCmd, $code);

        if ($code !== 0) {
            Output::newLine();
            Output::error('composer update failed. Deployment aborted.');
            return;
        }

        Output::step('3/6', 'Compressing vendor/…');

        $vendorDir = "$tmpDir/vendor";
        $vendorZip = "$tmpDir/vendor.zip";

        if (!$this->zipDirectory($vendorDir, $vendorZip)) {
            Output::error('vendor/ compression failed.');
            return;
        }

        $sizeMb = round(filesize($vendorZip) / 1024 / 1024, 2);
        Output::muted("  vendor.zip: {$sizeMb} MB");

        Output::step('4/6', "Connecting to FTP {$ftpHost}...");

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);

        if (!$conn) {
            Output::error('FTP connection failed.');
            return;
        }

        Output::step('5/6', 'Uploading files…');

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

        $total = count($filesToUpload);
        $uploaded = 0;
        $errors = 0;

        foreach ($filesToUpload as [$localPath, $remotePath, $remoteDir]) {
            $this->ftpMkdirRecursive($conn, $remoteDir);
            $ok = @ftp_put($conn, $remotePath, $localPath, FTP_BINARY);
            $ok ? $uploaded++ : $errors++;
            Output::progress($uploaded + $errors, $total);
        }

        if ($errors > 0) {
            Output::warning("$uploaded file(s) uploaded, $errors error(s).");
        } else {
            Output::success("$uploaded file(s) uploaded.");
        }

        ftp_close($conn);

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);
        if (!$conn) {
            Output::error('FTP reconnect failed (composer files).');
            return;
        }

        ftp_put($conn, "$ftpRoot/composer.json", "$tmpDir/composer.json", FTP_BINARY);

        if (file_exists("$tmpDir/composer.lock")) {
            ftp_put($conn, "$ftpRoot/composer.lock", "$tmpDir/composer.lock", FTP_BINARY);
        }

        ftp_close($conn);

        Output::info("Uploading vendor.zip ({$sizeMb} MB)...");

        $remoteZip = "$ftpRoot/vendor.zip";
        $ok = $this->ftpPutWithCurl($remoteZip, $vendorZip, $ftpHost, $ftpUser, $ftpPass);

        if (!$ok) {
            Output::error('vendor.zip upload failed after multiple retries. Deployment aborted.');
            return;
        }

        Output::success('vendor.zip uploaded.');

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);
        if (!$conn) {
            Output::error('FTP reconnect failed (unzip script).');
            return;
        }

        $unzipScript = $this->generateUnzipScript($ftpRoot, $ftpPublic);
        $tmpScript = "$tmpDir/" . self::UNZIP_SCRIPT_NAME;
        $remoteScript = rtrim($ftpPublic, '/') . '/' . self::UNZIP_SCRIPT_NAME;

        file_put_contents($tmpScript, $unzipScript);
        ftp_put($conn, $remoteScript, $tmpScript, FTP_BINARY);
        ftp_close($conn);

        Output::step('6/6', 'Server-side unzip…');

        $scriptUrl = "https://$domain/" . self::UNZIP_SCRIPT_NAME;
        $response  = $this->httpGet($scriptUrl);

        if ($response === false) {
            $response = $this->httpGet("http://$domain/" . self::UNZIP_SCRIPT_NAME);
        }

        if ($response !== false) {
            $decoded = json_decode(trim($response), true);
            $status = $decoded['status']  ?? 'unknown';
            $message = $decoded['message'] ?? trim($response);

            if ($status === 'success') {
                Output::success("Server: $message");
            } else {
                Output::warning("Server: $message");
            }
        } else {
            Output::warning('Could not reach the unzip script.');
            Output::muted("Call manually: $scriptUrl");
        }

        $conn = $this->ftpConnect($ftpHost, $ftpUser, $ftpPass);
        if ($conn) {
            @ftp_delete($conn, $remoteScript);
            @ftp_delete($conn, "$ftpRoot/vendor.zip");
            ftp_close($conn);
        }

        Fs::deleteDir($tmpDir);

        $elapsed = round(microtime(true) - $this->startTime, 2);
        Output::newLine();
        Output::success("Deployment to $domain completed in {$elapsed}s.");
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
            Output::warning('cURL not available, falling back to ftp_put.');
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
                Output::warning("Retry $i/$maxRetries in 3s…");
                sleep(3);
            }

            $fp = fopen($local, 'rb');

            if (!$fp) {
                Output::error("Cannot open local file: $local");
                return false;
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "ftp://$host$remote",
                CURLOPT_USERPWD => "$user:$pass",
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $fp,
                CURLOPT_INFILESIZE => filesize($local),
                CURLOPT_FTP_USE_EPSV => false,
                CURLOPT_FTP_USE_EPRT => false,
                CURLOPT_FTPSSLAUTH => CURLFTPAUTH_DEFAULT,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 30,
            ]);

            $ok = curl_exec($ch);
            $errno = curl_errno($ch);
            $errmsg = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($ok && $errno === 0) {
                return true;
            }

            Output::warning("cURL error $errno: $errmsg");
        }

        return false;
    }

    private function ftpMkdirRecursive(mixed $conn, string $path): void
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
                'timeout' => 120,
                'follow_location' => 1,
                'ignore_errors' => true,
            ],
            'ssl'  => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        return @file_get_contents($url, false, $ctx);
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

            $localPath = "$local/$file";
            $remotePath = "$remote/$file";

            if (is_dir($localPath)) {
                $this->collectFiles($localPath, $remotePath, $exclude, $list);
            } else {
                $list[] = [$localPath, $remotePath, $remote];
            }
        }
    }

    private function zipDirectory(string $sourceDir, string $outZip): bool
    {
        if (!extension_loaded('zip')) {
            Output::error('PHP zip extension not available.');
            return false;
        }

        $zip = new \ZipArchive();
        if ($zip->open($outZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $source = realpath($sourceDir);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
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

    private function buildMergedComposer(string $project): array
    {
        $rootComposerPath = ROOT_DIR . 'composer.json';
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

        $merged = [
            'name' => $root['name'] ?? 'neo/deployment',
            'description' => "Merged composer for deployment - $project",
            'type' => 'project',
            'license' => $root['license'] ?? 'MIT',
            'require' => array_merge($rootRequire, $projRequire),
            'minimum-stability' => $root['minimum-stability'] ?? 'stable',
            'prefer-stable' => $root['prefer-stable'] ?? true,
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

    private function generateUnzipScript(string $ftpRoot, string $ftpPublic): string
    {
        $root = '/' . trim($ftpRoot, '/');
        $public = '/' . trim($ftpPublic, '/');
        $relative = ltrim(substr($public, strlen($root)), '/');
        $levels = ($relative !== '') ? count(array_filter(explode('/', $relative))) : 0;
        $neo = basename($root);

        $dirnameExpr = $levels > 0
            ? "dirname(__DIR__, $levels) . '/$neo'"
            : "__DIR__ . '/$neo'";

        return implode("\n", [
                '<?php',
                '$frameworkDir = ' . $dirnameExpr . ';',
                '$vendorZip    = $frameworkDir . \'/vendor.zip\';',
                '$vendorTarget = $frameworkDir . \'/vendor\';',
                '',
                'if (!file_exists($vendorZip)) {',
                '    http_response_code(404);',
                '    echo json_encode([\'status\' => \'error\', \'message\' => \'vendor.zip not found: \' . $vendorZip]);',
                '    exit;',
                '}',
                '',
                'if (!extension_loaded(\'zip\')) {',
                '    http_response_code(500);',
                '    echo json_encode([\'status\' => \'error\', \'message\' => \'ZIP extension not available on server.\']);',
                '    exit;',
                '}',
                '',
                'if (is_dir($vendorTarget)) {',
                '    $it = new RecursiveIteratorIterator(',
                '        new RecursiveDirectoryIterator($vendorTarget, RecursiveDirectoryIterator::SKIP_DOTS),',
                '        RecursiveIteratorIterator::CHILD_FIRST',
                '    );',
                '    foreach ($it as $file) {',
                '        $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());',
                '    }',
                '    rmdir($vendorTarget);',
                '}',
                '',
                '$zip = new ZipArchive();',
                'if ($zip->open($vendorZip) !== true) {',
                '    http_response_code(500);',
                '    echo json_encode([\'status\' => \'error\', \'message\' => \'Cannot open vendor.zip\']);',
                '    exit;',
                '}',
                '',
                '$zip->extractTo($frameworkDir);',
                '$zip->close();',
                'unlink($vendorZip);',
                'unlink(__FILE__);',
                '',
                'echo json_encode([\'status\' => \'success\', \'message\' => \'vendor/ deployed successfully.\', \'path\' => $frameworkDir]);',
            ]) . "\n";
    }

    private function generateConfigFile(array $config): string
    {
        return "<?php\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
    }

    private function validateConfig(array $config): bool
    {
        $required = [
            'ftp.host' => $config['ftp']['host'] ?? null,
            'ftp.user' => $config['ftp']['user'] ?? null,
            'ftp.pass' => $config['ftp']['pass'] ?? null,
            'remote.domain' => $config['remote']['domain'] ?? null,
            'remote.framework_dir' => $config['remote']['framework_dir'] ?? null,
            'remote.public_dir' => $config['remote']['public_dir'] ?? null,
        ];

        foreach ($required as $key => $value) {
            if (empty($value)) {
                Output::error("Missing config key: '$key'");
                return false;
            }
        }

        return true;
    }

    private function getTempDir(string $project): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'neo_deploy_' . strtolower($project);
    }

    private function prepareDir(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            return;
        }

        Fs::emptyDir($dir);
    }

    public function getHelp(): string
    {
        Output::usage('make:deployment', $this->getDescription());
        Output::option('--project=<name>', 'Target project inside ./src/ (required)');
        Output::newLine();
        echo "  Examples:\n";
        Output::example('php bin/neo make:deployment --project=MonProjet');

        return '';
    }
}