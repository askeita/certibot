<?php

namespace App\Technology\Php;

use App\Core\DocSource\DocSourceInterface;

/**
 * DocSource implementation for the PHP manual (https://www.php.net/manual/en/).
 *
 * Topics are curated sections of the PHP manual covering the core language.
 * php.net serves static HTML, so no browser automation is required — a standard
 * HTTP client is used instead, which is faster and more reliable.
 *
 * PHP has no official certification, so no version selector is needed.
 */
class PhpManualDocSource implements DocSourceInterface
{
    /**
     * Curated list of PHP manual sections used as quiz topics.
     * Format: 'manual-slug' => 'Human-readable title'
     *
     * These slugs map directly to https://www.php.net/manual/en/{slug}.php
     */
    public const array TOPICS = [
        'language.types'              => 'Types',
        'language.variables'          => 'Variables',
        'language.constants'          => 'Constants',
        'language.expressions'        => 'Expressions',
        'language.operators'          => 'Operators',
        'language.control-structures' => 'Control Structures',
        'language.functions'          => 'Functions',
        'language.oop5'               => 'Object-Oriented Programming',
        'language.namespaces'         => 'Namespaces',
        'language.exceptions'         => 'Exceptions',
        'language.errors'             => 'Errors',
        'language.fibers'             => 'Fibers',
        'language.generators'         => 'Generators',
        'security'                    => 'Security',
        'book.array'                  => 'Array Functions',
        'ref.strings'                 => 'String Functions',
        'book.datetime'               => 'Date/Time Functions',
        'book.json'                   => 'JSON Functions',
        'book.pcre'                   => 'Regular Expressions',
        'book.pdo'                    => 'Database (PDO)',
        'book.session'                => 'Sessions',
        'book.filter'                 => 'Data Filtering',
        'book.math'                   => 'Math Functions',
    ];

    public function getSlug(): string
    {
        return 'php';
    }

    public function getLabel(): string
    {
        return 'PHP';
    }

    public function getDatabaseName(): string
    {
        return 'php_manual';
    }

    public function supportsVersion(): bool
    {
        return false;
    }

    public function validateIdentifier(mixed $identifier): bool
    {
        // PHP quiz has no version identifier
        return true;
    }

    public function getTopicsCollectionName(mixed $identifier = null): string
    {
        return 'php_topics';
    }

    public function getLinksCollectionName(mixed $identifier = null): string
    {
        return 'php_topics_links';
    }

    public function getMcqCollectionName(mixed $identifier = null): string
    {
        return 'php_mcq_gpt-4o';
    }

    public function getDocumentLabel(mixed $identifier = null): string
    {
        return 'PHP';
    }

    public function requiresBrowserForContent(): bool
    {
        // php.net serves static HTML — no JavaScript rendering required
        return false;
    }
}

