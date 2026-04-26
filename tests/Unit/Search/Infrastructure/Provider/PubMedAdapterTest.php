<?php

declare(strict_types=1);

namespace Tests\Unit\Search\Infrastructure\Provider;

use Nexus\Search\Domain\Port\HttpClientPort;
use Nexus\Search\Domain\Port\HttpResponse;
use Nexus\Search\Domain\Port\RateLimiterPort;
use Nexus\Search\Domain\SearchQuery;
use Nexus\Search\Domain\SearchTerm;
use Nexus\Search\Infrastructure\Provider\ProviderConfig;
use Nexus\Search\Infrastructure\Provider\PubMedAdapter;

it('handles_missing_pubmed_xml_nodes_gracefully', function (): void {
    $http = \Mockery::mock(HttpClientPort::class);
    
    // esearch returns 1 id
    $http->shouldReceive('get')->once()->withArgs(function($url, $params) {
        return str_contains($url, 'esearch.fcgi');
    })->andReturn(new HttpResponse(200, [], "<?xml version=\"1.0\"?>\n<eSearchResult><Count>1</Count><IdList><Id>12345</Id></IdList></eSearchResult>"));

    // efetch returns an article without an author list, abstract, or journal
    $malformedXml = <<<XML
<?xml version="1.0"?>
<PubmedArticleSet>
  <PubmedArticle>
    <MedlineCitation>
      <PMID>12345</PMID>
      <Article>
        <ArticleTitle>A title without authors</ArticleTitle>
      </Article>
    </MedlineCitation>
  </PubmedArticle>
</PubmedArticleSet>
XML;

    $http->shouldReceive('get')->once()->withArgs(function($url, $params) {
        return str_contains($url, 'efetch.fcgi');
    })->andReturn(new HttpResponse(200, [], $malformedXml));

    $rateLimiter = \Mockery::mock(RateLimiterPort::class);
    $rateLimiter->shouldReceive('waitForToken')->twice();

    $config = new ProviderConfig('pubmed', 'http://pubmed.org', 10.0);

    $adapter = new PubMedAdapter($http, $rateLimiter, $config);

    $query = new SearchQuery(new SearchTerm('test'));
    $results = $adapter->search($query);

    expect($results)->toHaveCount(1);
    expect($results[0]->title())->toBe('A title without authors');
    expect($results[0]->authors()->count())->toBe(0);
    expect($results[0]->year())->toBeNull();
    expect($results[0]->abstract())->toBeNull();
});
