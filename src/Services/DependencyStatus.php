<?php

namespace Plugins\Glitter\Translate\Services;

use App\Contracts\Repositories\ModuleRepositoryInterface;
use App\Models\Module;

class DependencyStatus
{
    public const MODULE = 'sirsoft-board';
    public const MINIMUM_VERSION = '1.1.2';

    public function resolve(): array
    {
        /** @var ModuleRepositoryInterface $repository */
        $repository = app(ModuleRepositoryInterface::class);
        $module = $repository->findByIdentifier(self::MODULE);

        if (! $module instanceof Module) {
            return [
                'installed' => false,
                'active' => false,
                'compatible' => false,
                'version' => null,
            ];
        }

        $version = (string) $module->version;

        return [
            'installed' => true,
            'active' => $module->isActive(),
            'compatible' => version_compare($version, self::MINIMUM_VERSION, '>='),
            'version' => $version,
        ];
    }

    public function isReady(): bool
    {
        $status = $this->resolve();

        return $status['installed'] && $status['active'] && $status['compatible'];
    }
}
