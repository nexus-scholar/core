<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

enum CorpusOperation: string
{
    case SEARCH = 'search';
    case DEDUPLICATE = 'deduplicate';
    case SNOWBALL = 'snowball';
    case SCREEN = 'screen';
    case ADJUDICATE = 'adjudicate';
    case BUILD_GRAPH = 'build_graph';
    case RETRIEVE_FULL_TEXT = 'retrieve_full_text';
    case EXPORT = 'export';
}
