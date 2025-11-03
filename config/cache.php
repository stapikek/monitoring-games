<?php
// config/cache.php - простая система кеширования

class SimpleCache {
    private $cache_dir;
    private $default_ttl = 3600; // 1 час по умолчанию
    
    public function __construct($cache_dir = null) {
        $this->cache_dir = $cache_dir ?? __DIR__ . '/../cache/';
        
        if (!file_exists($this->cache_dir)) {
            mkdir($this->cache_dir, 0755, true);
        }
    }
    
    public function get($key) {
        $file = $this->getCacheFile($key);
        
        if (!file_exists($file)) {
            return null;
        }
        
        $data = file_get_contents($file);
        $cache = unserialize($data);
        
        if ($cache['expires'] < time()) {
            unlink($file);
            return null;
        }
        
        return $cache['data'];
    }
    
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->default_ttl;
        $file = $this->getCacheFile($key);
        
        $cache = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($file, serialize($cache)) !== false;
    }
    
    public function delete($key) {
        $file = $this->getCacheFile($key);
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return true;
    }
    
    public function clear() {
        $files = glob($this->cache_dir . '*.cache');
        
        foreach ($files as $file) {
            unlink($file);
        }
        
        return true;
    }
    
    public function remember($key, callable $callback, $ttl = null) {
        $data = $this->get($key);
        
        if ($data !== null) {
            return $data;
        }
        
        $data = $callback();
        $this->set($key, $data, $ttl);
        
        return $data;
    }
    
    private function getCacheFile($key) {
        return $this->cache_dir . md5($key) . '.cache';
    }
    
    public function cleanExpired() {
        $files = glob($this->cache_dir . '*.cache');
        $deleted = 0;
        
        foreach ($files as $file) {
            $data = file_get_contents($file);
            $cache = unserialize($data);
            
            if ($cache['expires'] < time()) {
                unlink($file);
                $deleted++;
            }
        }
        
        return $deleted;
    }
}

$cache = new SimpleCache();

function cache($key, callable $callback = null, $ttl = 3600) {
    global $cache;
    
    if ($callback === null) {
        return $cache->get($key);
    }
    
    return $cache->remember($key, $callback, $ttl);
}

function cacheQuery($db, $query, $params = [], $ttl = 3600) {
    $cache_key = 'query_' . md5($query . serialize($params));
    
    return cache($cache_key, function() use ($db, $query, $params) {
        $stmt = $db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }, $ttl);
}


