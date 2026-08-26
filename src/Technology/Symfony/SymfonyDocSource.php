<?php

namespace App\Technology\Symfony;

use App\Core\DocSource\DocSourceInterface;

/**
 * DocSource implementation for the Symfony certification.
 *
 * Topics are scraped from https://certification.symfony.com and documentation
 * links from https://symfony.com/doc. The certification website uses JavaScript,
 * so content fetching requires a real browser (Panther).
 */
class SymfonyDocSource implements DocSourceInterface
{
    public function getSlug(): string
    {
        return 'symfony';
    }

    public function getLabel(): string
    {
        return 'Symfony';
    }

    public function getDatabaseName(): string
    {
        return 'symfony_certification';
    }

    public function supportsVersion(): bool
    {
        return true;
    }

    public function validateIdentifier(mixed $identifier): bool
    {
        $version = (int) $identifier;

        return is_numeric($identifier) && $version >= 6 && $version <= 8;
    }

    public function getTopicsCollectionName(mixed $identifier = null): string
    {
        return "sf{$identifier}_exam_topics";
    }

    public function getLinksCollectionName(mixed $identifier = null): string
    {
        return "sf{$identifier}_topics_links";
    }

    public function getMcqCollectionName(mixed $identifier = null): string
    {
        return "sf{$identifier}_mcq_gpt-4o";
    }

    public function getDocumentLabel(mixed $identifier = null): string
    {
        return "Symfony $identifier";
    }

    public function requiresBrowserForContent(): bool
    {
        // symfony.com documentation requires JavaScript rendering
        return true;
    }
}

