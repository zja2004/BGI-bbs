<?php
// autoload.php - 自定义类加载
spl_autoload_register(function ($class) {
    $prefix = 'FlarumAgents\\';
    $base_dir = __DIR__ . '/../';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    
    // 目录结构映射（小写目录名）
    $dirMap = [
        'Core' => 'core',
        'Agents' => 'agents',
        'Utils' => 'utils',
    ];
    
    $parts = explode('\\', $relative_class);
    if (isset($parts[0]) && isset($dirMap[$parts[0]])) {
        $parts[0] = $dirMap[$parts[0]];
    }
    
    $file = $base_dir . implode('/', $parts) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});
