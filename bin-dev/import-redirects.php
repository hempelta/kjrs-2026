<?php

/**
 * Import redirects from the old kjrs.de into sys_redirect.
 *
 * Usage (inside DDEV):
 *   ddev exec php bin-dev/import-redirects.php                 # rows with status "sicher"
 *   ddev exec php bin-dev/import-redirects.php sicher Vorschlag
 *
 * Source: docs/weiterleitungen/weiterleitungsliste.csv (alt;neu;neue_uid;status;...).
 * Targets are page links (t3://page?uid=…), so later slug changes do not break them.
 * Existing redirects with the same source path are skipped — the script can run again
 * after the customer has confirmed further rows.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

$projectRoot = dirname(__DIR__);
$classLoader = require $projectRoot . '/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
$container = Bootstrap::init($classLoader);
Bootstrap::initializeBackendUser(CommandLineUserAuthentication::class);
$GLOBALS['BE_USER']->authenticate();
$GLOBALS['LANG'] = $container->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);

$statuses = array_slice($_SERVER['argv'] ?? [], 1) ?: ['sicher'];
$csv = new SplFileObject($projectRoot . '/docs/weiterleitungen/weiterleitungsliste.csv');
$csv->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
$csv->setCsvControl(';', '"', '');

$connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_redirect');
$data = [];
$skipped = 0;
foreach ($csv as $index => $row) {
    if ($index === 0 || !is_array($row) || !in_array($row[3] ?? '', $statuses, true) || ($row[2] ?? '') === '') {
        continue;
    }
    [$source, , $uid, $status, $title] = $row;
    if ($connection->count('uid', 'sys_redirect', ['source_path' => $source, 'deleted' => 0]) > 0) {
        $skipped++;
        continue;
    }
    $data['sys_redirect']['NEW' . $index] = [
        'pid' => 0,
        'source_host' => '*',
        'source_path' => $source,
        'target' => 't3://page?uid=' . (int)$uid,
        'target_statuscode' => 301,
        'description' => 'Relaunch 2026: alte Seite "' . $title . '" (' . $status . ')',
    ];
}

if ($data === []) {
    echo "Nothing to import ($skipped already present).\n";
    exit(0);
}

$dataHandler = GeneralUtility::makeInstance(DataHandler::class);
$dataHandler->start($data, []);
$dataHandler->process_datamap();
if ($dataHandler->errorLog !== []) {
    fwrite(STDERR, implode("\n", $dataHandler->errorLog) . "\n");
    exit(1);
}
printf("Imported %d redirects (%d already present).\n", count($data['sys_redirect']), $skipped);
