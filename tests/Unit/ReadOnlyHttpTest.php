<?php

namespace Tests\Unit;

use App\Support\Http\ReadOnlyHttp;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ReadOnlyHttpTest extends TestCase
{
    #[Test]
    public function get_and_head_requests_are_allowed(): void
    {
        $this->assertSame('GET', ReadOnlyHttp::guard(new Request('GET', 'https://example.test'))->getMethod());
        $this->assertSame('HEAD', ReadOnlyHttp::guard(new Request('HEAD', 'https://example.test'))->getMethod());
    }

    /**
     * @return array<int, array{0: string}>
     */
    public static function writeMethods(): array
    {
        return [['POST'], ['PUT'], ['PATCH'], ['DELETE']];
    }

    #[DataProvider('writeMethods')]
    #[Test]
    public function write_methods_are_blocked_before_they_leave_the_process(string $method): void
    {
        $this->expectException(RuntimeException::class);

        ReadOnlyHttp::guard(new Request($method, 'https://example.test'));
    }

    #[Test]
    public function the_macro_blocks_a_write_request(): void
    {
        ReadOnlyHttp::register();
        Http::fake();

        $this->expectException(RuntimeException::class);

        Http::grcReadOnly('https://example.test')->post('/collect');
    }

    #[Test]
    public function callers_cannot_weaken_the_transport_guards(): void
    {
        ReadOnlyHttp::register();

        $request = Http::grcReadOnly('https://example.test', null, [
            'allow_redirects' => true,
            'verify' => false,
        ]);

        $options = (new \ReflectionProperty($request, 'options'))->getValue($request);

        $this->assertFalse($options['allow_redirects']);
        $this->assertTrue($options['verify']);
    }
}
