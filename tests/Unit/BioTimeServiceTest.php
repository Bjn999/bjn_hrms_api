<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BioTimeService;
use Illuminate\Support\Facades\Http;
use Exception;

class BioTimeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup mock config variables for testing
        config([
            'services.biotime.server_url' => 'http://biotime-mock-server:8085',
            'services.biotime.username' => 'api_test_user',
            'services.biotime.password' => 'api_test_pass'
        ]);
    }

    /**
     * Test successful authentication token retrieval.
     */
    public function test_auth_token_retrieval_success()
    {
        Http::fake([
            'biotime-mock-server:8085/api-token-auth/' => Http::response(['token' => 'mocked-jwt-token-xyz123'], 200)
        ]);

        $service = new BioTimeService();
        $token = $service->getAuthToken();

        $this->assertEquals('mocked-jwt-token-xyz123', $token);
        
        Http::assertSent(function ($request) {
            return $request->url() === 'http://biotime-mock-server:8085/api-token-auth/' &&
                   $request['username'] === 'api_test_user' &&
                   $request['password'] === 'api_test_pass';
        });
    }

    /**
     * Test authentication failure throws exception.
     */
    public function test_auth_failure_throws_exception()
    {
        Http::fake([
            'biotime-mock-server:8085/api-token-auth/' => Http::response(['detail' => 'Invalid credentials'], 400)
        ]);

        $service = new BioTimeService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("فشل الاتصال بسيرفر BioTime");

        $service->getAuthToken();
    }

    /**
     * Test fetching transactions success with pagination.
     */
    public function test_fetch_transactions_success_with_pagination()
    {
        // Fake both authentication token request and the paginated transactions endpoint requests
        Http::fake([
            'biotime-mock-server:8085/api-token-auth/' => Http::response(['token' => 'mocked-token'], 200),
            'biotime-mock-server:8085/iclock/api/transactions/?*' => Http::sequence()
                ->push([
                    'count' => 3,
                    'next' => 'http://biotime-mock-server:8085/iclock/api/transactions/?page=2',
                    'previous' => null,
                    'results' => [
                        [
                            'id' => 101,
                            'emp_code' => '9991',
                            'punch_time' => '2026-08-01 08:00:00',
                            'punch_state' => '0',
                            'terminal_sn' => 'SN001'
                        ],
                        [
                            'id' => 102,
                            'emp_code' => '9991',
                            'punch_time' => '2026-08-01 17:00:00',
                            'punch_state' => '1',
                            'terminal_sn' => 'SN001'
                        ]
                    ]
                ], 200)
                ->push([
                    'count' => 3,
                    'next' => null,
                    'previous' => 'http://biotime-mock-server:8085/iclock/api/transactions/?page=1',
                    'results' => [
                        [
                            'id' => 103,
                            'emp_code' => '9992',
                            'punch_time' => '2026-08-01 08:30:00',
                            'punch_state' => '0',
                            'terminal_sn' => 'SN002'
                        ]
                    ]
                ], 200)
        ]);

        $service = new BioTimeService();
        $transactions = $service->fetchTransactions('2026-08-01', '2026-08-31', 5);

        // Verify we fetched all 3 records across pages
        $this->assertCount(3, $transactions);
        $this->assertEquals('9991', $transactions[0]['emp_code']);
        $this->assertEquals('9992', $transactions[2]['emp_code']);

        // Assert query parameters were sent correctly
        Http::assertSent(function ($request) {
            if (str_contains($request->url(), 'iclock/api/transactions/')) {
                $this->assertTrue($request->hasHeader('Authorization', 'JWT mocked-token'));
                $urlParts = parse_url($request->url());
                parse_str($urlParts['query'] ?? '', $queryParams);
                $this->assertEquals('2026-08-01 00:00:00', $queryParams['start_time'] ?? null);
                $this->assertEquals('2026-08-31 23:59:59', $queryParams['end_time'] ?? null);
                $this->assertEquals('5', $queryParams['area'] ?? null);
                return true;
            }
            return true;
        });
    }
}