<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use DOMDocument;
use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Domain\Port\NetworkSerializerPort;
use Nexus\Search\Domain\CorpusSlice;

final class GraphMlSerializer implements NetworkSerializerPort
{
	public function serialize(CorpusSlice $corpus): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$graphml = $doc->createElement('graphml');
		$graphml->setAttribute('xmlns', 'http://graphml.graphdrawing.org/xmlns');
		$graphml->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
		$graphml->setAttribute(
			'xsi:schemaLocation',
			'http://graphml.graphdrawing.org/xmlns http://graphml.graphdrawing.org/xmlns/1.0/graphml.xsd'
		);

		$key = $doc->createElement('key');
		$key->setAttribute('id', 'label');
		$key->setAttribute('for', 'node');
		$key->setAttribute('attr.name', 'label');
		$key->setAttribute('attr.type', 'string');
		$graphml->appendChild($key);

		$graph = $doc->createElement('graph');
		$graph->setAttribute('id', 'corpus');
		$graph->setAttribute('edgedefault', 'directed');

		foreach ($corpus->all() as $work) {
			$id = $work->primaryId()?->toString() ?? 'work_' . spl_object_hash($work);

			$node = $doc->createElement('node');
			$node->setAttribute('id', $id);

			$data = $doc->createElement('data');
			$data->setAttribute('key', 'label');
			$data->appendChild($doc->createTextNode($work->title()));
			$node->appendChild($data);

			$graph->appendChild($node);
		}

		$graphml->appendChild($graph);
		$doc->appendChild($graphml);

		return $doc->saveXML() ?: '';
	}

	public function supports(NetworkExportFormat $format): bool
	{
		return $format === NetworkExportFormat::GRAPHML;
	}
}
