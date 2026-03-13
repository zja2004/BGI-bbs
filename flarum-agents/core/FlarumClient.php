<?php

namespace FlarumAgents\Core;

class FlarumClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct(string $baseUrl, string $apiKey)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
    }

    private function request(string $method, string $endpoint, array $data = [], ?string $authToken = null): array
    {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        $token = $authToken ?? $this->apiKey;
        
        $headers = [
            'Content-Type: application/vnd.api+json',
            'Accept: application/vnd.api+json',
            'Authorization: Token ' . $token
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new \Exception('CURL Error: ' . curl_error($ch));
        }
        curl_close($ch);

        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $error = $result['errors'][0]['detail'] ?? "HTTP Error $httpCode";
            throw new \Exception("Flarum API Error: $error");
        }

        return $result ?? [];
    }

    public function createDiscussion(string $title, string $content, array $tags = [], int $userId = null): array
    {
        $data = [
            'data' => [
                'type' => 'discussions',
                'attributes' => [
                    'title' => $title,
                    'content' => $content
                ],
                'relationships' => [
                    'tags' => [
                        'data' => array_map(fn($tagId) => [
                            'type' => 'tags',
                            'id' => (string)$tagId
                        ], $tags)
                    ]
                ]
            ]
        ];

        $authToken = null;
        if ($userId === 6) {
            $authToken = 'article-bot-token-6';
        } elseif ($userId === 7) {
            $authToken = 'qa-bot-token-7';
        } elseif ($userId === 8) {
            $authToken = 'writer-bot-token-8';
        }

        return $this->request('POST', '/discussions', $data, $authToken);
    }

    /**
     * 获取讨论详情（包含内容）
     */
    public function getDiscussion(int $discussionId): array
    {
        $result = $this->request('GET', "/discussions/$discussionId");
        return $result['data'] ?? [];
    }

    public function getRecentDiscussions(int $limit = 20): array
    {
        $result = $this->request('GET', "/discussions?page[limit]=$limit&sort=-createdAt");
        return $result['data'] ?? [];
    }

    public function replyToDiscussion(int $discussionId, string $content, int $userId = null): array
    {
        $data = [
            'data' => [
                'type' => 'posts',
                'attributes' => ['content' => $content],
                'relationships' => [
                    'discussion' => [
                        'data' => ['type' => 'discussions', 'id' => (string)$discussionId]
                    ]
                ]
            ]
        ];

        $authToken = null;
        if ($userId === 6) {
            $authToken = 'article-bot-token-6';
        } elseif ($userId === 7) {
            $authToken = 'qa-bot-token-7';
        } elseif ($userId === 8) {
            $authToken = 'writer-bot-token-8';
        }

        return $this->request('POST', '/posts', $data, $authToken);
    }

    public function getDiscussionPosts(int $discussionId): array
    {
        $result = $this->request('GET', "/discussions/$discussionId?include=posts");
        $included = $result['included'] ?? [];
        return array_filter($included, fn($item) => $item['type'] === 'posts');
    }

    public function getTags(): array
    {
        $result = $this->request('GET', '/tags');
        return $result['data'] ?? [];
    }

    /**
     * 获取最近的帖子（按时间倒序）
     */
    public function getRecentPosts(int $minutes = 60): array
    {
        // 获取最近的帖子，按时间排序
        $result = $this->request('GET', '/posts?page[limit]=50&sort=-createdAt');
        $posts = $result['data'] ?? [];
        
        // 过滤出指定时间内的帖子
        $cutoffTime = time() - ($minutes * 60);
        
        return array_filter($posts, function($post) use ($cutoffTime) {
            $createdAt = strtotime($post['attributes']['createdAt'] ?? '1970-01-01');
            return $createdAt >= $cutoffTime;
        });
    }
}
