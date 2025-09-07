<?php

$finder = PhpCsFixer\Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__,
    ])
    ->exclude([
        'vendor',
        'node_modules',
    ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12' => true,
        'array_syntax' => ['syntax' => 'short'],
        'single_quote' => true,
        'no_unused_imports' => true,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_trailing_whitespace' => true,
        'no_extra_blank_lines' => true,
        'blank_line_after_namespace' => true,
        'blank_line_after_opening_tag' => true,
        'declare_strict_types' => true,
        'phpdoc_trim' => true,
    ])
    ->setFinder($finder);
