<?php

namespace Tests\Feature;

use App\Models\SoftwareComponent;
use App\Services\SupplyChain\OsvClient;
use App\Services\SupplyChain\Purl;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OsvClientTest extends TestCase
{
    #[Test]
    public function it_builds_osv_queries_for_supported_purl_types(): void
    {
        $this->assertSame('Packagist', Purl::ecosystem('pkg:composer/laravel/framework@11.0.0'));
        $this->assertSame('npm', Purl::ecosystem('pkg:npm/%40angular/core@17.0.0'));
        $this->assertSame('Maven', Purl::ecosystem('pkg:maven/org.apache.logging.log4j/log4j-core@2.17.0'));
        $this->assertSame('Go', Purl::ecosystem('pkg:golang/github.com/gin-gonic/gin@1.9.0'));
        $this->assertNull(Purl::ecosystem('pkg:generic/thing@1.0.0'));

        $npm = new SoftwareComponent([
            'purl' => 'pkg:npm/%40angular/core@17.0.0',
            'name' => 'core',
            'version' => '17.0.0',
        ]);

        $query = Purl::osvQuery($npm);

        $this->assertSame('@angular/core', $query['package']['name']);
        $this->assertSame('npm', $query['package']['ecosystem']);
    }

    #[Test]
    public function it_queries_the_batch_endpoint(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.osv.dev/v1/querybatch' => Http::response([
                'results' => [['vulns' => [['id' => 'GHSA-1']]]],
            ]),
        ]);

        $results = app(OsvClient::class)->queryBatch([
            ['package' => ['name' => 'laravel/framework', 'ecosystem' => 'Packagist'], 'version' => '11.0.0'],
        ]);

        $this->assertSame('GHSA-1', $results[0]['vulns'][0]['id']);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://api.osv.dev/v1/querybatch');
    }

    #[Test]
    public function it_reads_an_advisory_by_get(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.osv.dev/v1/vulns/GHSA-1' => Http::response([
                'id' => 'GHSA-1',
                'summary' => 'Example advisory',
            ]),
        ]);

        $advisory = app(OsvClient::class)->vulnerability('GHSA-1');

        $this->assertSame('Example advisory', $advisory['summary']);
    }

    #[Test]
    public function it_fails_loudly_on_a_batch_error(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.osv.dev/v1/querybatch' => Http::response([], 500),
        ]);

        $this->expectException(RuntimeException::class);

        app(OsvClient::class)->queryBatch([
            ['package' => ['name' => 'laravel/framework', 'ecosystem' => 'Packagist'], 'version' => '11.0.0'],
        ]);
    }
}
