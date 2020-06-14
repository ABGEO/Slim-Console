<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim-Console/blob/0.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace Slim\Console\Command\Initializer\Profiles\blank;

use Slim\Console\Command\Initializer\Dependency\Dependency;
use Slim\Console\Command\Initializer\Dependency\GuzzleDependency;
use Slim\Console\Command\Initializer\Dependency\LaminasDependency;
use Slim\Console\Command\Initializer\Dependency\MonologDependency;
use Slim\Console\Command\Initializer\Dependency\NyholmDependency;
use Slim\Console\Command\Initializer\Dependency\OtherDependency;
use Slim\Console\Command\Initializer\Dependency\PHPDIDependency;
use Slim\Console\Command\Initializer\Dependency\PimpleDependency;
use Slim\Console\Command\Initializer\Dependency\SlimPsr7Dependency;
use Slim\Console\Command\Initializer\Profiles\AbstractInitProfile;
use Slim\Console\Command\Initializer\Util\FileBuilder;
use Slim\Console\Config\Config;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_keys;
use function copy;
use function file_get_contents;
use function file_put_contents;
use function get_class;
use function getcwd;
use function is_dir;
use function is_file;
use function mkdir;
use function str_replace;
use function touch;

use const DIRECTORY_SEPARATOR;

/**
 * Init class implementation for profile Blank.
 *
 * @package Slim\Console\Command\Initializer\Profiles\blank
 * @author Temuri Takalandze <me@abgeo.dev>
 */
class Init extends AbstractInitProfile
{
    /**
     * @var bool
     */
    protected $useDefaultSetup;

    /**
     * @var string
     */
    protected $templatesDirectory;

    /**
     * {@inheritDoc}
     */
    public function __construct(InputInterface $input, OutputInterface $output, ?Config $config = null)
    {
        parent::__construct($input, $output, $config);

        $this->templatesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
    }

    /**
     * {@inheritDoc}
     */
    public function run(string $projectDirectory, bool $useDefaultSetup = false): int
    {
        $this->useDefaultSetup = $useDefaultSetup;
        $exitCode = null;

        if (0 !== ($exitCode = parent::run($projectDirectory, $useDefaultSetup))) {
            return $exitCode;
        }

        if (0 !== ($exitCode = $this->createStructure($projectDirectory))) {
            return $exitCode;
        }

        if (0 === ($exitCode = $this->setupDependencies($projectDirectory))) {
            $this->io->success('New Slim project successfully created. Please run `composer install`.');
        }

        return $exitCode;
    }

    /**
     * Create basic directory and files structure.
     *
     * @param string $projectDirectory
     *
     * @return int The Exit Code.
     */
    protected function createStructure(string $projectDirectory): int
    {
        $phpunitTemplate = null;
        $directoryFullPath = getcwd() . DIRECTORY_SEPARATOR . $projectDirectory;
        $composerJsonContent = $this->readComposerJson($directoryFullPath);
        $directoriesToCreate = [
            'bootstrap' => $this->config ? $this->config->getBootstrapDir() : 'app',
            'index'     => $this->config ? $this->config->getIndexDir() : 'public',
            'source'    => $this->config ? $this->config->getSourceDir() : 'src',
            'logs'      => 'logs',
            'tests'     => 'tests',
        ];
        $filesToCreate = [
            $directoriesToCreate['bootstrap'] . DIRECTORY_SEPARATOR . 'dependencies.php',
            $directoriesToCreate['bootstrap'] . DIRECTORY_SEPARATOR . 'routes.php',
            $directoriesToCreate['bootstrap'] . DIRECTORY_SEPARATOR . 'settings.php',

            $directoriesToCreate['index'] . DIRECTORY_SEPARATOR .
                ($this->config ? $this->config->getIndexFile() : 'index.php'),
        ];

        foreach ($directoriesToCreate as $directory) {
            if (!is_dir($directoryFullPath . DIRECTORY_SEPARATOR . $directory)) {
                if (!mkdir($directoryFullPath . DIRECTORY_SEPARATOR . $directory, 0755, true)) {
                    return -1;
                }
            }
        }

        foreach ($filesToCreate as $file) {
            if (!is_file($directoryFullPath . DIRECTORY_SEPARATOR . $file)) {
                if (!touch($directoryFullPath . DIRECTORY_SEPARATOR . $file)) {
                    return -1;
                }
            }
        }

        if ($this->useDefaultSetup ? true : $this->io->confirm('Do you want to create docker-compose.yml?', true)) {
            if (
                !copy(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'docker-compose.yml.template',
                    $directoryFullPath . DIRECTORY_SEPARATOR . 'docker-compose.yml'
                )
            ) {
                return -1;
            }
        }

        if ($this->useDefaultSetup ? true : $this->io->confirm('Do you want to create docker-compose.yml?', true)) {
            if (
                !copy(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . '.gitignore.template',
                    $directoryFullPath . DIRECTORY_SEPARATOR . '.gitignore'
                )
            ) {
                return -1;
            }
        }
        if ($this->useDefaultSetup ? true : $this->io->confirm('Do you want to create docker-compose.yml?', true)) {
            if (
                !copy(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR .
                        '.htaccess.template',
                    $directoryFullPath . DIRECTORY_SEPARATOR . $directoriesToCreate['index'] .
                        DIRECTORY_SEPARATOR . '.htaccess'
                )
            ) {
                return -1;
            }
        }

        // Setup PHPUnit.

        if ($this->useDefaultSetup ? true : $this->io->confirm('Do you want to create docker-compose.yml?', true)) {
            if (
                !copy(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR .
                        'bootstrap.php.template',
                    $directoryFullPath . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'bootstrap.php'
                )
            ) {
                return -1;
            }
        }

        $phpunitTemplate = file_get_contents($this->templatesDirectory . DIRECTORY_SEPARATOR . 'phpunit.xml.template');
        $phpunitTemplate = str_replace(
            ['{testsDirectory}', '{sourceDirectory}'],
            [
                '.' . DIRECTORY_SEPARATOR . ($this->config ? $this->config->getSourceDir() : 'src')
                . DIRECTORY_SEPARATOR,
                '.' . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR,
            ],
            $phpunitTemplate ? $phpunitTemplate : ''
        );

        if (false === file_put_contents($directoryFullPath . DIRECTORY_SEPARATOR . 'phpunit.xml', $phpunitTemplate)) {
            return -1;
        }

        $composerJsonContent['require-dev']['phpunit/phpunit'] = Versions::PHP_UNIT;
        $composerJsonContent['scripts']['test'] = 'phpunit';
        $composerJsonContent['autoload-dev'] = [
            'psr-4' => [
                'Tests\\' => 'tests',
            ],
        ];

        // End of Setup PHPUnit.

        return $this->writeToComposerJson($directoryFullPath, $composerJsonContent);
    }

    /**
     * Setup project dependencies.
     *
     * @param string $projectDirectory
     *
     * @return int The Exit Code.
     */
    protected function setupDependencies(string $projectDirectory): int
    {
        $exitCode = null;
        $directoryFullPath = getcwd() . DIRECTORY_SEPARATOR . $projectDirectory;
        $composerJsonContent = $this->readComposerJson($directoryFullPath);
        $bootstrapDirectory = $this->config ? $this->config->getBootstrapDir() : 'app';
        $indexDirectory = $this->config ? $this->config->getIndexDir() : 'public';
        $sourceDirPrefix = $this->templatesDirectory . DIRECTORY_SEPARATOR;
        $destinationDirPrefix = $directoryFullPath . DIRECTORY_SEPARATOR;
        $returnFunctionSkeletonFile = $sourceDirPrefix . 'app' . DIRECTORY_SEPARATOR . 'return_function.php.template';
        $dependencies = $this->askDependencies();
        ['psr7' => $psr7, 'dependencyContainer' => $dependencyContainer, 'logger' => $logger] = $dependencies;

        foreach ($dependencies as $dependency) {
            foreach ($dependency->getPackages() as $package => $version) {
                $composerJsonContent['require'][$package] = $version;
            }
        }

        if (
            0 !== ($exitCode = $this->buildRoutesFile(
                $returnFunctionSkeletonFile,
                $destinationDirPrefix . $bootstrapDirectory . DIRECTORY_SEPARATOR . 'routes.php',
                $psr7
            ))
        ) {
            return $exitCode;
        }

        if (
            0 !== ($exitCode = $this->buildSettingsFile(
                $returnFunctionSkeletonFile,
                $destinationDirPrefix . $bootstrapDirectory . DIRECTORY_SEPARATOR . 'settings.php',
                $dependencyContainer,
                ['projectDirectory' => $projectDirectory]
            ))
        ) {
            return $exitCode;
        }

        if (
            0 !== ($exitCode = $this->buildDependenciesFile(
                $returnFunctionSkeletonFile,
                $destinationDirPrefix . $bootstrapDirectory . DIRECTORY_SEPARATOR . 'dependencies.php',
                $dependencyContainer
            ))
        ) {
            return $exitCode;
        }

        if (
            0 !== ($exitCode = $this->buildIndexFile(
                $sourceDirPrefix . 'public' . DIRECTORY_SEPARATOR . 'index.php.template',
                $destinationDirPrefix . $indexDirectory . DIRECTORY_SEPARATOR . 'index.php',
                $dependencyContainer
            ))
        ) {
            return $exitCode;
        }

        return $this->writeToComposerJson($directoryFullPath, $composerJsonContent);
    }

    /**
     * Build a routes file from the template.
     *
     * @param string     $templatePath    Template file path.
     * @param string     $destinationFile Destination file to write to.
     * @param Dependency $psr7            PSR-7 Implementation Dependency.
     *
     * @return int The Exit Code.
     */
    protected function buildRoutesFile(string $templatePath, string $destinationFile, Dependency $psr7): int
    {
        $PSR7ImportsReplace = null;
        $bodyReplace = file_get_contents(
            $this->templatesDirectory . DIRECTORY_SEPARATOR . 'parts' . DIRECTORY_SEPARATOR . 'routes_body.template'
        );

        switch (get_class($psr7)) {
            case SlimPsr7Dependency::class:
                $PSR7ImportsReplace = "\nuse Psr\Http\Message\ResponseInterface as Response;\n" .
                    "use Psr\Http\Message\ServerRequestInterface as Request;\nuse Slim\App;\n";
                break;
            case LaminasDependency::class:
                $PSR7ImportsReplace = "\nuse Laminas\Diactoros\ServerRequest as Request;\n" .
                    "use Laminas\Diactoros\Response;\nuse Slim\App;\n";
                break;
            case GuzzleDependency::class:
                $PSR7ImportsReplace = "\nuse GuzzleHttp\Psr7\Request;\nuse GuzzleHttp\Psr7\Response;\nuse Slim\App;\n";
                break;
            case NyholmDependency::class:
                $PSR7ImportsReplace = "\nuse Nyholm\Psr7\Response;\nuse Nyholm\Psr7\ServerRequest as Request;\n" .
                    "use Slim\App;\n";
                break;
        }

        return (new FileBuilder($templatePath))
            ->setReplaceToken('{argument}', 'App $app')
            ->setReplaceToken('{body}', (string)$bodyReplace)
            ->setReplaceToken('{imports}', $PSR7ImportsReplace)
            ->buildFile($destinationFile);
    }

    /**
     * Build a settings file from the template.
     *
     * @param string       $templatePath        Template file path.
     * @param string       $destinationFile     Destination file to write to.
     * @param Dependency   $dependencyContainer Dependency Container Dependency.
     * @param array<mixed> $additional          Additional parameters.
     *
     * @return int The Exit Code.
     */
    protected function buildSettingsFile(
        string $templatePath,
        string $destinationFile,
        Dependency $dependencyContainer,
        array $additional = []
    ): int {
        $importsReplace = null;
        $argumentReplace = null;
        $bodyReplace = null;
        ['projectDirectory' => $projectDirectory] = $additional;

        switch (get_class($dependencyContainer)) {
            case PHPDIDependency::class:
                $importsReplace = "\nuse DI\ContainerBuilder;\nuse Monolog\Logger;\n";
                $argumentReplace = 'ContainerBuilder $containerBuilder';
                $bodyReplace = file_get_contents(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'parts' .
                    DIRECTORY_SEPARATOR . 'settings_body_php_di.template'
                );
                break;
            case PimpleDependency::class:
                $importsReplace = "\nuse Monolog\Logger;\nuse Pimple\Container;\n";
                $argumentReplace = 'Container $container';
                $bodyReplace = file_get_contents(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'parts' .
                    DIRECTORY_SEPARATOR . 'settings_body_pimple.template'
                );
                break;
            case OtherDependency::class:
                $importsReplace = '';
                $argumentReplace = '$container';
                $bodyReplace = "\n";
                break;
        }

        return (new FileBuilder($templatePath))
            ->setReplaceToken('{imports}', $importsReplace)
            ->setReplaceToken('{argument}', $argumentReplace)
            ->setReplaceToken('{body}', (string)$bodyReplace)
            ->setReplaceToken('{appName}', $projectDirectory)
            ->buildFile($destinationFile);
    }

    /**
     * Build a dependencies file from the template.
     *
     * @param string     $templatePath        Template file path.
     * @param string     $destinationFile     Destination file to write to.
     * @param Dependency $dependencyContainer Dependency Container Dependency.
     *
     * @return int The Exit Code.
     */
    protected function buildDependenciesFile(
        string $templatePath,
        string $destinationFile,
        Dependency $dependencyContainer
    ): int {
        $importsReplace = null;
        $argumentReplace = null;
        $bodyReplace = null;

        switch (get_class($dependencyContainer)) {
            case PHPDIDependency::class:
                $importsReplace = "\nuse DI\ContainerBuilder;\nuse Monolog\Handler\StreamHandler;\n" .
                    "use Monolog\Logger;\nuse Monolog\Processor\UidProcessor;\n" .
                    "use Psr\Container\ContainerInterface;\nuse Psr\Log\LoggerInterface;\n";
                $argumentReplace = 'ContainerBuilder $containerBuilder';
                $bodyReplace = file_get_contents(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'parts' .
                    DIRECTORY_SEPARATOR . 'dependencies_body_php_di.template'
                );
                break;
            case PimpleDependency::class:
                $importsReplace = "\nuse Monolog\Handler\StreamHandler;\nuse Monolog\Logger;" .
                    "\nuse Monolog\Processor\UidProcessor;\nuse Pimple\Container;\nuse Psr\Log\LoggerInterface;\n";
                $argumentReplace = 'Container $container';
                $bodyReplace = file_get_contents(
                    $this->templatesDirectory . DIRECTORY_SEPARATOR . 'parts' .
                    DIRECTORY_SEPARATOR . 'dependencies_body_pimple.template'
                );
                break;
            case OtherDependency::class:
                $importsReplace = '';
                $argumentReplace = '$container';
                $bodyReplace = "\n";
                break;
        }

        return (new FileBuilder($templatePath))
            ->setReplaceToken('{imports}', $importsReplace)
            ->setReplaceToken('{argument}', $argumentReplace)
            ->setReplaceToken('{body}', (string)$bodyReplace)
            ->buildFile($destinationFile);
    }

    /**
     * Build a index file from the template.
     *
     * @param string     $templatePath        Template file path.
     * @param string     $destinationFile     Destination file to write to.
     * @param Dependency $dependencyContainer Dependency Container Dependency.
     *
     * @return int The Exit Code.
     */
    protected function buildIndexFile(
        string $templatePath,
        string $destinationFile,
        Dependency $dependencyContainer
    ): int {
        $containerVariableReplace = null;
        $importsReplace = null;
        $defineContainerReplace = null;
        $setContainerReplace = null;

        switch (get_class($dependencyContainer)) {
            case PHPDIDependency::class:
                $containerVariableReplace = '$containerBuilder';
                $importsReplace = "use DI\ContainerBuilder;\nuse Slim\Factory\AppFactory;";
                $defineContainerReplace = "// Instantiate PHP-DI ContainerBuilder\n" .
                    "\$containerBuilder = new ContainerBuilder();\n\n" .
                    "if (false) { // Should be set to true in production\n" .
                    "    \$containerBuilder->enableCompilation(__DIR__ . '/../var/cache');\n}";
                $setContainerReplace = "// Build PHP-DI Container instance\n" .
                    "\$container = \$containerBuilder->build();\n\n// Instantiate the app\n" .
                    "AppFactory::setContainer(\$container);";
                break;
            case PimpleDependency::class:
                $containerVariableReplace = '$container';
                $importsReplace = "use Pimple\Container;\nuse Slim\Factory\AppFactory;";
                $defineContainerReplace = "// Instantiate Pimple Container\n\$container = new Container();";
                $setContainerReplace = "// Instantiate the app\n" .
                    "AppFactory::setContainer(new \Pimple\Psr11\Container(\$container));";
                break;
            case OtherDependency::class:
                $containerVariableReplace = '$container';
                $importsReplace = "use Slim\Factory\AppFactory;";
                $defineContainerReplace = "// TODO: Instantiate you'r Dependency Container\n\$container = null;";
                $setContainerReplace = "// Instantiate the app\n" .
                    "// TODO: Uncomment the line below if you created an instance of Dependency Container\n" .
                    "//AppFactory::setContainer(\$container);";
                break;
        }

        return (new FileBuilder($templatePath))
            ->setReplaceToken('{containerVariable}', $containerVariableReplace)
            ->setReplaceToken('{imports}', $importsReplace)
            ->setReplaceToken('{defineContainer}', $defineContainerReplace)
            ->setReplaceToken('{setContainer}', $setContainerReplace)
            ->buildFile($destinationFile);
    }

    /**
     * Collect dependencies from user input.
     *
     * @return array<Dependency>
     */
    protected function askDependencies(): array
    {
        $psr7 = null;
        $dependencyContainer = null;
        $dependencyContainerOtherPackage = null;
        $dependencyContainerOtherVersion = null;
        $logger = null;
        $availableDependencies = [
            'psr7' => [
                SlimPsr7Dependency::NAME => new SlimPsr7Dependency(),
                LaminasDependency::NAME  => new LaminasDependency(),
                GuzzleDependency::NAME   => new GuzzleDependency(),
                NyholmDependency::NAME   => new NyholmDependency(),
            ],
            'dependencyContainer' => [
                PHPDIDependency::NAME  => new PHPDIDependency(),
                PimpleDependency::NAME => new PimpleDependency(),
                OtherDependency::NAME  => new OtherDependency(),
            ],
            'logger' => [
                MonologDependency::NAME => new MonologDependency(),
            ],
        ];
        $dependencies = [
            'psr7' => $availableDependencies['psr7'][SlimPsr7Dependency::NAME],
            'dependencyContainer' => $availableDependencies['dependencyContainer'][PHPDIDependency::NAME],
            'logger' => $availableDependencies['logger'][MonologDependency::NAME],
        ];

        if (!$this->useDefaultSetup) {
            if ($this->io->confirm('Do you want to configure the PSR-7 HTTP message interface?')) {
                $psr7 = $this->io->choice(
                    'Select PSR-7 implementation',
                    array_keys($availableDependencies['psr7']),
                    SlimPsr7Dependency::NAME
                );

                $dependencies['psr7'] = $availableDependencies['psr7'][$psr7];
            }

            if ($this->io->confirm('Do you want to configure Dependency Container?')) {
                $dependencyContainer = $this->io->choice(
                    'Select Dependency Container',
                    array_keys($availableDependencies['dependencyContainer']),
                    PHPDIDependency::NAME
                );

                $dependencyContainer = $availableDependencies['dependencyContainer'][$dependencyContainer];
                if ($dependencyContainer instanceof OtherDependency) {
                    $dependencyContainerOtherPackage = $this->io->ask(
                        'Enter Dependency Container package (<vendor>/<package>)'
                    );
                    $dependencyContainerOtherVersion = $this->io->ask('Enter Dependency Container version', '*');

                    $dependencyContainer->addPackage(
                        $dependencyContainerOtherPackage,
                        $dependencyContainerOtherVersion
                    );
                }

                $dependencies['dependencyContainer'] = $dependencyContainer;
            }

            if ($this->io->confirm('Do you want to configure PSR-3 Logging?')) {
                $logger = $this->io->choice(
                    'Select PSR-3 Logger',
                    array_keys($availableDependencies['logger']),
                    MonologDependency::NAME
                );

                $dependencies['logger'] = $availableDependencies['logger'][$logger];
            }
        }

        return $dependencies;
    }
}
