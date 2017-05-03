<?php

class PublisherFactory {
    public static function make($type, array $params)
    {
        $publisher = ucwords($type) . 'Publisher';
        if (class_exists($publisher)) {
            return new $publisher($params);
        } else {
            throw new Exception("Invalid publisher type given.");
        }
    }
}
