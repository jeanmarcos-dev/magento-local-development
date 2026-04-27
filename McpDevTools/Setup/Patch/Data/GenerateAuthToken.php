<?php

declare(strict_types=1);

namespace Development\McpDevTools\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Setup\Patch\PatchRevertableInterface;

/**
 * One-time data patch: generates a 32-byte random token, base64-encodes it,
 * and stores it at `development/mcp_dev_tools/auth_token` if no value is set.
 *
 * Idempotent: re-running does nothing if the token already has a value.
 *
 * To rotate: clear the field via admin (or `bin/magento config:set ... ""`)
 * and re-run `bin/magento setup:upgrade`.
 */
class GenerateAuthToken implements DataPatchInterface, PatchRevertableInterface
{
    private const PATH = 'development/mcp_dev_tools/auth_token';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly WriterInterface $configWriter
    ) {
    }

    public function apply(): self
    {
        $existing = (string) $this->scopeConfig->getValue(self::PATH);
        if ($existing !== '') {
            return $this;
        }

        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $this->configWriter->save(self::PATH, $token);

        return $this;
    }

    public function revert(): void
    {
        $this->configWriter->delete(self::PATH);
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
