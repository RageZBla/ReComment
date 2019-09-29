<?php
declare(strict_types=1);

namespace Tests\Feature;

use Tests\CleanDatabase;

class LoginTest extends TestCase
{
    use CleanDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanDatabase($this->app->get('redis'));
    }

    /**
     * @test
     */
    public function it_allows_user_to_log_in()
    {
        $response = $this->get('/');

        $response->assertSeeText('You are not logged in!');

        $response = $this->get('/login');

        $response->assertSee('Username');

        $response = $this->post('/login', ['username' => 'john']);
        $response->assertRedirect('/');
    }

    /**
     * @test
     */
    public function it_shows_logged_in_username()
    {
        $response = $this->loggedInRequest('GET', '/');

        $response->assertSeeText('jane');
    }
}
