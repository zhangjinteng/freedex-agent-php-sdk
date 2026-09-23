<?php
declare(strict_types=1);

// 仓库示例可直接运行；正式商户项目使用 Composer 的 vendor/autoload.php。
spl_autoload_register(function (string $class): void {
    $prefix = 'Freedex\\Agent\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $file = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
