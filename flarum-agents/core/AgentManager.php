<?php

namespace FlarumAgents\Core;

/**
 * Agent 管理器 - 管理所有Agent的注册和执行
 */
class AgentManager
{
    /** @var AgentInterface[] */
    private array $agents = [];
    private string $stateFile;
    private array $state = [];

    public function __construct()
    {
        $this->stateFile = __DIR__ . '/../config/agent_state.json';
        $this->loadState();
    }

    /**
     * 注册Agent
     */
    public function registerAgent(AgentInterface $agent): void
    {
        $this->agents[$agent->getName()] = $agent;
    }

    /**
     * 获取所有Agent
     */
    public function getAllAgents(): array
    {
        return $this->agents;
    }

    /**
     * 获取指定Agent
     */
    public function getAgent(string $name): ?AgentInterface
    {
        return $this->agents[$name] ?? null;
    }

    /**
     * 执行单个Agent
     */
    public function executeAgent(string $name): array
    {
        $agent = $this->getAgent($name);
        if (!$agent) {
            return ['success' => false, 'error' => "Agent '$name' 不存在"];
        }

        if (!$agent->isEnabled()) {
            return ['success' => false, 'error' => "Agent '$name' 已禁用"];
        }

        // 检查是否应该执行（基于间隔）
        if (!$this->shouldExecute($name, $agent->getInterval())) {
            return ['success' => false, 'message' => '未到执行时间，跳过'];
        }

        try {
            $result = $agent->execute();
            $this->updateLastRun($name);
            return ['success' => true, 'result' => $result];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 执行所有到期的Agent
     */
    public function executeAllDue(): array
    {
        $results = [];
        foreach ($this->agents as $name => $agent) {
            $results[$name] = $this->executeAgent($name);
        }
        return $results;
    }

    /**
     * 检查是否应该执行
     */
    private function shouldExecute(string $name, int $intervalMinutes): bool
    {
        $lastRun = $this->state[$name]['last_run'] ?? 0;
        $nextRun = $lastRun + ($intervalMinutes * 60);
        return time() >= $nextRun;
    }

    /**
     * 更新最后执行时间
     */
    private function updateLastRun(string $name): void
    {
        $this->state[$name]['last_run'] = time();
        $this->saveState();
    }

    /**
     * 加载状态
     */
    private function loadState(): void
    {
        if (file_exists($this->stateFile)) {
            $content = file_get_contents($this->stateFile);
            $this->state = json_decode($content, true) ?: [];
        }
    }

    /**
     * 保存状态
     */
    private function saveState(): void
    {
        file_put_contents($this->stateFile, json_encode($this->state, JSON_PRETTY_PRINT));
    }

    /**
     * 强制重置Agent状态
     */
    public function resetState(string $name = null): void
    {
        if ($name) {
            unset($this->state[$name]);
        } else {
            $this->state = [];
        }
        $this->saveState();
    }
}
