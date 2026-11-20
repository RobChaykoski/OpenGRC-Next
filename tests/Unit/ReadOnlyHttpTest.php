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

    #[Test]
    public function a_ca_bundle_replaces_the_trust_store_without_disabling_verification(): void
    {
        ReadOnlyHttp::register();

        $path = tempnam(sys_get_temp_dir(), 'opengrc-ca-');
        file_put_contents($path, 'test-ca');

        try {
            $read = Http::grcReadOnly('https://example.test', null, [], $path);
            $auth = Http::grcAuthenticate('https://example.test', '/security/user/authenticate', $path);

            $readOptions = (new \ReflectionProperty($read, 'options'))->getValue($read);
            $authOptions = (new \ReflectionProperty($auth, 'options'))->getValue($auth);

            $this->assertSame($path, $readOptions['verify']);
            $this->assertSame($path, $authOptions['verify']);
            $this->assertFalse($readOptions['allow_redirects']);
            $this->assertFalse($authOptions['allow_redirects']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function the_authentication_macro_only_permits_the_named_path(): void
    {
        ReadOnlyHttp::register();
        Http::fake();

        $this->expectException(RuntimeException::class);

        Http::grcAuthenticate('https://example.test', '/security/user/authenticate')->post('/agents');
    }

    #[Test]
    public function the_authentication_macro_blocks_a_get_request(): void
    {
        ReadOnlyHttp::register();
        Http::fake();

        $this->expectException(RuntimeException::class);

        Http::grcAuthenticate('https://example.test', '/security/user/authenticate')->get('/security/user/authenticate');
    }
}
