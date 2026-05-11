<?php

declare(strict_types=1);

namespace Nexus\Dissemination\Infrastructure\Serializer;

use DOMDocument;
use Nexus\Dissemination\Domain\NetworkExportFormat;
use Nexus\Dissemination\Domain\Port\NetworkSerializerPort;
use Nexus\Search\Domain\CorpusSlice;

final class GexfSerializer implements NetworkSerializerPort
{
	public function serialize(CorpusSlice $corpus): string
	{
		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->formatOutput = true;

		$gexf = $doc->createElement('gexf');
		$gexf->setAttribute('xmlns', 'http://www.gexf.net/1.2draft');
		$gexf->setAttribute('version', '1.2');

		$graph = $doc->createElement('graph');
		$graph->setAttribute('mode', 'static');
		$graph->setAttribute('defaultedgetype', 'directed');

		$nodes = $doc->createElement('nodes');
		foreach ($corpus->all() as $work) {
			$id = $work->primaryId()?->toString() ?? 'work_' . spl_object_hash($work);

			$node = $doc->createElement('node');
			$node->setAttribute('id', $id);
			$node->setAttribute('label', $work->title());
			$nodes->appendChild($node);
		}

		$graph->appendChild($nodes);
		$gexf->appendChild($graph);
		$doc->appendChild($gexf);

		return $doc->saveXML() ?: '';
	}

	public function supports(NetworkExportFormat $format): bool
	{
		return $format === NetworkExportFormat::GEXF;
	}
}
