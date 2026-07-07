<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\DomainValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainAllowlistTest extends TestCase
{
    use RefreshDatabase;

    private DomainValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DomainValidationService::class);
    }

    public function test_all_domains_allowed_when_setting_is_empty(): void
    {
        // No setting exists — all domains allowed
        $this->assertTrue($this->service->isDomainAllowed('user@anything.com'));
    }

    public function test_all_domains_allowed_when_setting_is_null(): void
    {
        Setting::create(['key' => 'allowed_domains', 'value' => '']);
        $this->assertTrue($this->service->isDomainAllowed('user@anything.com'));
    }

    public function test_specific_domain_is_allowed(): void
    {
        Setting::create(['key' => 'allowed_domains', 'value' => 'company.com,partner.org']);
        $this->assertTrue($this->service->isDomainAllowed('user@company.com'));
        $this->assertTrue($this->service->isDomainAllowed('admin@partner.org'));
    }

    public function test_non_allowed_domain_is_rejected(): void
    {
        Setting::create(['key' => 'allowed_domains', 'value' => 'company.com']);
        $this->assertFalse($this->service->isDomainAllowed('user@evil.com'));
    }

    public function test_domain_matching_is_case_insensitive(): void
    {
        Setting::create(['key' => 'allowed_domains', 'value' => 'Company.COM']);
        $this->assertTrue($this->service->isDomainAllowed('user@company.com'));
    }

    public function test_whitespace_in_domains_is_trimmed(): void
    {
        Setting::create(['key' => 'allowed_domains', 'value' => ' company.com , partner.org ']);
        $this->assertTrue($this->service->isDomainAllowed('user@company.com'));
        $this->assertTrue($this->service->isDomainAllowed('user@partner.org'));
    }
}
