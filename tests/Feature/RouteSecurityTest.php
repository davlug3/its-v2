<?php

namespace Tests\Feature;

use Tests\TestCase;

class RouteSecurityTest extends TestCase
{
    public function test_admin_routes_require_acl_middleware(): void
    {
        $routes = app('router')->getRoutes();

        $adminUsers = $routes->getByName(null); // not named reliably; inspect by URI
        $found = false;
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'api/v1/admin/users') && $route->uri() === 'api/v1/admin/users') {
                $found = true;
                $middleware = $route->gatherMiddleware();
                $this->assertTrue(
                    collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'acl:')),
                    'Admin users route must require ACL middleware.'
                );
            }
        }

        $this->assertTrue($found, 'Admin users route not found.');
    }

    public function test_reports_routes_require_acl_middleware(): void
    {
        $routes = app('router')->getRoutes();
        $found = false;
        foreach ($routes as $route) {
            if ($route->uri() === 'api/v1/reports/tickets') {
                $found = true;
                $middleware = $route->gatherMiddleware();
                $this->assertTrue(
                    collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'acl:')),
                    'Reports route must require ACL middleware.'
                );
            }
        }

        $this->assertTrue($found, 'Reports route not found.');
    }

    public function test_ticket_mutation_routes_require_auth_middleware(): void
    {
        $routes = app('router')->getRoutes();
        foreach ($routes as $route) {
            if ($route->uri() === 'api/v1/tickets/{id}/close') {
                $middleware = $route->gatherMiddleware();
                $this->assertTrue(
                    collect($middleware)->contains(fn ($m) => $m === 'auth:api' || str_contains((string) $m, 'Authenticate')),
                    'Ticket close route must require authentication.'
                );

                return;
            }
        }

        $this->fail('Ticket close route not found.');
    }
}
