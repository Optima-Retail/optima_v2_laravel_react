<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TabulatorQuery;
use Illuminate\Http\Request;
use Tests\TestCase;

final class TabulatorQueryTest extends TestCase
{
    public function test_it_parses_tabulator_sort_array_page_and_size(): void
    {
        $request = Request::create('/companies/data', 'GET', [
            'page' => 2,
            'size' => 25,
            'search' => ' acme ',
            'kind' => 'party',
            'sort' => [
                ['field' => 'tax_id', 'dir' => 'desc'],
            ],
        ]);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name', 'tax_id', 'kind'],
            filterKeys: ['search', 'kind'],
        );

        $this->assertSame(2, $filters['page']);
        $this->assertSame(25, $filters['per_page']);
        $this->assertSame('acme', $filters['search']);
        $this->assertSame('party', $filters['kind']);
        $this->assertSame('tax_id', $filters['sort']);
        $this->assertSame('desc', $filters['direction']);
    }

    public function test_it_falls_back_for_invalid_sort_and_size(): void
    {
        $request = Request::create('/companies/data', 'GET', [
            'size' => 999,
            'sort' => [
                ['field' => 'hacked', 'dir' => 'sideways'],
            ],
        ]);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['id', 'name'],
            defaultSort: 'name',
        );

        $this->assertSame(12, $filters['per_page']);
        $this->assertSame('name', $filters['sort']);
        $this->assertSame('asc', $filters['direction']);
    }

    public function test_it_maps_header_filter_values_when_query_keys_empty(): void
    {
        $request = Request::create('/companies/data', 'GET', [
            'filter' => [
                ['field' => 'kind', 'type' => '=', 'value' => 'holding'],
            ],
        ]);

        $filters = TabulatorQuery::fromRequest(
            $request,
            allowedSorts: ['name'],
            filterKeys: ['search', 'kind'],
        );

        $this->assertSame('holding', $filters['kind']);
    }
}
