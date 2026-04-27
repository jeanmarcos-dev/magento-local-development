<?php

declare(strict_types=1);

namespace Development\McpDevTools\Console\Command;

use Development\Core\Model\ProductionGuard;
use Development\McpDevTools\Mcp\ServerFactory;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `bin/magento mcp:serve` — runs the MCP server over stdio.
 *
 * Blocks on STDIN, hands the streams to the Logiscape SDK's stdio transport,
 * and bootstraps Magento exactly once. Every tool call after that reuses
 * the warm object manager.
 *
 * Intended to be spawned as a subprocess by an MCP client like Claude Code
 * or Cursor:
 *
 *   claude mcp add magento-dev "/path/to/magento/bin/magento mcp:serve"
 */
class ServeCommand extends Command
{
    public function __construct(
        private readonly ProductionGuard $guard,
        private readonly ServerFactory $serverFactory,
        private readonly State $appState
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('mcp:serve');
        $this->setDescription('Run the MCP Dev Tools server over stdio. Intended to be spawned as a subprocess by an MCP client.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->guard->isEnabled()) {
            // Stdout is owned by the MCP protocol; route human-facing messages
            // to stderr only. Returning a non-zero exit lets the client surface
            // the disabled state cleanly.
            $output->getErrorOutput()->writeln(
                '<error>MCP Dev Tools is disabled. Run Magento in developer mode or enable "Allow in Production" via Stores → Configuration.</error>'
            );

            return Cli::RETURN_FAILURE;
        }

        try {
            // Ensure an area code is set; the SDK and tools may resolve
            // services that require it. `crontab` is the most permissive
            // and avoids accidentally rendering frontend layout.
            try {
                $this->appState->getAreaCode();
            } catch (\Throwable) {
                $this->appState->setAreaCode('crontab');
            }

            $server = $this->serverFactory->create();
            // Logiscape SDK: $server->run() blocks reading STDIN and writing
            // JSON-RPC responses to STDOUT, until the client closes the stream.
            $server->run();

            return Cli::RETURN_SUCCESS;
        } catch (RuntimeException $e) {
            $output->getErrorOutput()->writeln('<error>' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        } catch (\Throwable $e) {
            $output->getErrorOutput()->writeln('<error>MCP server crashed: ' . $e->getMessage() . '</error>');

            return Cli::RETURN_FAILURE;
        }
    }
}
