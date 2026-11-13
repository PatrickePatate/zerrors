<?php

namespace Tests\Feature\Organization;

use App\Livewire\MembersManager;
use Livewire\Attributes\Locked;
use ReflectionProperty;
use Tests\TestCase;

class MembersManagerLockedTest extends TestCase
{
    public function test_the_organization_property_is_locked(): void
    {
        $attributes = (new ReflectionProperty(MembersManager::class, 'organization'))->getAttributes();

        $this->assertContains(Locked::class, array_map(fn ($a) => $a->getName(), $attributes));
    }
}
