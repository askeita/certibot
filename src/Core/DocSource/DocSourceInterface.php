<?php

namespace App\Core\DocSource;

/**
 * Strategy interface for technology documentation sources.
 *
 * Each technology (Symfony, PHP, Java…) implements this interface and self-registers
 * via a Symfony tagged service. The TechnologyRegistry discovers them automatically,
 * so adding a new technology never requires modifying existing code (Open/Closed Principle).
 */
interface DocSourceInterface
{
    /**
     * Unique slug used in routes and registry keys (e.g. 'symfony', 'php').
     */
    public function getSlug(): string;

    /**
     * Human-readable label (e.g. 'Symfony', 'PHP').
     */
    public function getLabel(): string;

    /**
     * MongoDB database name for this technology.
     */
    public function getDatabaseName(): string;

    /**
     * Whether this source requires a version identifier (e.g. Symfony 7).
     * PHP or JavaScript typically return false.
     */
    public function supportsVersion(): bool;

    /**
     * Validates the identifier passed by the user (e.g. a Symfony version number).
     * For version-less technologies, always return true.
     */
    public function validateIdentifier(mixed $identifier): bool;

    /**
     * Returns the MongoDB collection name for exam/study topics.
     * e.g. 'sf7_exam_topics' or 'php_topics'
     */
    public function getTopicsCollectionName(mixed $identifier = null): string;

    /**
     * Returns the MongoDB collection name for documentation links per topic.
     * e.g. 'sf7_topics_links' or 'php_topics_links'
     */
    public function getLinksCollectionName(mixed $identifier = null): string;

    /**
     * Returns the MongoDB collection name for generated MCQs.
     * e.g. 'sf7_mcq_gpt-4o' or 'php_mcq_gpt-4o'
     */
    public function getMcqCollectionName(mixed $identifier = null): string;

    /**
     * Human-readable label for a specific version/identifier.
     * e.g. 'Symfony 7' or 'PHP'
     */
    public function getDocumentLabel(mixed $identifier = null): string;

    /**
     * Whether fetching documentation page content requires a real browser (Panther/Selenium).
     * Return false for static HTML sites (e.g. php.net) to use a lightweight HTTP client instead.
     */
    public function requiresBrowserForContent(): bool;
}

