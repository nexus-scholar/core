<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Domain\Port\NetworkSerializerPort;
use Nexus\Search\Domain\CorpusSlice;

final class CytoscapeSerializer implements NetworkSerializerPort
{
	public function serialize(CorpusSlice $corpus): string
	{
		$nodes = [];
		foreach ($corpus->all() as $work) {
			$id = $work->primaryId()?->toString() ?? 'work_' . spl_object_hash($work);
			$nodes[] = [
				'data' => [
					'id' => $id,
					'label' => $work->title(),
				],
			];
		}

		$payload = [
			'elements' => [
				'nodes' => $nodes,
				'edges' => [],
			],
		];

		return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	public function supports(NetworkExportFormat $format): bool
	{
		return $format === NetworkExportFormat::CYTOSCAPE;
	}
}
