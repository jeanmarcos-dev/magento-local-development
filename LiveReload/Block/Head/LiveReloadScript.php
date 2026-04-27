<?php

declare(strict_types=1);

namespace Development\LiveReload\Block\Head;

use Development\Core\Model\ProductionGuard;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class LiveReloadScript extends Template
{
    public function __construct(
        Context $context,
        private readonly ProductionGuard $guard,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->guard->isEnabled();
    }

    public function getCacheKeyInfo(): array
    {
        return array_merge(parent::getCacheKeyInfo(), [
            'livereload_enabled_' . ((int) $this->isEnabled()),
        ]);
    }

    protected function _toHtml(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }
}
