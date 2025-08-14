<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/Job.php';

class JobTest extends TestCase
{
    public function testGetFilteredReturnsMockedJobs()
    {
        // Step 1: Mock the PDOStatement to return fake job data
        $mockStmt = $this->createMock(PDOStatement::class);
        $mockStmt->method('fetchAll')->willReturn([
            [
                'id' => 1,
                'description' => 'Mock Job',
                'scheduled_date' => '2025-08-07',
                'scheduled_time' => '09:00:00',
                'status' => 'scheduled',
                'duration_minutes' => 60,
                'customer_id' => 10,
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'address_line1' => '123 Main St',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '78704',
                'job_type_ids' => '1,2'
            ]
        ]);

        // Step 2: Mock the PDO object
        $mockPdo = $this->createMock(PDO::class);
        $mockPdo->method('prepare')->willReturn($mockStmt);

        // Step 3: Run the test
        $results = Job::getFiltered($mockPdo, 'scheduled');

        // Step 4: Assert the mock data is returned correctly
        $this->assertCount(1, $results);
        $this->assertEquals('Mock Job', $results[0]['description']);
        $this->assertEquals('Jane', $results[0]['first_name']);
        $this->assertEquals('1,2', $results[0]['job_type_ids']);
    }
}
