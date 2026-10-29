<?php

namespace Tests\Feature;

use App\Modules\Erp\Services\TransitionSheetReader;
use Google\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\{HandlerStack,Middleware};
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class ErpSheetTransitionReaderTest extends TestCase
{
    private array $requests = [];

    private function reader(array $responses): TransitionSheetReader
    {
        // No credentials or external network: Guzzle is replaced at the HTTP boundary.
        config(['sheet_transition.credentials_path'=>__FILE__]);
        $stack = HandlerStack::create(new MockHandler(array_map(fn($body)=>new Response(200,['Content-Type'=>'application/json'],json_encode($body)),$responses)));
        $stack->push(Middleware::history($this->requests));
        $client = new Client();
        $client->setAccessToken(['access_token'=>'synthetic','expires_in'=>3600,'created'=>time()]);
        $client->setHttpClient(new \GuzzleHttp\Client(['handler'=>$stack]));
        return new class($client) extends TransitionSheetReader {
            public function __construct(private Client $fake) {}
            protected function client(string $credentials): Client { return $this->fake; }
            protected function pace(): void {}
        };
    }

    private function metadata(int $rows=3): array
    {
        return ['sheets'=>[['properties'=>['sheetId'=>0,'title'=>"Attendance's Data",'sheetType'=>'GRID','gridProperties'=>['rowCount'=>$rows,'columnCount'=>8]]]]];
    }

    private function header(): array
    {
        return ['values'=>[['ID','Teacher Name','Teacher Code','Student Name','Student Code','Date','Work Hour','Section']]];
    }

    public function test_reader_uses_gid_and_quoted_bounded_ranges_with_read_only_http(): void
    {
        $reader=$this->reader([['version'=>'7'],$this->metadata(),$this->header(),['values'=>[['9','Teacher','CT1','Student','CCA1','2026-10-26','1','AA']]],['version'=>'7']]);
        $beats=0; $result=$reader->read(config('sheet_transition.sources.attendance'),function () use (&$beats) { $beats++; });
        $this->assertSame('7',$result['version']); $this->assertSame('9',$result['rows'][2][0]);
        $this->assertSame(2,$beats);
        $this->assertCount(5,$this->requests);
        foreach ($this->requests as $call) $this->assertSame('GET',$call['request']->getMethod());
        $uri=urldecode((string)$this->requests[3]['request']->getUri());
        $this->assertStringContainsString("'Attendance''s Data'!A2:H3",$uri);
    }

    public function test_header_drift_stops_before_reading_body(): void
    {
        $reader=$this->reader([['version'=>'7'],$this->metadata(),['values'=>[['ID','Student Code','Teacher Code']]]]);
        try { $reader->read(config('sheet_transition.sources.attendance'),fn()=>null); $this->fail('Expected drift rejection'); }
        catch(\RuntimeException $e) { $this->assertSame('header_changed',$e->getMessage()); }
        $this->assertCount(3,$this->requests);
    }

    public function test_source_edited_between_pages_is_rejected(): void
    {
        $reader=$this->reader([['version'=>'7'],$this->metadata(),$this->header(),['values'=>[]],['version'=>'8']]);
        $this->expectExceptionMessage('source_changed_during_read');
        $reader->read(config('sheet_transition.sources.attendance'),fn()=>null);
    }

    public function test_oversized_grid_fails_instead_of_truncating(): void
    {
        $reader=$this->reader([['version'=>'7'],$this->metadata(100001)]);
        $this->expectExceptionMessage('source_grid_limit');
        $reader->read(config('sheet_transition.sources.attendance'),fn()=>null);
    }

    public function test_blank_pages_do_not_end_scan_before_later_rows(): void
    {
        $reader=$this->reader([['version'=>'7'],$this->metadata(503),$this->header(),['values'=>[]],['values'=>[[],['44']]],['version'=>'7']]);
        $result=$reader->read(config('sheet_transition.sources.attendance'),fn()=>null);
        $this->assertSame('44',$result['rows'][503][0]);
        $this->assertCount(6,$this->requests);
    }

    public function test_payload_limit_rejects_the_whole_source(): void
    {
        config(['sheet_transition.max_payload_bytes'=>10]);
        $reader=$this->reader([['version'=>'7'],$this->metadata(),$this->header(),['values'=>[['a larger than allowed value']]]]);
        $this->expectExceptionMessage('source_payload_limit');
        $reader->read(config('sheet_transition.sources.attendance'),fn()=>null);
    }
}
