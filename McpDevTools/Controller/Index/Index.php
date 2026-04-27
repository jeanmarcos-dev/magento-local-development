<?php

declare(strict_types=1);

namespace Development\McpDevTools\Controller\Index;

use Development\Core\Model\ProductionGuard;
use Development\McpDevTools\Mcp\ServerFactory;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * `POST /dev-mcp` — experimental single-shot MCP HTTP transport for v0.1.
 *
 * Hard constraints in v0.1:
 *  - Loopback-only by default (REMOTE_ADDR ∈ {127.0.0.1, ::1}); flip via config.
 *  - X-MCP-Token header must hash_equals() the configured token.
 *  - Production guard must be on.
 *  - No session continuity, no MCP `initialize` ceremony — the request body is
 *    expected to be a single JSON-RPC `tools/call` envelope. Other methods
 *    return JSON-RPC error -32601 (method not found).
 *
 * v0.2 will graduate this to full Streamable HTTP with Mcp-Session-Id.
 */
class Index implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const TOKEN_HEADER = 'X-MCP-Token';
    private const PATH_TOKEN = 'development/mcp_dev_tools/auth_token';
    private const PATH_LOOPBACK_ONLY = 'development/mcp_dev_tools/http_loopback_only';

    public function __construct(
        private readonly ProductionGuard $guard,
        private readonly ServerFactory $serverFactory,
        private readonly RequestInterface $request,
        private readonly RawFactory $rawResultFactory,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(): ResultInterface
    {
        if (!$this->guard->isEnabled()) {
            return $this->jsonRpcError(-32603, 'MCP Dev Tools is disabled in production mode.', null, 503);
        }

        if (!$this->isLoopbackAllowed()) {
            return $this->jsonRpcError(-32603, 'HTTP transport is restricted to loopback only.', null, 403);
        }

        if (!$this->tokenMatches()) {
            return $this->jsonRpcError(-32600, 'Invalid or missing X-MCP-Token.', null, 401);
        }

        $body = (string) $this->request->getContent();
        if ($body === '') {
            return $this->jsonRpcError(-32600, 'Empty request body.', null, 400);
        }

        $envelope = json_decode($body, true);
        if (!is_array($envelope)) {
            return $this->jsonRpcError(-32700, 'Parse error: request body is not valid JSON.', null, 400);
        }

        $id = $envelope['id'] ?? null;
        $method = $envelope['method'] ?? null;
        if ($method !== 'tools/call') {
            return $this->jsonRpcError(-32601, 'Only "tools/call" is supported in v0.1 HTTP. Use stdio for the full protocol.', $id, 400);
        }

        try {
            $server = $this->serverFactory->create();
            // The Logiscape SDK exposes a per-request handler we can use to
            // dispatch a single JSON-RPC message without binding a long-lived
            // transport. The exact API is verified during integration testing;
            // we wrap defensively.
            if (method_exists($server, 'handle')) {
                $response = $server->handle($envelope);
            } elseif (method_exists($server, 'dispatch')) {
                $response = $server->dispatch($envelope);
            } else {
                return $this->jsonRpcError(
                    -32603,
                    'SDK does not expose a single-shot handler in this version. Use stdio transport.',
                    $id,
                    501
                );
            }

            return $this->jsonResponse($response, 200);
        } catch (\Throwable $e) {
            return $this->jsonRpcError(-32603, 'Internal error: ' . $e->getMessage(), $id, 500);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function isLoopbackAllowed(): bool
    {
        $loopbackOnly = $this->scopeConfig->isSetFlag(self::PATH_LOOPBACK_ONLY, ScopeInterface::SCOPE_STORE);
        if (!$loopbackOnly) {
            return true;
        }
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return $remote === '127.0.0.1' || $remote === '::1';
    }

    private function tokenMatches(): bool
    {
        $expected = (string) $this->scopeConfig->getValue(self::PATH_TOKEN, ScopeInterface::SCOPE_STORE);
        if ($expected === '') {
            return false;
        }
        $provided = (string) $this->request->getHeader(self::TOKEN_HEADER);

        return hash_equals($expected, $provided);
    }

    private function jsonResponse(mixed $payload, int $httpStatus): ResultInterface
    {
        $raw = $this->rawResultFactory->create();
        $raw->setHttpResponseCode($httpStatus);
        $raw->setHeader('Content-Type', 'application/json', true);
        $raw->setHeader('Cache-Control', 'no-store', true);
        $raw->setContents(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $raw;
    }

    private function jsonRpcError(int $code, string $message, mixed $id, int $httpStatus): ResultInterface
    {
        return $this->jsonResponse(
            [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
            $httpStatus
        );
    }
}
