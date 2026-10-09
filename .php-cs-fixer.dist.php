<?php

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();

$config->getFinder()
    ->in(__DIR__ . '/packages/')
    ->exclude('Tests/Fixtures')
    ->exclude('var')
    ->exclude('public');

// Merge TYPO3 defaults with overrides
$config->setRules(array_merge(
    $config->getRules(),
    [
        // This prevents CS-Fixer from collapsing empty constructors/methods/classes (needed for CodeSniffer)
        'single_line_empty_body' => false,
    ]
));

return $config;
