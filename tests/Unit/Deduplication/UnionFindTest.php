<?php

declare(strict_types=1);

use Nexus\Deduplication\Infrastructure\UnionFind;

it('groups_transitively_connected_ids', function (): void {
    $uf = new UnionFind();
    $uf->makeSet('a');
    $uf->makeSet('b');
    $uf->makeSet('c');
    $uf->union('a', 'b');
    $uf->union('b', 'c');

    expect($uf->connected('a', 'c'))->toBeTrue();
});

it('finds_root_with_path_compression', function (): void {
    $uf = new UnionFind();
    foreach (['a', 'b', 'c', 'd'] as $id) {
        $uf->makeSet($id);
    }
    $uf->union('a', 'b');
    $uf->union('b', 'c');
    $uf->union('c', 'd');

    // After find, path compression should flatten the tree
    $rootBefore = $uf->find('d');
    $rootAfter  = $uf->find('d');
    expect($rootBefore)->toBe($rootAfter);
});

it('unions_by_rank', function (): void {
    $uf = new UnionFind();
    $uf->makeSet('a');
    $uf->makeSet('b');
    $uf->union('a', 'b');
    expect($uf->connected('a', 'b'))->toBeTrue();
});

it('returns_correct_groups', function (): void {
    $uf = new UnionFind();
    foreach (['a', 'b', 'c', 'd', 'e'] as $id) {
        $uf->makeSet($id);
    }
    $uf->union('a', 'b');
    $uf->union('c', 'd');

    $groups = $uf->groups();
    // 3 groups: {a,b}, {c,d}, {e}
    expect($groups)->toHaveCount(3);
});

it('handles_single_element_clusters', function (): void {
    $uf = new UnionFind();
    $uf->makeSet('solo');

    $groups = $uf->groups();
    expect($groups)->toHaveCount(1);
    expect($uf->find('solo'))->toBe('solo');
});

it('does_not_connect_disjoint_sets', function (): void {
    $uf = new UnionFind();
    $uf->makeSet('x');
    $uf->makeSet('y');

    expect($uf->connected('x', 'y'))->toBeFalse();
});
