<?php

declare(strict_types=1);

use Nexus\CitationNetwork\Domain\CitationGraph;
use Nexus\CitationNetwork\Domain\CitationGraphType;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraph;
use Nexus\Dissemination\Application\UseCase\ExportCitationGraphHandler;
use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerCollection;
use Nexus\Dissemination\Domain\Port\CitationGraphSerializerPort;
use Nexus\Dissemination\Domain\Port\FileStoragePort;
use Tests\Support\PersistenceFactory;

it('stores serialized citation graphs with matching serializer', function (): void {
    $graph = CitationGraph::create(CitationGraphType::CITATION, 'project-1');
    $graph->addWork(PersistenceFactory::makeWork());

    $serializer = new class implements CitationGraphSerializerPort {
        public function serialize(CitationGraph $graph, NetworkExportFormat $format): string
        {
            return 'graph-content';
        }

        public function supports(NetworkExportFormat $format): bool
        {
            return $format === NetworkExportFormat::GRAPHML;
        }
    };

    $storage = exportCitationGraphHandlerTestStorage();
    $handler = new ExportCitationGraphHandler(
        new CitationGraphSerializerCollection($serializer),
        $storage,
    );

    $path = $handler->handle(new ExportCitationGraph(
        graph: $graph,
        format: NetworkExportFormat::GRAPHML,
        filename: 'exports/network.graphml',
    ));

    expect($path)->toBe('exports/network.graphml')
        ->and($storage->get('exports/network.graphml'))->toBe('graph-content');
});

it('rejects citation graph exports whose filename extension does not match the format', function (): void {
    $handler = new ExportCitationGraphHandler(
        new CitationGraphSerializerCollection(),
        exportCitationGraphHandlerTestStorage(),
    );

    expect(fn () => $handler->handle(new ExportCitationGraph(
        graph: CitationGraph::create(CitationGraphType::CITATION, 'project-1'),
        format: NetworkExportFormat::GEXF,
        filename: 'exports/network.graphml',
    )))->toThrow(InvalidArgumentException::class, 'Export filename extension must match format gexf (.gexf): exports/network.graphml');
});

function exportCitationGraphHandlerTestStorage(): FileStoragePort
{
    return new class implements FileStoragePort {
        /** @var array<string, string> */
        public array $stored = [];

        public function store(string $filename, string $content): string
        {
            $this->stored[$filename] = $content;

            return $filename;
        }

        public function get(string $path): string
        {
            return $this->stored[$path] ?? '';
        }

        public function delete(string $path): void
        {
            unset($this->stored[$path]);
        }

        public function exists(string $path): bool
        {
            return array_key_exists($path, $this->stored);
        }

        public function url(string $path): ?string
        {
            return $this->exists($path) ? 'memory://' . $path : null;
        }
    };
}
