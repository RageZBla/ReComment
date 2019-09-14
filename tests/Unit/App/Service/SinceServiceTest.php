<?php
declare(strict_types=1);

namespace Tests\Unit\App\Service;

use App\Service\SinceService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class SinceServiceTest extends TestCase
{

    /**
     * @var \Carbon\Carbon
     */
    private $now;

    protected function setUp()
    {
        parent::setUp();
    }

    protected function tearDown()
    {
        parent::tearDown();
        Carbon::setTestNow(null);
    }


    /**
     * @test
     * @dataProvider dataProvider
     */
    public function it_format_time_elapsed(int $input, string $expected): void
    {
        Carbon::setTestNow($this->now);

        $this->assertSame($expected, (new SinceService())->since($input));
    }

    public function dataProvider(): \Generator
    {
        $this->now = CarbonImmutable::now();
        $convert = function (\DateTimeInterface $d) {
            return (int)$d->format('U');
        };

        yield [$convert($this->now), '0 second'];
        yield [$convert($this->now->subSecond()), '1 second'];
        yield [$convert($this->now->subSeconds(2)), '2 seconds'];
        yield [$convert($this->now->subSeconds(59)), '59 seconds'];
        yield [$convert($this->now->subSeconds(60)), '1 minute'];
        yield [$convert($this->now->subSeconds(90)), '1 minute'];
        yield [$convert($this->now->subMinutes(2)), '2 minutes'];
        yield [$convert($this->now->subMinutes(59)->subSeconds(59)), '59 minutes'];
        yield [$convert($this->now->subMinutes(59)->subSeconds(60)), '1 hour'];
        yield [$convert($this->now->subHours(2)), '2 hours'];
        yield [$convert($this->now->subHours(23)->subMinutes(59)->subSeconds(59)), '23 hours'];
        yield [$convert($this->now->subHours(23)->subMinutes(59)->subSeconds(60)), '1 day'];
        yield [$convert($this->now->subDays(2)), '2 days'];
        yield [$convert($this->now->subDays(10)), '10 days'];
    }
}
