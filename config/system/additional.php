<?php

declare(strict_types=1);

defined('TYPO3') or die();

use Dotenv\Exception\ValidationException;
use OliverThiele\OtMailcatcher\Service\MailcatcherState;
use TYPO3\CMS\Core\Core\Environment;

$context = Environment::getContext();

if (!$context->isDevelopment() && !$context->isTesting() && !$context->isProduction()) {
    $redColor = '';
    $greenColor = '';
    $resetColor = '';
    if (PHP_SAPI === 'cli') {
        $redColor = "\033[31m";
        $greenColor = "\033[32m";
        $resetColor = "\033[0m";
    }
    die(
        $redColor . 'Error: No application context set!' . $resetColor . PHP_EOL .
        'Example for Development Context:' . PHP_EOL .
        $greenColor . 'export TYPO3_CONTEXT=Development' . $resetColor . PHP_EOL
    );
}

/**
 * @param $envFile
 * @return void
 */
function loadEnvFile($envFile): void
{
    if (isset($_ENV['TYPO3_PATH_APP'])) {
        $typo3WebDirectory = $_ENV['TYPO3_PATH_APP'] . '/';
    } else {
        $typo3WebDirectory = dirname(__DIR__, 2) . '/';
    }

    if (is_file($typo3WebDirectory . $envFile)) {
        $dotenv = Dotenv\Dotenv::createImmutable($typo3WebDirectory, $envFile);
        $dotenv->load();

        try {
            $dotenv->required([
                'SMTP_SERVER',
                'SMTP_USER',
                'SMTP_PASSWORD',
            ]);

            $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_server'] = $_ENV['SMTP_SERVER'];
            $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_username'] = $_ENV['SMTP_USER'];
            $GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_smtp_password'] = $_ENV['SMTP_PASSWORD'];
        } catch (ValidationException $e) {
            die('These environment variables are missing: ' . $e->getMessage());
        }

        try {
            $dotenv->required([
                'TYPO3_INSTALL_TOOL',
                'DB_HOST',
                'DB_NAME',
                'DB_USER',
                'DB_PASS',
                'ENCRYPTION_KEY'
            ])->notEmpty();

            $GLOBALS['TYPO3_CONF_VARS']['BE']['installToolPassword'] = $_ENV['TYPO3_INSTALL_TOOL'];

            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = $_ENV['DB_NAME'];
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = $_ENV['DB_USER'];
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = $_ENV['DB_PASS'];
            $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = $_ENV['DB_HOST'];

            $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $_ENV['ENCRYPTION_KEY'];
        } catch (ValidationException $e) {
            die('These environment variables must not be empty: ' . $e->getMessage());
        }
    }
}

/**
 * ## Default for all systems
 */
$GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] = '2775';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'] = '0664';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['systemLocale'] = 'en_US.UTF-8';

/**
 * ## DDEV Instance
 * TYPO3_CONTEXT Development/Local
 */
function setSitename(string $prefix)
{
    if (isset($_ENV['PROJECT_NAME']) && $_ENV['PROJECT_NAME'] !== '') {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = '[' . $prefix . '] ' . $_ENV['PROJECT_NAME'];
    } else {
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = '[' . $prefix . ']';
    }
}

if ($context->isDevelopment() && $context->__toString() === 'Development/Local') {
    loadEnvFile('.env.ddev');
    setDevelopmentDefaultSettings();
    setSitename('DDEV');
}

/**
 * ## Development Instance
 * TYPO3_CONTEXT Development
 */
if ($context->isDevelopment() && $context->__toString() === 'Development') {
    loadEnvFile('.env.development');
    setDevelopmentDefaultSettings();
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = '[Dev] ' . $_ENV['PROJECT_NAME'];
}

/**
 * ## Production Instance
 * TYPO3_CONTEXT Production
 */
if ($context->isProduction() && $context->__toString() === 'Production') {
    loadEnvFile('.env');
    setProductionDefaultSettings();
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = '[Production] ' . $_ENV['PROJECT_NAME'];
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration']['notice']['TYPO3\CMS\Core\Log\Writer\FileWriter'] =
        ['disabled' => true];
}

/**
 * ## Staging Instance
 * TYPO3_CONTEXT Production/Staging
 */
if ($context->isProduction() && $context->__toString() === 'Production/Staging') {
    loadEnvFile('.env.staging');
    setProductionDefaultSettings();
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] = '[Staging] ' . $_ENV['PROJECT_NAME'];
    $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration']['notice']['TYPO3\CMS\Core\Log\Writer\FileWriter'] =
        ['disabled' => true];
}


/**
 * ## Development default environment
 * TYPO3_CONTEXT Development
 */
function setDevelopmentDefaultSettings(): void
{
    $GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = '1';

    $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = '1';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = '1';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*.*';
    // Default:
    // $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = '12290';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = '4096';
}

/**
 * ## Production settings
 *
 * TYPO3_CONTEXT Production
 *
 * This function sets the production settings for TYPO3.
 *
 * The settings are loaded from the .env file.
 *
 * .env file is used per default for production systems, no TYPO3_CONTEXT is required
 */
function setProductionDefaultSettings(): void
{
    $GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = '';

    $GLOBALS['TYPO3_CONF_VARS']['FE']['debug'] = '';

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = '0';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = '4096';
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = 'SERVER_NAME';

    $GLOBALS['TYPO3_CONF_VARS']['LOG']['TYPO3']['CMS']['deprecations']['writerConfiguration']['notice']['TYPO3\CMS\Core\Log\Writer\FileWriter'] =
        ['disabled' => true];
}

/**
 * Optimized DDEV Configuration
 */
if (
    (bool)getenv('IS_DDEV_PROJECT') === true ||
    ($context->isDevelopment() && $context->__toString() === 'Development/Local')
) {
    $GLOBALS['TYPO3_CONF_VARS'] = array_replace_recursive(
        $GLOBALS['TYPO3_CONF_VARS'],
        [
            'BE' => [
                'versionNumberInFilename' => false,
            ],
            'FE' => [
                'versionNumberInFilename' => false,
                'loginRateLimit' => '0'
            ],
            'DB' => [
                'Connections' => [
                    'Default' => [
                        'dbname' => 'db',
                        'driver' => 'mysqli',
                        'host' => 'db',
                        'password' => 'db',
                        'port' => '3306',
                        'user' => 'db',
                        'charset' => 'utf8mb4',
                        'defaultTableOptions' => [
                            'charset' => 'utf8mb4',
                            'collation' => 'utf8mb4_unicode_ci',
                        ],
                    ],
                ],
            ],
            // This GFX configuration allows processing by installed ImageMagick 6
            'GFX' => [
                'processor' => 'ImageMagick',
                'processor_path' => '/usr/bin/',
                'processor_path_lzw' => '/usr/bin/',
            ],
            // This mail configuration sends all emails to mailpit
            'MAIL' => [
                'transport' => 'smtp',
                'transport_smtp_encrypt' => false,
                'transport_smtp_server' => 'localhost:1025',
            ],
            'SYS' => [
                'trustedHostsPattern' => '.*.*',
                'devIPmask' => '*',
                'displayErrors' => 1,
            ],
        ]
    );
}

/**
 * Mail catcher transport switch — must stay at the end of this file,
 * after every block that rewrites the MAIL array (see EXT:ot_mailcatcher README)
 */
if (class_exists(MailcatcherState::class)) {
    MailcatcherState::wireMailTransport();
}
