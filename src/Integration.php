<?php

declare(strict_types=1);

namespace Scafera\Integration;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
class Integration extends Autowire
{
    public function __construct(string $name, ?string $key = null)
    {
        if ($key !== null) {
            parent::__construct(param: 'scafera.integration.' . $name . '.' . $key);
        } else {
            parent::__construct(service: 'scafera.integration.' . $name);
        }
    }
}
