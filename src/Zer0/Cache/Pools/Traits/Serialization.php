<?php

namespace Zer0\Cache\Traits;

trait Serialization
{
    /**
     * @param $value
     *
     * @return string
     */
    public function serialize ($value): string
    {
        return ($this->config->serializer ?? 'igbinary_serialize')($value);
    }

    /**
     * @param string $string
     *
     * @return mixed
     */
    public function unserialize (string $string)
    {
        foreach (explode(',', $this->config->unserializer ?? 'igbinary_unserialize') as $func) {
            if ($func === 'msgpack_unpack') {
                $unpacker = new \MessagePackUnpacker(false);
                $unpacker->feed($string);
                $unpacker->execute();
                $value = $unpacker->data();
                $unpacker->reset();
                if ($value === 0 && strlen($string) > 1) {
                    continue;
                }
                return $value;
            } else {
                return $func($string);
            }
        }
    }
}
