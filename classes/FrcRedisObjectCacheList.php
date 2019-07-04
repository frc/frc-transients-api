<?php

namespace Frc;

use function defined;
use function in_array;
use function str_replace;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class FrcRedisObjectCacheList {

    private $objectCache;

    public function __construct() {
        global $wp_object_cache;
        $this->objectCache = $wp_object_cache;
    }

    public function keys($key, $group = 'transient') {
        $result     = true;
        $derivedKey = $this->objectCache->build_key($key, $group);
        $emptyKey   = $this->objectCache->build_key('', $group);

        // save if group not excluded from redis and redis is up
        if (!in_array($group, $this->objectCache->ignored_groups) && $this->objectCache->redis_status()) {
            try {
                $result = $this->objectCache->redis_instance()->keys($derivedKey);

                foreach ($result as $k => $r) {
                    $result[$k] = str_replace($emptyKey, '', $r);
                }

            } catch (Exception $exception) {
                $this->objectCache->handle_exception($exception);

                return false;
            }
        }

        return $result;
    }
}
