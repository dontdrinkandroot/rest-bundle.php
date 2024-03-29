<?php

namespace Dontdrinkandroot\RestBundle\Metadata\Attribute;

abstract class Writeable
{
    public function __construct(
        public ?bool $byReference = null,
        public ?string $granted = null,
        public ?string $grantedExpression = null,
    ) {
    }

    protected static function parseInstance($config, $instance)
    {
        if (null === $config) {
            return null;
        }

        $instance->byReference = ParseUtils::parseBool($config['byReference'] ?? null);
        $instance->granted = $config['granted'] ?? null;
        $instance->grantedExpression = $config['granted_expression'] ?? null;

        return $instance;
    }
}
