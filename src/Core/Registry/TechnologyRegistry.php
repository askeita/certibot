<?php

namespace App\Core\Registry;

use App\Core\DocSource\DocSourceInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * TechnologyRegistry — Plugin Registry Pattern.
 *
 * All classes implementing DocSourceInterface are automatically tagged via services.yaml
 * (_instanceof). This registry collects them and acts as the single source of truth
 * for available technologies.
 *
 * Adding a new technology (e.g. Java) only requires creating a new DocSourceInterface
 * implementation and registering it — zero changes to existing code.
 */
class TechnologyRegistry
{
    /** @var array<string, DocSourceInterface> */
    private array $sources = [];

    /**
     * @param iterable<DocSourceInterface> $sources Auto-injected by Symfony via tagged iterator.
     */
    public function __construct(
        #[AutowireIterator('app.doc_source')]
        iterable $sources
    ) {
        foreach ($sources as $source) {
            $this->sources[$source->getSlug()] = $source;
        }
    }

    /**
     * Returns the DocSource for the given technology slug.
     *
     * @throws \InvalidArgumentException if the slug is not registered.
     */
    public function get(string $slug): DocSourceInterface
    {
        if (!$this->has($slug)) {
            throw new \InvalidArgumentException(
                sprintf('Technology "%s" is not registered. Available: %s', $slug, implode(', ', array_keys($this->sources)))
            );
        }

        return $this->sources[$slug];
    }

    /**
     * Returns whether a technology slug is registered.
     */
    public function has(string $slug): bool
    {
        return isset($this->sources[$slug]);
    }

    /**
     * Returns all registered DocSource instances, keyed by slug.
     *
     * @return array<string, DocSourceInterface>
     */
    public function all(): array
    {
        return $this->sources;
    }
}

