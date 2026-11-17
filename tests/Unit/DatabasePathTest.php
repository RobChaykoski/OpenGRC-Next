<?php

namespace Tests\Unit;

use App\Support\DatabasePath;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabasePathTest extends TestCase
{
    #[Test]
    public function it_converts_windows_separators_to_forward_slashes(): void
    {
        $this->assertSame(
            'C:/Users/rob/repos/OpenGRC/database/opengrc.sqlite',
            DatabasePath::normalize('C:\Users\rob\repos\OpenGRC\database\opengrc.sqlite')
        );
    }

    #[Test]
    public function it_leaves_posix_paths_untouched(): void
    {
        $this->assertSame(
            '/var/www/html/database/opengrc.sqlite',
            DatabasePath::normalize('/var/www/html/database/opengrc.sqlite')
        );
    }

    #[Test]
    public function it_detects_escaping_windows_separators(): void
    {
        $this->assertTrue(DatabasePath::hasWindowsSeparators('C:\app\database\opengrc.sqlite'));
        $this->assertFalse(DatabasePath::hasWindowsSeparators('C:/app/database/opengrc.sqlite'));
        $this->assertFalse(DatabasePath::hasWindowsSeparators(':memory:'));
        $this->assertFalse(DatabasePath::hasWindowsSeparators(null));
    }
}
