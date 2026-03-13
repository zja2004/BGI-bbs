<?php

namespace FlarumAgents\Core;

/**
 * Agent 接口 - 所有Agent必须实现此接口
 */
interface AgentInterface
{
    /**
     * 获取Agent名称
     */
    public function getName(): string;

    /**
     * 获取Agent描述
     */
    public function getDescription(): string;

    /**
     * 执行Agent任务
     * @return array 执行结果
     */
    public function execute(): array;

    /**
     * 获取Agent配置
     */
    public function getConfig(): array;

    /**
     * 更新Agent配置
     */
    public function setConfig(array $config): void;

    /**
     * 获取执行间隔（分钟）
     */
    public function getInterval(): int;

    /**
     * 是否启用
     */
    public function isEnabled(): bool;
}
